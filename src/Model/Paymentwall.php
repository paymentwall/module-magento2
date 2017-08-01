<?php
namespace Paymentwall\Paymentwall\Model;

use Magento\Sales\Model\Order;
use Magento\Customer\Model\Customer;

/**
 * Class Paymentwall
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Paymentwall extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CODE = 'paymentwall';

    protected $_code = self::PAYMENT_METHOD_CODE;

    protected $_objectManager;

    protected $_urlBuilder;

    protected $_currencyFactory;

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
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
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
        $this->_objectManager = $objectManager;
        $this->_urlBuilder = $urlBuilder;
        $this->_storeManager = $storeManager;
        $this->helperConfig = $helperConfig;
    }

    public function generateWidget(\Magento\Sales\Model\Order $order, \Magento\Customer\Model\Customer $customer)
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
                'success_url' => $this->_urlBuilder->getUrl('paymentwall/onepage/success/'),
            ],
            $userProfileData
        );

        $widget = new \Paymentwall_Widget(
            $uid, // id of the end-user who's making the payment
            $this->helperConfig->getConfig('widget_code'), // widget code, e.g. p1; can be picked inside of your merchant account
            $pwProducts,
            $additionalParams
        );

        return $widget;

    }

    public function getUserProfileData(\Magento\Sales\Model\Order $order, \Magento\Customer\Model\Customer $customer)
    {
        $data = array();
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
            $data = array_merge($data, $this->getUserExtraData($order, $customer));
        }
        return $data;
    }

    public function getUserExtraData(\Magento\Sales\Model\Order $order, \Magento\Customer\Model\Customer $customer) {
        $countOrders = 0;
        $totalAmount = 0;
        $customer_email = $order->getCustomerEmail();
        if (!empty($customer_email)) {
            $orderCollectionFactory = $this->_objectManager->create('Magento\Sales\Model\ResourceModel\Order\CollectionFactory');
            $salesOrderCollection = $orderCollectionFactory->create();
            $salesOrderCollection
                ->join(['order__payment' => $salesOrderCollection->getTable('sales_order_payment')],'order__payment.entity_id = main_table.entity_id')
                ->addFieldToFilter('customer_email', $customer_email)
                ->addFieldToFilter('order__payment.method','paymentwall')
                ->addFieldToFilter('status', Order::STATE_COMPLETE);
            $items = $salesOrderCollection->getItems();
            $USDcurrency = $this->_objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load('USD');
            foreach($items as $ord) {
                $countOrders++;
                $orderGrandTotal = $ord->getGrandTotal();
                if ($ord->getOrderCurrencyCode()!='USD') {
                    $orderGrandTotal = $this->currencyConvert($orderGrandTotal,$ord->getOrderCurrency(),$USDcurrency);
                }
                $totalAmount += $orderGrandTotal;
            }
        }
        $data = [
            'history[payments_amount]' => $totalAmount,
            'history[delivered_products]' => $countOrders,
        ];
        if (!empty($customer->getEntityId())) {
            $data = array_merge(
                $data,
                [
                    'history[registration_date]' => strtotime($customer->getData('created_at'))
                ]
            );
        }
        return $data;
    }

    public function currencyConvert($amount, $fromCurrency = null, $toCurrency = null)
    {
        if (!$fromCurrency){
            $fromCurrency = $this->_storeManager->getStore()->getBaseCurrency();
        }
        if (!$toCurrency){
            $toCurrency = $this->_storeManager->getStore()->getCurrentCurrency();
        }
        if (is_string($fromCurrency)) {
            $currencyFactory = $this->_objectManager->create('Magento\Directory\Model\CurrencyFactory');
            $rateToBase = $currencyFactory->create()->load($fromCurrency)->getAnyRate($this->_storeManager->getStore()->getBaseCurrency()->getCode());
        } elseif ($fromCurrency instanceof \Magento\Directory\Model\Currency) {
            $rateToBase = $fromCurrency->getAnyRate($this->_storeManager->getStore()->getBaseCurrency()->getCode());
        }
        $rateFromBase = $this->_storeManager->getStore()->getBaseCurrency()->getRate($toCurrency);
        if($rateToBase && $rateFromBase){
            $amount = $amount * $rateToBase * $rateFromBase;
        } else {
            throw new InputException(__('Please correct the target currency.'));
        }
        return $amount;

    }
    
    
}
