<?php
namespace Paymentwall\Paymentwall\Model;

class Paymentwall extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_PAYMENTWALL_CODE = 'paymentwall';

    protected $_code = self::PAYMENT_METHOD_PAYMENTWALL_CODE;

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

    }
}
