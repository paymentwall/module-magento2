<?php

namespace Paymentwall\Paymentwall\Model;

use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ObjectManager;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

$directoryList = ObjectManager::getInstance()->get('\Magento\Framework\App\Filesystem\DirectoryList');
$appPath = $directoryList->getPath('app');
if (!class_exists('Paymentwall_Config')) {
    require_once $appPath. '/code/Paymentwall/paymentwall-php/lib/paymentwall.php';
}

class Brick
{

    const PAYMENT_METHOD_CODE = 'brick';

    protected $helperConfig;
    protected $checkoutSession;
    protected $customerSession;
    protected $incrementId;
    private $adapter;

    public function __construct(
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        \Paymentwall\Paymentwall\Helper\Helper $helper
    )
    {
        $this->helperConfig = $helperConfig;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->helper = $helper;

    }

    public function charge($params)
    {
        $this->helperConfig->getInitBrickConfig();
        $chargeInfo = $this->prepareChargeInfo($params);
        $response = [
            'isSuccessful' => 0
        ];

        try {
            $this->adapter = new \Paymentwall_Charge();
            $this->adapter->create($chargeInfo);

            $response = [
                'payment' => json_decode($this->adapter->getRawResponseData(), true),
                'isSuccessful' => $this->adapter->isSuccessful(),
                'isCaptured' => $this->adapter->isCaptured(),
                'isUnderReview' => $this->adapter->isUnderReview(),
            ];

            $response = array_merge_recursive($response, json_decode($this->adapter->getPublicData(), true));

        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

    private function prepareChargeInfo($params)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        $order = $this->checkoutSession->getLastRealOrder();
        $quote = $this->checkoutSession->getQuote();
        $customerEmail = $quote->getBillingAddress()->getEmail();
        $billing = $quote->getBillingAddress();

        $userProfile = $this->prepareUserProfile($billing, $params);

        if ($this->config->getValue('payment/brick/user_profile_api', ScopeInterface::SCOPE_STORE)) {
            $userProfile = array_merge($userProfile, $this->helper->getUserExtraData($order, 'brick'));
        }

        $chargeInfo =  [
            'email' => $customerEmail,
            'amount' => $quote->getGrandTotal(),
            'currency' => $quote->getQuoteCurrencyCode(),
            'token' => $params['brick_token'],
            'fingerprint' => $params['brick_fingerprint'],
            'description' => "Magento2 order - ". $customerEmail,
            'plan' => 'brick_'. (string) time(),
        ];

        $chargeInfo = array_merge($chargeInfo, $userProfile);

        if (!empty($params['brick_charge_id']) && !empty($params['brick_secure_token'])) {
            $chargeInfo['charge_id'] = $params['brick_charge_id'];
            $chargeInfo['secure_token'] = $params['brick_secure_token'];
        }

        if (!empty($params['brick_reference_id'])) {
            $chargeInfo['reference_id'] = $params['brick_reference_id']; // Collected by Brick.js when customer click pay with 3DS 2.0 enabled
        }

        return $chargeInfo;
    }

    protected function prepareUserProfile($billing, $params)
    {
        return [
            'customer[city]' => $billing->getCity(),
            'customer[state]' => $billing->getRegionCode(),
            'customer[address]' => $billing->getStreetFull(),
            'customer[country]' => $billing->getCountryId(),
            'customer[zip]' => !empty($params['zip']) ? $params['zip'] : $billing->getPostcode(),
            'customer[firstname]' => !empty($params['firstname']) ? $params['firstname'] : $billing->getFirstname(),
            'customer[lastname]' => !empty($params['lastname']) ? $params['lastname'] : $billing->getLastname()
        ];
    }


}
