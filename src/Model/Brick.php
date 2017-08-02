<?php

namespace Paymentwall\Paymentwall\Model;

class Brick extends \Magento\Payment\Model\Method\Cc
{
    const STATE_PENDING_PAYMENT = 'pending_payment';
    const CODE                  = 'paymentwall_brick';

    protected $_code                = self::CODE;
    protected $_customerSession     = true;
    protected $_isGateway           = true;
    protected $_canCapture          = true;
    protected $_isInitializeNeeded  = true;
    protected $_checkoutSession = true;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data = array()
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_objectManager = $objectManager;
        $this->_helper = $this->_objectManager->get('Paymentwall\Paymentwall\Helper\Config');
        $this->_customerSession = $this->_objectManager->get('Magento\Customer\Model\Session');
        $this->_checkoutSession = $this->_objectManager->get('Magento\Checkout\Model\Session');

    }

    public function charge($paymentData, $orderId=null) {
        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
        $this->_helper->getInitBrickConfig();
        try {
            $charge = new \Paymentwall_Charge();
            $chargeData = array_merge(
                $this->prepareCardInfo($order, $paymentData),
                $this->prepareUserProfile($order), // for User Profile API
                $this->getExtraData()
            );
            $charge->create($chargeData);
            $response = $charge->getPublicData();
            $responseData = json_decode($charge->getRawResponseData(), true);
            if ($charge->isSuccessful() && empty($responseData['secure'])) {
                if ($charge->isCaptured()) {
                    $order->addStatusToHistory(self::STATE_PENDING_PAYMENT, "Payment capturing");
                }
                $this->_checkoutSession->unsBrickSessionData();
                return [
                    'result' => 1
                ];
            } elseif (!empty($responseData['secure'])) {
                $brickSessionData = array('orderId' => $orderId);
                $this->_checkoutSession->setBrickSessionData($brickSessionData);
                return [
                    'result' => 'secure',
                    'secure' => $responseData['secure']['formHTML']
                ];
            } else {
                $order->setStatus(self::STATE_PENDING_PAYMENT);
                $order->setState(self::STATE_PENDING_PAYMENT);
                $order->save();
                return [
                    'result' => 'error',
                    'message' => $responseData['error'],
                    'code' => $responseData['code']
                ];
            }
            return $response;
        } catch (\Exception $e) {
            $this->_logger->error(__('Payment capturing error: ' . $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__("Brick error(s):\n" . $e->getMessage()));
        }

        return $this;
    }


    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    public function validate()
    {
        return true;
    }

    public function getExtraData()
    {
        $customerId = $_SERVER['REMOTE_ADDR'];
        if ($this->_customerSession->isLoggedIn()) {
            $customerId = $this->_customerSession->getCustomer()->getId();
        }
        $baseUrl = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')
            ->getStore()
            ->getBaseUrl();
        return array(
            'integration_module' => 'magento2',
            'uid' => $customerId,
        );
    }

    public function prepareCardInfo($order, $data)
    {
        return array(
            'email' => $order->getCustomerEmail(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'token' => $data['additional_data']['paymentwall_pwbrick_token'],//$info->getAdditionalInformation('brick_token'),
            'fingerprint' => $data['additional_data']['paymentwall_pwbrick_fingerprint'],//$info->getAdditionalInformation('brick_fingerprint'),
            'description' => 'Order #' . $order->getIncrementId(),
            'plan' => $order->getIncrementId(),
            'secure_token' => !empty($data['additional_data']['brick_secure_token']) ? $data['additional_data']['brick_secure_token'] : '',
            'charge_id' => !empty($data['additional_data']['brick_charge_id']) ? $data['additional_data']['brick_charge_id'] : '',
        );
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        if (empty($data)) {
            return false;
        }
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('brick_token', $data->getData('paymentwall_pwbrick_token'))
            ->setAdditionalInformation('brick_fingerprint', $data->getData('paymentwall_pwbrick_fingerprint'));
        return $this;
    }

    protected function prepareUserProfile($order)
    {
        $billing = $order->getBillingAddress();
        return [
            'customer[city]' => $billing->getCity(),
            'customer[state]' => $billing->getRegion(),
            'customer[address]' => $billing->getStreetLine(1),
            'customer[country]' => $billing->getCountryId(),
            'customer[zip]' => $billing->getPostcode(),
            'customer[firstname]' => $billing->getFirstname(),
            'customer[lastname]' => $billing->getLastname()
        ];
    }
}