<?php

namespace Paymentwall\Paymentwall\Gateway\Request;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paymentwall\Paymentwall\Gateway\Helper\SubjectReader;

class RefundRequest implements BuilderInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $data = [
            'charge_id' => $payment->getAdditionalInformation()['brick_transaction_id']
        ];
        return $data;
    }
}
