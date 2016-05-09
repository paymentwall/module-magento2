<?php

namespace Paymentwall\Paymentwall\Model;

class Pingback
{
    protected $_objectManager;
    protected $_helper;
    const PINGBACK_OK = 'OK';
    const TRANSACTION_TYPE_ORDER = 'order';
    const STATE_PAID = 2;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager
    )
    {
        $this->_objectManager = $objectManager;
        $this->_helper = $this->_objectManager->get('Paymentwall\Paymentwall\Model\Helper');
    }

    public function pingback($getData)
    {
        $this->_helper->getInitConfig();
        $pingback = new \Paymentwall_Pingback($getData, $_SERVER['REMOTE_ADDR']);
        if ($pingback->validate()) {
            $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order');

            $orderIncrementId = $pingback->getProductId();
            $orderModel->loadByIncrementId($orderIncrementId);
            $orderStatus = $orderModel::STATE_CANCELED;

            if ($pingback->isDeliverable()) {
                $orderStatus = $orderModel::STATE_PROCESSING;
                $this->createOrderInvoice($orderModel, $pingback);
            } elseif ($pingback->isCancelable()) {
                $orderStatus = $orderModel::STATE_CANCELED;
            }
            $orderModel->setStatus($orderStatus);
            $orderModel->save();

            $result = self::PINGBACK_OK;
        } else {
            $result = $pingback->getErrorSummary();
        }
        return $result;
    }

    public function createOrderInvoice($order, $pingback)
    {
        if ($order->canInvoice()) {
            $invoice = $this->_objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
            $invoice->register();
            $invoice->setState(self::STATE_PAID);
            $invoice->save();

            $transactionSave = $this->_objectManager->create('Magento\Framework\DB\Transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            $order->addStatusHistoryComment(__('Created invoice #%1.', $invoice->getId()))->setIsCustomerNotified(true)->save();

            $this->createTransaction($order, $pingback);
        }
    }

    public function createTransaction($order, $pingback)
    {
        $payment = $this->_objectManager->create('Magento\Sales\Model\Order\Payment');
        $payment->setTransactionId($pingback->getReferenceId());
        $payment->setOrder($order);
        $payment->setIsTransactionClosed(1);
        $transaction = $payment->addTransaction(self::TRANSACTION_TYPE_ORDER);
        $transaction->beforeSave();
        $transaction->save();
    }

}