<?php
namespace Paymentwall\Paymentwall\Model;

use Magento\Framework\Exception\CouldNotSaveException;

class Pingback
{
    protected $objectManager;
    protected $helper;
    protected $orderModel;
    protected $transactionSearchResult;
    protected $remoteAddress;
    protected $invoiceService;
    protected $dbTransaction;

    const PINGBACK_OK               = 'OK';
    const TRANSACTION_TYPE_ORDER    = 'order';
    const TRANSACTION_TYPE_CAPTURE  = 'capture';
    const STATE_PAID                = 2;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\Order $orderModel,
        \Magento\Sales\Api\Data\TransactionSearchResultInterface $transactionSearchResult,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $dbTransaction
    ) {
        $this->objectManager = $objectManager;
        $this->orderModel = $orderModel;
        $this->helper = $helperConfig;
        $this->transactionSearchResult = $transactionSearchResult;
        $this->remoteAddress = $remoteAddress;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
    }

    public function pingback($getData)
    {
        if (empty($getData['goodsid'])) {
            return "Order invalid !";
        }
        $orderIncrementId = $getData['goodsid'];
        $orderModel = $this->orderModel;
        $orderModel->loadByIncrementId($orderIncrementId);
        $method = $orderModel->getPayment()->getMethodInstance()->getCode();

        if ($method == Paymentwall::PAYMENT_METHOD_CODE) {
            $this->helper->getInitConfig();
        } else {
            $this->helper->getInitBrickConfig(true);
        }

        $objRemoteAddress = $this->remoteAddress;
        $realIp =  $objRemoteAddress->getRemoteAddress();

        $pingback = new \Paymentwall_Pingback($getData, $realIp);
        if ($pingback->validate()) {
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
                $orderInvoices = $orderModel->getInvoiceCollection();
                foreach ($orderInvoices as $invoice) {
                    $invoice->pay();
                    $invoice->save();
                }

                $transactions = $this->transactionSearchResult->addOrderIdFilter($orderModel->getId());
                $transactions->getItems();
                foreach ($transactions as $trans) {
                    $trans->close();
                }

                $orderStatus = $orderModel::STATE_PROCESSING;
                $orderModel->addStatusToHistory($orderStatus, "Brick payment successful.");
            } elseif ($pingback->isCancelable()) {
                $orderStatus = $orderModel::STATE_CANCELED;
                $orderModel->addStatusToHistory($orderStatus, "Payment canceled.");
            }

            $orderModel->setStatus($orderStatus)->setState($orderStatus);
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
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->setState(self::STATE_PAID);
                $invoice->save();

                $transactionSave = $this->dbTransaction
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
            $payment = $this->objectManager->create('Magento\Sales\Model\Order\Payment');
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
