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

    protected $helper;

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
        $this->helper = $helper;
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
            $data = array_merge($data, $this->helper->getUserExtraData($order, 'paymentwall'));
        }
        return $data;
    }

}
