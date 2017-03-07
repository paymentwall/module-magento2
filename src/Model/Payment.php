<?php

namespace Paymentwall\Paymentwall\Model;

class Payment extends \Magento\Payment\Model\Method\Cc
{
    const STATE_PENDING_PAYMENT = 'pending_payment';
    const CODE                  = 'paymentwall_brick';

    protected $_code                = self::CODE;
    protected $_customerSession     = true;
    protected $_isGateway           = true;
    protected $_canCapture          = true;
    protected $_canCapturePartial   = true;

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
        $this->_helper = $this->_objectManager->get('Paymentwall\Paymentwall\Model\Helper');
        $this->_customerSession = $this->_objectManager->get('Magento\Customer\Model\Session');


    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $this->_helper->getInitBrickConfig();

        try {
            $charge = new \Paymentwall_Charge();
            $chargeData = array_merge(
                $this->prepareCardInfo($order),
                $this->prepareUserProfile($order), // for User Profile API
                $this->getExtraData()
            );

            $charge->create($chargeData);
            $response = $charge->getPublicData();

            if ($charge->isSuccessful()) {
                if ($charge->isCaptured()) {
                    $order->addStatusToHistory(self::STATE_PENDING_PAYMENT, "Payment capturing");
                }
            } else {
                $result = json_decode($response, true);
                throw new \Magento\Framework\Validator\Exception(__("Brick error(s):\n" . $result['error']['message']));
            }
        } catch (\Exception $e) {
            $this->_logger->error(__('Payment capturing error: ' . $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__("Brick error(s):\n" . $e->getMessage()));
        }

        return $this;
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

        return array(
            'integration_module' => 'magento2',
            'uid' => $customerId
        );
    }

    public function prepareCardInfo($order)
    {
        $info = $this->getInfoInstance();
        return array(
            'email' => $order->getCustomerEmail(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'token' => $info->getAdditionalInformation('brick_token'),
            'fingerprint' => $info->getAdditionalInformation('brick_fingerprint'),
            'description' => 'Order #' . $order->getIncrementId(),
            'plan' => $order->getIncrementId(),
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