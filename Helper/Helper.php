<?php
namespace Paymentwall\Paymentwall\Helper;

use Magento\Sales\Model\Order;
use Magento\Framework\Exception\InputException;
use Magento\Framework\HTTP\ClientInterface;

class Helper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $objectManager;
    protected $storeManager;
    protected $customerSession;
    protected $orderCollection;
    protected $currencyFactory;
    protected $helperConfig;
    protected $client;
    protected $transactionSearchResultInF;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        ClientInterface $client,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
    ) {
        parent::__construct($context);
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->orderCollection = $orderCollection;
        $this->currencyFactory = $currencyFactory;
        $this->helperConfig = $helperConfig;
        $this->client = $client;
        $this->transactionSearchResultInF = $transactionSearchResultInterfaceFactory;
    }

    public function getUserExtraData(\Magento\Sales\Model\Order $order, $paymentMethod = 'paymentwall')
    {
        $countOrders = 0;
        $totalAmount = 0;
        $customer_email = $order->getCustomerEmail();
        if (!empty($customer_email)) {
            $orderCollectionFactory = $this->orderCollection;
            $salesOrderCollection = $orderCollectionFactory->create();
            $joinCondition = 'order__payment.entity_id = main_table.entity_id';
            $salesOrderCollection
                ->join(['order__payment' => $salesOrderCollection->getTable('sales_order_payment')], $joinCondition)
                ->addFieldToFilter('customer_email', $customer_email)
                ->addFieldToFilter('order__payment.method', $paymentMethod)
                ->addFieldToFilter('status', Order::STATE_COMPLETE);
            $items = $salesOrderCollection->getItems();
            $USDcurrency = $this->currencyFactory->create()->load('USD');
            foreach ($items as $ord) {
                $countOrders++;
                $orderGrandTotal = $ord->getGrandTotal();
                if ($ord->getOrderCurrencyCode()!='USD') {
                    $orderGrandTotal = $this->currencyConvert($orderGrandTotal, $ord->getOrderCurrency(), $USDcurrency);
                }
                $totalAmount += $orderGrandTotal;
            }
        }
        $data = [
            'history[payments_amount]' => $totalAmount,
            'history[delivered_products]' => $countOrders,
        ];
        if (!empty($this->customerSession->getCustomer()->getEntityId())) {
            $customerCreatedTime = $this->customerSession->getCustomer()->getData('created_at');
            $data = array_merge(
                $data,
                [
                    'history[registration_date]' => strtotime($customerCreatedTime)
                ]
            );
        }
        return $data;
    }

    public function currencyConvert($amount, $fromCurrency = null, $toCurrency = null)
    {
        if (!$fromCurrency) {
            $fromCurrency = $this->storeManager->getStore()->getBaseCurrency();
        }
        if (!$toCurrency) {
            $toCurrency = $this->storeManager->getStore()->getCurrentCurrency();
        }
        if (is_string($fromCurrency)) {
            $currencyFactory = $this->currencyFactory;
            $rateToBase = $currencyFactory->create()->load($fromCurrency)
                ->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        } elseif ($fromCurrency instanceof \Magento\Directory\Model\Currency) {
            $rateToBase = $fromCurrency->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        }
        $rateFromBase = $this->storeManager->getStore()->getBaseCurrency()->getRate($toCurrency);
        if ($rateToBase && $rateFromBase) {
            $amount = $amount * $rateToBase * $rateFromBase;
        } else {
            throw new InputException(__('Please correct the target currency.'));
        }
        return $amount;
    }

    public function getBrickExtraData(\Magento\Sales\Model\Order $order)
    {
        $customerId =  $order->getCustomerEmail();
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomer()->getId();
        }
        return [
            'integration_module' => 'magento2',
            'uid' => $customerId,
        ];
    }

    public function getRealUserIp() {
        if ($this->_request->getServer('HTTP_CLIENT_IP')) {
            return $this->_request->getServer('HTTP_CLIENT_IP');

        } elseif ($this->_request->getServer('HTTP_X_FORWARDED_FOR')) {
            $ips = explode(',', $this->_request->getServer('HTTP_X_FORWARDED_FOR'));
            return trim($ips[0]);

        } elseif ($this->_request->getServer('HTTP_X_FORWARDED')) {
            return $this->_request->getServer('HTTP_X_FORWARDED');

        } elseif ($this->_request->getServer('HTTP_FORWARDED_FOR')) {
            return $this->_request->getServer('HTTP_FORWARDED_FOR');

        } elseif ($this->_request->getServer('HTTP_FORWARDED')) {
            return $this->_request->getServer('HTTP_FORWARDED');

        } elseif ($this->_request->getServer('REMOTE_ADDR')) {
            return $this->_request->getServer('REMOTE_ADDR');
        }

        return '';
    }

    public function getPaymentInfo($gatewayTxnId)
    {
        try {
            $params = array(
                'key' => $this->helperConfig->getConfig('api_key'),
                'ref' => $gatewayTxnId,
                'sign_version' => 2
            );

            \Paymentwall_Config::getInstance()->set(array('private_key' => $this->helperConfig->getConfig('secret_key')));
            $params['sign'] = (new \Paymentwall_Signature_Widget())->calculate(
                $params,
                $params['sign_version']
            );

            $this->client->get(\Paymentwall_Config::API_BASE_URL . '/rest/payment/?'. http_build_query($params));
            return json_decode($this->client->getBody(), true);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPaymentAmount($gatewayTxnId)
    {
        $paymentInfo = $this->getPaymentInfo($gatewayTxnId);
        if (!empty($paymentInfo['amount'])) {
            return $paymentInfo['amount'];
        }

        return null;
    }

    /**
     * @param $orderId
     * @return string
     */
    public function getPwPaymentId($orderId)
    {
        $transactionsCollection = $this->transactionSearchResultInF->create()->addOrderIdFilter($orderId);
        $transactionItems = $transactionsCollection->getItems();
        $paymentId = '';

        foreach ($transactionItems as $trans) {
            $transData = $trans->getData();
            if ($transData['txn_id']) {
                $paymentId = $transData['txn_id'];
            }
        }

        return $paymentId;
    }
}
