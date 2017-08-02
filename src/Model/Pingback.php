<?php

namespace Paymentwall\Paymentwall\Model;

class Pingback
{
    protected $_objectManager;
    protected $_helper;
    const PINGBACK_OK               = 'OK';
    const TRANSACTION_TYPE_ORDER    = 'order';
    const TRANSACTION_TYPE_CAPTURE  = 'capture';
    const STATE_PAID                = 2;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig
    )
    {
        $this->_objectManager = $objectManager;
        $this->_helper = $helperConfig;
    }

    public function pingback($getData)
    {
        if (empty($getData['goodsid'])) {
            return "Order invalid !";
        }
        $orderIncrementId = $getData['goodsid'];
        $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order');
        $orderModel->loadByIncrementId($orderIncrementId);
        $method = $orderModel->getPayment()->getMethodInstance()->getCode();

        if ($method == Paymentwall::PAYMENT_METHOD_CODE) {
            $this->_helper->getInitConfig();
        } else {
            $this->_helper->getInitBrickConfig(true);
        }

        $objRemoteAddress = $this->_objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        $realIp =  $objRemoteAddress->getRemoteAddress();

        $pingback = new \Paymentwall_Pingback($getData, $realIp);
        if ($pingback->validate(true)) {
            if ($method == Paymentwall::PAYMENT_METHOD_CODE) {
                $result = $this->pwLocalPingback($orderModel, $pingback);
            } else {
                $result = $this->brickPingback($orderModel, $pingback);
            }
        } else {
            $result = $pingback->getErrorSummary();
        }
        return $result;
    }

    public function pwLocalPingback($orderModel, $pingback)
    {
        $orderStatus = $orderModel::STATE_CANCELED;
        if ($pingback->isDeliverable()) {
            $orderStatus = $orderModel::STATE_PROCESSING;
            $this->createOrderInvoice($orderModel, $pingback);
        } elseif ($pingback->isCancelable()) {
            $orderStatus = $orderModel::STATE_CANCELED;
        }
        $orderModel->setStatus($orderStatus);
        $orderModel->save();
        return self::PINGBACK_OK;
    }

    public function brickPingback($orderModel, $pingback)
    {
        $result = self::PINGBACK_OK;
        try {
            $orderStatus = $orderModel::STATE_CANCELED;
            if ($pingback->isDeliverable()) {
                $orderStatus = $orderModel::STATE_PROCESSING;
                $orderModel->addStatusToHistory($orderStatus, "Brick payment successful.");
                $this->createOrderInvoice($orderModel, $pingback);
            } elseif ($pingback->isCancelable()) {
                $orderStatus = $orderModel::STATE_CANCELED;
                $orderModel->addStatusToHistory($orderStatus, "Payment canceled.");
            } elseif ($pingback->isUnderReview()) {
                $orderStatus = $orderModel::STATE_PAYMENT_REVIEW;
                $orderModel->addStatusToHistory($orderStatus, "Payment review.");
            }
            $orderModel->setStatus($orderStatus);
            $orderModel->save();
        } catch (\Exception $e) {
            $result = "Transaction ID is invalid.";
        }
        return $result;
    }

    public function createOrderInvoice($order, $pingback)
    {
        try {
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
                $this->createTransaction($order, $pingback->getReferenceId());
            }
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('An error occurred when tried to create Order Invoice.'),
                $e
            );
        }
    }

    public function createTransaction($order, $referenceId, $type = self::TRANSACTION_TYPE_ORDER)
    {
        try {
            $payment = $this->_objectManager->create('Magento\Sales\Model\Order\Payment');
            $payment->setTransactionId($referenceId);
            $payment->setOrder($order);
            $payment->setIsTransactionClosed(1);
            $transaction = $payment->addTransaction($type);
            $transaction->beforeSave();
            $transaction->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('An error occurred when tried to create Order Transaction.'),
                $e
            );
        }
    }
}