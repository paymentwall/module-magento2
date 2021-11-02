<?php

namespace Paymentwall\Paymentwall\Observer;

use Paymentwall\Paymentwall\Model\Paymentwall;
use Magento\Framework\Event\ObserverInterface;

class OrderEmailStop implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
            $order = $observer->getEvent()->getOrder();
            $this->_current_order = $order;

            $payment = $order->getPayment()->getMethodInstance()->getCode();

            if ($payment == Paymentwall::PAYMENT_METHOD_CODE) {
                $this->stopNewOrderEmail($order);
            }
        }
        catch (\Exception $ex)
        {

        }
    }

    public function stopNewOrderEmail(\Magento\Sales\Model\Order $order)
    {
        $order->setCanSendNewEmailFlag(false);
    }
}
