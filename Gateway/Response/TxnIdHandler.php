<?php
namespace Paymentwall\Paymentwall\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class TxnIdHandler implements HandlerInterface
{
    const TXN_ID = 'id';
    protected $riskStatus;

    private $privateInfoKey = [
        'brick_secure_token',
        'brick_charge_id'
    ];

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        $responseData = $response['responseData'];
        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment->setTransactionId($responseData[self::TXN_ID]);
        $payment->setIsTransactionPending(true);

        $payment->setAdditionalInformation('card_last4', "xxxx-".$responseData['card']['last4']);
        $payment->setAdditionalInformation('card_type', $responseData['card']['type']);
        if (!empty($responseData['risk'])) {
            $payment->setAdditionalInformation('risk_status', $responseData['risk']);
        }
    }
}
