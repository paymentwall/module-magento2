<?php
namespace Paymentwall\Paymentwall\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CaptureRequest implements BuilderInterface
{

    const AMOUNT = 'amount';
    const BRICK_TOKEN = 'brick_token';

    /**
     * @var ConfigInterface
     */
    private $config;

    private $checkoutSession;

    private $helperConfig;
    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Paymentwall\Paymentwall\Helper\Helper $helper
    ) {
        $this->config = $config;
        $this->checkoutSession  = $checkoutSession;
        $this->helper           = $helper;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];

        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }
        $additionalData = $payment->getAdditionalInformation();
        $tmpOrder = $payment->getOrder();
        if (!empty($additionalData['brick_secure_token']) && !empty($additionalData['brick_charge_id'])) {
            $brickSession = $this->checkoutSession->getBrickSessionData();
            if (!empty($brickSession['orderIncrementId'])) {
                $tmpOrder->setIncrementId($brickSession['orderIncrementId']);
                $this->checkoutSession->unsBrickSessionData();
            }
        }

        $cardInfo = $this->prepareCardInfo($tmpOrder, $additionalData);
        $userProfile = $this->prepareUserProfile($tmpOrder);

        if ($this->config->getValue('user_profile_api')) {
            $userProfile = array_merge($userProfile, $this->helper->getUserExtraData($tmpOrder, 'brick'));
        }

        $brickSecureToken = $additionalData['brick_secure_token'];
        $brickChargeId = $additionalData['brick_charge_id'];
        $result = [
            'cardInfo' => $cardInfo,
            'userProfile' => $userProfile,
            'extraData' => $this->helper->getBrickExtraData($tmpOrder),
            'isSecureEnabled' => empty($brickSecureToken) && empty($brickChargeId) ? 1 : 0,
            'orderIncrementId' => $tmpOrder->getIncrementId()
        ];
        return $result;
    }

    public function prepareCardInfo(\Magento\Sales\Model\Order $order, $additionalData)
    {
        $brickSecureToken = $additionalData['brick_secure_token'];
        return [
            'email' => $order->getCustomerEmail(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'token' => $additionalData['pwbrick_token'],
            'fingerprint' => $additionalData['pwbrick_fingerprint'],
            'description' => 'Order #' . $order->getIncrementId(),
            'plan' => $order->getIncrementId(),
            'secure_token' => !empty($brickSecureToken) ? $brickSecureToken : '',
            'charge_id' => !empty($additionalData['brick_charge_id']) ? $additionalData['brick_charge_id'] : '',
        ];
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
