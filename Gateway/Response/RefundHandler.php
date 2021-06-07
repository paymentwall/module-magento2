<?php

namespace Paymentwall\Paymentwall\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Framework\Message\ManagerInterface;
use Paymentwall\Paymentwall\Gateway\Helper\SubjectReader;

class RefundHandler implements HandlerInterface
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    public function __construct(
        ManagerInterface $messageManager,
        SubjectReader $subjectReader
    ) {
        $this->messageManager = $messageManager;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        // TODO: handle refund request response
        if (isset($response['charge_id'])) {
            $payment->setTransactionId($response['charge_id'] . "_refund");
            $this->messageManager->addSuccessMessage(__('Paymentwall Brick refund successful.'));
        }
    }
}
