<?php
namespace Paymentwall\Paymentwall\Model;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterface;
use \Magento\Framework\HTTP\ZendClientFactory;
use Magento\Quote\Model\Quote;
use \Magento\Sales\Model\Order;
use \Magento\Customer\Model\Customer;
use \Magento\Payment\Model\InfoInterface;
use \Magento\Framework\Message\ManagerInterface;
use \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use \Magento\Framework\App\RequestInterface;
use PHPUnit\Util\Exception;
use \Magento\Backend\Model\Auth\Session;
use Magento\Customer\Api\Data\GroupInterface;

/**
 * Class Paymentwall
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Paymentwall extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CODE = 'paymentwall';
    const DEFAULT_USER_ID = 'user101';

    const GATEWAY_BASE_URL = 'https://api.paymentwall.com';

    private $gatewayTxnId;

    protected $_code = self::PAYMENT_METHOD_CODE;
    protected $authSession;
    protected $gateway;
    protected $request;
    protected $objectManager;
    protected $urlBuilder;
    protected $helper;
    protected $client;
    protected $remoteAddress;
    protected $messageManager;
    protected $_storeManager;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $checkoutSession;
    protected $_quote;
    const NEW_CHECKOUT_FLOW_MERCHANT_ORDER_ID_PREFIX = 'MOD::';
    protected $customerRepository;
    protected $_customerSession;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Paymentwall\Paymentwall\Helper\Helper $helper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        ManagerInterface $messageManager,
        ClientInterface $client,
        RequestInterface $request,
        Session $authSession,
        array $data = [],
        \Magento\Checkout\Model\Session $checkoutSession,
        CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->objectManager = $objectManager;
        $this->urlBuilder = $urlBuilder;
        $this->_storeManager = $storeManager;
        $this->helperConfig = $helperConfig;
        $this->helper = $helper;
        $this->remoteAddress = $remoteAddress;
        $this->messageManager = $messageManager;
        $this->client = $client;
        $this->request = $request;
        $this->authSession = $authSession;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->_customerSession = $customerSession;
    }

    private function initGateway(&$params)
    {
        \Paymentwall_Config::getInstance()->set(array('private_key' => $this->helperConfig->getConfig('secret_key')));
        $params['sign'] = (new \Paymentwall_Signature_Widget())->calculate(
            $params,
            $params['sign_version']
        );
    }

    public function generateWidget(Order $order, Customer $customer, $paymentSystem = null)
    {
        $this->helperConfig->getInitConfig();

        $userProfileData = $this->getUserProfileData($order, $customer);
        $uid = ($customer->getEntityId()) ? $customer->getEntityId() : $userProfileData['customer_email'];
        unset($userProfileData['customer_email']);

        $pwProducts = [
            new \Paymentwall_Product(
                $order->getIncrementId(),
                $order->getData('total_due'),
                $order->getOrderCurrency()->getCode(),
                "Order #" . $order->getIncrementId(),
                \Paymentwall_Product::TYPE_FIXED
            )
        ];

        $additionalParams = array_merge(
            [
                'integration_module' => 'magento2',
                'test_mode' => $this->helperConfig->getConfig('test_mode'),
                'success_url' => $this->urlBuilder->getUrl('paymentwall/onepage/success/'),
            ],
            $userProfileData
        );

        if (!empty($paymentSystem)) {
            $additionalParams['ps'] = $paymentSystem;
        }

        $widget = new \Paymentwall_Widget(
            $uid, // id of the end-user who's making the payment
            $this->helperConfig->getConfig('widget_code'),
            $pwProducts,
            $additionalParams
        );

        return $widget;
    }

    /**
     * @param InfoInterface $payment
     * @param $amount // creditmemo's amount is DEFAULT processed in BASE CURRENCY, NOT ORDER CURRENCY
     * @return $this|Paymentwall
     * @throws LocalizedException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        if ($this->isCalledFromPingback()) {
            return $this->processRefundFromPaymentwallMA($payment, $amount);
        }

        $txnId = $this->getParentTransactionId($payment);
        if (empty($txnId)) {
            throw new LocalizedException(
                __("The payment is not captured!")
            );
        }
        $this->setGatewayTxnId($payment);
        $this->markCreditMemoAsOpen($payment->getCreditmemo());

        $order = $payment->getOrder();

        $isPartialRefund = $this->isPartialRefund($order, $amount);

        $refundAmountInPaidCurrency = null;
        if($isPartialRefund) {
            $refundAmountInPaidCurrency = $this->calculateRefundAmountInPaidCurrency($order, $amount);
        }

        $refundTransactionId = $this->buildPwRefundTransactionId($order, $refundAmountInPaidCurrency);
        $payment->setTransactionId($refundTransactionId);

        $result = $this->issueRefund($refundTransactionId, $isPartialRefund, $refundAmountInPaidCurrency);

        if (empty($result['result'])) {
            throw new LocalizedException(
                __("Issuing refund failed!, please try again!")
            );
        }
    }

    /**
     * @param InfoInterface $payment
     * @param $amount // amount in BASE CURRENCY
     * @return $this
     */
    protected function processRefundFromPaymentwallMA(InfoInterface $payment, $amount)
    {
        $canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();
        $amountInPaidCurrency = $this->calculateRefundAmountInPaidCurrency($payment->getOrder(), $amount);

        $refundTransactionId = $this->buildPwRefundTransactionId($payment->getOrder(), $amountInPaidCurrency);
        $this->importRefundResultToPayment($refundTransactionId, $payment, $canRefundMore);
        return $this;
    }

    /**
     * @param Order $order
     * @return bool
     */
    protected function isLastPartialRefund(Order $order)
    {
        $availableRefund = $order->getBaseTotalPaid() - $order->getBaseTotalRefunded();

        return $availableRefund == 0;
    }

    protected function markCreditMemoAsOpen(Order\Creditmemo $creditMemo)
    {
        $creditMemo->setState(Order\Creditmemo::STATE_OPEN);
    }

    public function getCurrentUserId()
    {
        $currentUser = $this->authSession->getUser();
        if (!empty($currentUser)) {
            return $currentUser->getId();
        }

        return 0;
    }

    /**
     * @param Order $order
     * @param null $refundAmount // in Paid Currency
     * @return string
     */
    protected function buildPwRefundTransactionId(Order $order, $refundAmount = null)
    {
        $base = 'pw_'.hash("md5", $this->getCurrentUserId()."_".$order->getIncrementId()."_".time());

        if (!empty($refundAmount)) {
            return $base."_".$refundAmount;
        }
        return $base;
    }

    protected function setGatewayTxnId($payment)
    {
        $this->gatewayTxnId = $this->getParentTransactionId($payment);
    }

    protected function getGatewayTxnId()
    {
        return $this->gatewayTxnId;
    }

    /**
     * @param Order $order
     * @param $amount // in BASE CURRENCY
     * @return float|int|null
     */
    protected function calculateRefundAmountInPaidCurrency(Order $order, $amount)
    {
        $paymentInfo = $this->helper->getPaymentInfo($this->getGatewayTxnId());
        if (empty($paymentInfo['currency'] || empty($paymentInfo['amount']))) {
            return null;
        }

        if ($paymentInfo['currency'] == $order->getBaseCurrencyCode()) {
            return $amount;
        }

        $paidAmount = $paymentInfo['amount'];

        if ($this->isLastPartialRefund($order)) {
            return $this->getLastRefundAmountInPaidCurrency($order, $paidAmount);
        }

        return round($paidAmount / $order->getBaseTotalPaid() * $amount, 2);
    }

    /**
     * @param Order $order
     * @param $paidAmount
     * @return float|int
     */
    protected function getLastRefundAmountInPaidCurrency(Order $order, $paidAmount)
    {
        $total = 0;
        foreach ($order->getCreditmemosCollection() as $creditMemo) {
            if (empty($creditMemo->getIncrementId())) continue;

            // if the memo was not created by refund online
            if (empty($creditMemo->getTransactionId())) {
                $amount = round($paidAmount / $order->getTotalPaid() * $creditMemo->getGrandTotal(), 2);
            } else{
                $amount = (float) $this->extractCreditMemoAmount($creditMemo->getTransactionId());
            }

            if (empty($amount)) continue;

            $total += $amount;
        }

        return $paidAmount - $total;
    }

    /**
     * @param $memoTxnId
     * @return float|null
     */
    protected function extractCreditMemoAmount($memoTxnId)
    {
        try {
            $exploded = explode('_', $memoTxnId);
            return (float) end($exploded);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param Order $order
     * @param $amount // in Base Currency
     * @return bool
     */
    protected function isPartialRefund(Order $order, $amount)
    {
        return $order->getBaseTotalPaid() != $amount;
    }

    protected function issueRefund($refundTransactionId, $isPartialRefund = false, $amount = null)
    {
        try {
            $params = [
                'key' => $this->helperConfig->getConfig('api_key'),
                'ref' => $this->getGatewayTxnId(),
                'sign_version' => 2,
                'type' => 1,
                'message' => 'Full refund ',
                'test_mode' => $this->helperConfig->getConfig('test_mode'),
                'merchant_refund_id' => $refundTransactionId
            ];

            if ($isPartialRefund) {
                $params['amount'] = $amount;
                $params['type'] = 5;
                $params['message'] = 'Partial refund amount = ' . $amount;
            }

            $this->initGateway($params);

            $this->client->post(self::GATEWAY_BASE_URL.'/developers/api/ticket', $params);
            return json_decode($this->client->getBody(), true);
        } catch (\Exception $e) {
            return null;
        }

    }

    public function getUserProfileData(\Magento\Sales\Model\Order $order)
    {
        $data = [];
        if ($order->hasShippingAddressId()) {
            $shippingData = $order->getShippingAddress()->getData();
        } elseif ($order->hasBillingAddressId()) {
            $shippingData = $order->getBillingAddress()->getData();
        }

        $customer_email = $order->getCustomerEmail();
        $data = array_merge($data, [
            'customer[city]' => $shippingData['city'],
            'customer[state]' => $shippingData['region'],
            'customer[address]' => $shippingData['street'],
            'customer[country]' => $shippingData['country_id'],
            'customer[zip]' => $shippingData['postcode'],
            'customer[firstname]' => $shippingData['firstname'],
            'customer[lastname]' => $shippingData['lastname'],
            'customer_email' => $customer_email
        ]);
        if ($this->helperConfig->getConfig('user_profile_api')) {
            $data = array_merge($data, $this->helper->getUserExtraData($order, 'paymentwall'));
        }
        return $data;
    }

    public function getLocalMethods($params)
    {
        $response = [
            'success' => 0
        ];

        try {
            $params = array_merge(
                [
                    'key' => $this->helperConfig->getConfig('api_key'),
                    'sign_version' => 2,
                    'img_size' => '@2x'
                ],
                $params
            );

            \Paymentwall_Config::getInstance()->set(['private_key' => $this->helperConfig->getConfig('secret_key')]);
            $params['sign'] = (new \Paymentwall_Signature_Widget())->calculate(
                $params,
                $params['sign_version']
            );

            $client = $this->objectManager->get(ZendClientFactory::class)->create();
            $client->setUri(\Paymentwall_Config::API_BASE_URL . '/payment-systems/?'. http_build_query($params));
            $client->setMethod(\Zend_Http_Client::GET);
            $json = json_decode($client->request()->getBody(), true);

            if (!empty($json['error'])) {
                throw new \Exception($json['error']);
            }

            $response['success'] = 1;
            $response['data'] = $json;
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }

        return json_encode($response);
    }

    public function getCountryByRemoteAddress()
    {
        $response = [
            'success' => 0
        ];

        try {
            $client = $this->objectManager->get(ZendClientFactory::class)->create();
            $client->setUri(\Paymentwall_Config::API_BASE_URL . '/rest/country');
            $client->setParameterPost([
                'key' => $this->helperConfig->getConfig('api_key'),
                'user_ip' => $this->helper->getRealUserIp() ?: $this->remoteAddress->getRemoteAddress(),
                'uid' => self::DEFAULT_USER_ID
            ]);
            $client->setMethod(\Zend_Http_Client::POST);
            $json = json_decode($client->request()->getBody(), true);

            if (!empty($json['error'])) {
                throw new \Exception($json['error']);
            }

            if (empty($json['code'])) {
                throw new \Exception('Missing country code');
            }

            $response['success'] = 1;
            $response['data'] = $json['code'];
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }

        return json_encode($response);
    }

    protected function getParentTransactionId(InfoInterface $payment)
    {
        return $payment->getParentTransactionId();
    }

    protected function importRefundResultToPayment($transactionId, $payment, $canRefundMore)
    {
        $payment->setTransactionId($transactionId)
            ->setIsTransactionClosed(true)
            ->setShouldCloseParentTransaction(!$canRefundMore);
    }

    protected function isCalledFromPingback()
    {
        return $this->request->getRouteName() == 'paymentwall'
            && $this->request->getModuleName() == 'paymentwall'
            && $this->request->getActionName() == 'pingback';
    }

    public function getPaymentWidget($paymentwallPaymentMethod)
    {
        $response = [];

        $paymentwallLocalMethods = $this->checkoutSession->getPaymentwallLocalMethod();

        $paymentwallLocalMethodIds = array_column($paymentwallLocalMethods, 'id');
        if (!in_array($paymentwallPaymentMethod, $paymentwallLocalMethodIds)) {
            return $response;
        }

        $quote = $this->getQuote();
        if (!$quote->hasItems()
            || $quote->getHasError()
            || $quote->getIsMultiShipping()
        ) {
            return null;
        }
        $onepageObj = $this->objectManager->get(\Magento\Checkout\Model\Type\Onepage::class);
        $checkoutMethod = $onepageObj->getCheckoutMethod();

        $isNewCustomer = false;

        switch ($checkoutMethod) {
            case Onepage::METHOD_GUEST:
                $this->_prepareGuestQuote();
                break;
            case Onepage::METHOD_REGISTER:
                $this->_prepareNewCustomerQuote();
                $isNewCustomer = true;
                break;
            default:
                $this->_prepareCustomerQuote();
                break;
        }

        if ($isNewCustomer) {
            try {
                $this->_involveNewCustomer();
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }

        $userProfileData = $this->getUserProfileByQuote($quote);

        $referenceId = $quote->getId();

        $this->helperConfig->getInitConfig();
        $pwProducts = [
            new \Paymentwall_Product(
                self::NEW_CHECKOUT_FLOW_MERCHANT_ORDER_ID_PREFIX . $quote->getId(),
                $quote->getGrandTotal(),
                $this->_storeManager->getStore()->getCurrentCurrency()->getCode(),
                "Ref id #" . $referenceId,
                \Paymentwall_Product::TYPE_FIXED
            )
        ];

        $additionalParams = array_merge(
            [
                'integration_module' => 'magento2',
                'test_mode' => $this->helperConfig->getConfig('test_mode'),
                'success_url' => $this->urlBuilder->getUrl('paymentwall/onepage/success') . '?quote-id=' . $referenceId,
                'ps' => $paymentwallPaymentMethod
            ],
            $userProfileData
        );
        $customerId = $_SERVER['REMOTE_ADDR'];
        $customerEmail = $quote->getBillingAddress()->getEmail();
        $customerId = !empty($customerEmail) ? $customerEmail : $customerId;
        $widget = new \Paymentwall_Widget(
            $customerId,
            $this->helperConfig->getConfig('widget_code'),
            $pwProducts,
            $additionalParams
        );
        $response['widget_url'] = $widget->getUrl();
        $response['widget_html_code'] = $widget->getHtmlCode();

        return $response;
    }

    public function getUserProfileByQuote(Quote $quote)
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'customer[city]' => $billingAddress->getCity(),
            'customer[state]' => $billingAddress->getRegion(),
            'customer[address]' => $billingAddress->getStreetFull(),
            'customer[country]' => $billingAddress->getCountryId(),
            'customer[zip]' => $billingAddress->getPostcode(),
            'customer[firstname]' => $billingAddress->getFirstname(),
            'customer[lastname]' => $billingAddress->getLastname(),
            'customer_email' => $billingAddress->getEmail()
        ];
    }

    public function getQuote()
    {
        if ($this->_quote === null) {
            return $this->checkoutSession->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function _prepareGuestQuote()
    {
        $quote = $this->getQuote();
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
        return $this;
    }

    /**
     * Prepare quote for customer registration and customer order submit
     *
     * @return void
     */
    protected function _prepareNewCustomerQuote()
    {
        $quote = $this->getQuote();
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        $customerBillingData = $billing->exportCustomerAddress();
        $dataArray = $this->_objectCopyService->getDataFromFieldset('checkout_onepage_quote', 'to_customer', $quote);
        $this->dataObjectHelper->populateWithArray(
            $customer,
            $dataArray,
            \Magento\Customer\Api\Data\CustomerInterface::class
        );
        $quote->setCustomer($customer)->setCustomerId(true);

        $customerBillingData->setIsDefaultBilling(true);

        if ($shipping) {
            if (!$shipping->getSameAsBilling()) {
                $customerShippingData = $shipping->exportCustomerAddress();
                $customerShippingData->setIsDefaultShipping(true);
                $shipping->setCustomerAddressData($customerShippingData);
                // Add shipping address to quote since customer Data Object does not hold address information
                $quote->addCustomerAddress($customerShippingData);
            } else {
                $shipping->setCustomerAddressData($customerBillingData);
                $customerBillingData->setIsDefaultShipping(true);
            }
        } else {
            $customerBillingData->setIsDefaultShipping(true);
        }
        $billing->setCustomerAddressData($customerBillingData);
        // TODO : Eventually need to remove this legacy hack
        // Add billing address to quote since customer Data Object does not hold address information
        $quote->addCustomerAddress($customerBillingData);
    }

    /**
     * Prepare quote for customer order submit
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _prepareCustomerQuote()
    {
        $quote = $this->getQuote();
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->customerRepository->getById($this->getCustomerSession()->getCustomerId());
        $hasDefaultBilling = (bool)$customer->getDefaultBilling();
        $hasDefaultShipping = (bool)$customer->getDefaultShipping();

        if ($shipping && !$shipping->getSameAsBilling() &&
            (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            $shippingAddress = $shipping->exportCustomerAddress();
            if (!$hasDefaultShipping) {
                //Make provided address as default shipping address
                $shippingAddress->setIsDefaultShipping(true);
                $hasDefaultShipping = true;
            }
            $quote->addCustomerAddress($shippingAddress);
            $shipping->setCustomerAddressData($shippingAddress);
        }

        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $billingAddress = $billing->exportCustomerAddress();
            if (!$hasDefaultBilling) {
                //Make provided address as default shipping address
                if (!$hasDefaultShipping) {
                    //Make provided address as default shipping address
                    $billingAddress->setIsDefaultShipping(true);
                }
                $billingAddress->setIsDefaultBilling(true);
            }
            $quote->addCustomerAddress($billingAddress);
            $billing->setCustomerAddressData($billingAddress);
        }
    }

    /**
     * Involve new customer to system
     *
     * @return $this
     */
    protected function _involveNewCustomer()
    {
        $customer = $this->getQuote()->getCustomer();
        $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());
        if ($confirmationStatus === \Magento\Customer\Model\AccountManagement::ACCOUNT_CONFIRMATION_REQUIRED) {
            $url = $this->_customerUrl->getEmailConfirmationUrl($customer->getEmail());
            $this->messageManager->addSuccessMessage(
            // @codingStandardsIgnoreStart
                __(
                    'You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.',
                    $url
                )
            // @codingStandardsIgnoreEnd
            );
        } else {
            $this->getCustomerSession()->loginById($customer->getId());
        }
        return $this;
    }

    /**
     * Get customer session object
     *
     * @return \Magento\Customer\Model\Session
     * @codeCoverageIgnore
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

}
