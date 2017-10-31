<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderHandlerObserver implements ObserverInterface
{
    const BRICK             = 'brick';
    protected $transactionSearchResult;

    public function __construct(
        \Magento\Sales\Api\Data\TransactionSearchResultInterface $transactionSearchResult
    ) {
        $this->transactionSearchResult = $transactionSearchResult;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //Observer execution code...
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        if ($payment->getMethod() == self::BRICK && $order->getState() == 'payment_review' && $payment->getAdditionalInformation('risk_status') != 'pending') {
            $orderInvoices = $order->getInvoiceCollection();
            foreach ($orderInvoices as $invoice) {
                $invoice->pay();
                $invoice->save();
            }

            $transactions = $this->transactionSearchResult->addOrderIdFilter($order->getId());
            $transactions->getItems();
            foreach ($transactions as $trans) {
                $trans->close();
            }

            $orderStatus = $order::STATE_PROCESSING;
            $order->addStatusToHistory($orderStatus, "Brick payment successful.");

            $order->setStatus($orderStatus)->setState($orderStatus);
            $order->save();
        }
    }
}
