<?php
namespace Paymentwall\Paymentwall\Model;

use Magento\Framework\App\ObjectManager;
use \Magento\Framework\HTTP\ZendClientFactory;
use \Magento\Sales\Model\Order;
use \Magento\Customer\Model\Customer;
use \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

$directoryList = ObjectManager::getInstance()->get('\Magento\Framework\App\Filesystem\DirectoryList');
$appPath = $directoryList->getPath('app');
if (!class_exists('Paymentwall_Config')) {
    require_once $appPath. '/code/Paymentwall/paymentwall-php/lib/paymentwall.php';
}
/**
 * Class Paymentwall
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Paymentwall extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CODE = 'paymentwall';
    const DEFAULT_USER_ID = 'user101';

    protected $_code = self::PAYMENT_METHOD_CODE;
    protected $objectManager;
    protected $urlBuilder;
    protected $helper;
    protected $remoteAddress;

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
        $this->objectManager = $objectManager;
        $this->urlBuilder = $urlBuilder;
        $this->_storeManager = $storeManager;
        $this->helperConfig = $helperConfig;
        $this->helper = $helper;
        $this->remoteAddress = $remoteAddress;
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
                'user_ip' => $this->remoteAddress->getRemoteAddress(),
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
}
