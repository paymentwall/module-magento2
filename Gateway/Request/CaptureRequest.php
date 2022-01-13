<?php
namespace Paymentwall\Paymentwall\Gateway\Request;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class CaptureRequest implements BuilderInterface
{
    protected $invoiceService;
    protected $dbTransaction;
    protected $payment;
    protected $cart;

    /**
     * @var ConfigInterface
     */
    private $config;

    private $checkoutSession;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Paymentwall\Paymentwall\Helper\Helper $helper,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $dbTransaction,
        \Magento\Sales\Model\Order\Payment $payment

    ) {
        $this->config = $config;
        $this->checkoutSession  = $checkoutSession;
        $this->helper           = $helper;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->payment = $payment;
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

        if ($additionalData['is_under_review']) {
            $this->processUnderReviewOrderPayment($tmpOrder, $additionalData);
        }

        $result = [
            'card_info' => $this->prepareCardInfo($additionalData),
            'charge_state' => [
                'is_captured' => $additionalData['is_captured'],
                'is_under_review' => $additionalData['is_under_review'],
            ],
            'transaction_id' => $additionalData['brick_transaction_id']
        ];

        $result = array_merge($result, $this->risk($additionalData));

        return $result;
    }

    public function processUnderReviewOrderPayment(Order $order, $additionalData)
    {

        $order->setStatus(Order::STATE_PAYMENT_REVIEW)->setState(Order::STATE_PAYMENT_REVIEW);
        $order->save();

        $order->setTotalPaid(0)
            ->setBaseTotalPaid(0)
            ->setBaseTotalInvoiced(0)
            ->setTotalInvoiced(0)
            ->setBaseSubtotalInvoiced(0)
            ->setSubtotalInvoiced(0)
            ->setBaseShippingInvoiced(0)
            ->setShippingInvoiced(0)
            ->setBaseDiscountInvoiced(0)
            ->setDiscountInvoiced(0);

        foreach ($order->getAllItems() as $item) {
            $item->setBaseRowInvoiced(0);
            $item->setRowInvoiced(0);
            $item->setQtyInvoiced(0);
            $item->setDiscountInvoiced(0);
            $item->setBaseDiscountInvoiced(0);
            $item->setTaxInvoiced(0);
            $item->setBaseTaxInvoiced(0);
            $item->save();
        }
        $order->save();
        $this->setRealOrder($order);

        $this->createInvoice($order);
        $this->createTransaction($order, $additionalData);
        $this->clearCart();
    }

    private function setRealOrder($order)
    {
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getRealOrderId());
    }

    private function clearCart()
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->delete();
    }

    private function createInvoice(Order $order)
    {
        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setShippingAmount($order->getShippingAmount());
            $invoice->setBaseShippingAmount($order->getBaseShippingAmount());
            $invoice->setGrandTotal($order->getGrandTotal());
            $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
            $invoice->setTaxAmount($order->getTaxAmount());
            $invoice->setBaseTaxAmount($order->getBaseTaxAmount());
            $invoice->setDiscountAmount($order->getDiscountAmount());
            $invoice->setBaseDiscountAmount($order->getBaseDiscountAmount());
            $invoice->setDiscountTaxCompensationAmount($order->getDiscountTaxCompensationAmount());
            $invoice->setBaseDiscountTaxCompensationAmount($order->getBaseDiscountTaxCompensationAmount());
            $invoice->setShippingDiscountTaxCompensationAmount($order->getShippingDiscountTaxCompensationAmount());
            $invoice->setBaseShippingDiscountTaxCompensationAmnt($order->getBaseShippingDiscountTaxCompensationAmnt());

            $invoice->register();
            $invoiceState = \Magento\Sales\Model\Order\Invoice::STATE_OPEN;
            $invoice->setState($invoiceState);
            $invoice->save();

            $transactionSave = $this->dbTransaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            $order->addStatusHistoryComment(__('Created invoice #%1.', $invoice->getId()))
            ->setIsCustomerNotified(true)->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('An error occurred when tried to create Order Invoice.'),
                $e
            );
        }
    }

    private function createTransaction(Order $order, $additionalData)
    {
        try {
            $payment = $this->payment;
            $payment->setTransactionId($additionalData['brick_transaction_id']);
            $payment->setAdditionalInformation('card_last4', "xxxx-".$additionalData['card_last_four']);
            $payment->setAdditionalInformation('card_type', $additionalData['card_type']);
            $payment->setCcLast4($additionalData['card_last_four']);
            $payment->setCcType($additionalData['card_type']);
            $payment->setOrder($order);
            $payment->setIsTransactionClosed(0);
            $transaction = $payment->addTransaction('capture');
            $transaction->beforeSave();
            $transaction->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('An error occurred when tried to create Order Transaction.'),
                $e
            );
        }
    }

    public function prepareCardInfo($additionalData)
    {
        return [
            'card_type' => $additionalData['card_type'],
            'card_last4' => $additionalData['card_last_four'],
        ];
    }

    public function risk($additionalData)
    {
        if (!empty($additionalData['risk'])) {
            return [
                'risk' => $additionalData['brick_risk'],
            ];
        }

        return [];
    }

}
