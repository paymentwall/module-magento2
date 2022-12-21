<?php
namespace Paymentwall\Paymentwall\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface as OrderManagement;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use \Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Paymentwall\Paymentwall\Observer\PWObserver;
use \Magento\Sales\Model\Order\CreditmemoFactory;
use \Magento\Sales\Model\Order\Invoice;
use Magento\Quote\Model\QuoteFactory;
use Magento\Payment\Helper\Data as paymentData;

class Pingback
{
    protected $objectManager;
    protected $helperConfig;
    protected $helper;
    protected $orderModel;
    protected $transactionSearchResult;
    protected $transactionRepository;
    protected $invoiceService;
    protected $dbTransaction;
    protected $payment;
    protected $pingbackFactory;
    protected $orderSender;
    protected $checkoutSession;
    protected $searchCriteriaBuilder;
    protected $creditmemoService;
    protected $creditmemoFactory;
    protected $invoiceModel;
    protected $creditmemoRepository;

    const PINGBACK_OK               = 'OK';
    const PINGBACK_NOK               = 'NOK';
    const TRANSACTION_TYPE_ORDER    = 'order';
    const TRANSACTION_TYPE_CAPTURE  = 'capture';
    const STATE_PAID                = 2;
    const PAYMENTWALL_METHOD_CODE = 'paymentwall';

    const FULL_REFUND_TYPE = 2;
    const PARTIAL_REFUND_TYPE = 220;
    protected $quoteFactory;
    protected $quoteManagement;
    protected $customerFactory;
    protected $customerRepository;
    protected $storeManager;
    protected $orderRepository;
    protected $quoteRepository;
    protected $orderManagement;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\Order $orderModel,
        \Magento\Sales\Api\Data\TransactionSearchResultInterface $transactionSearchResult,
        TransactionRepositoryInterface $transactionRepository,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Paymentwall\Paymentwall\Helper\Helper $helper,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $dbTransaction,
        \Magento\Sales\Model\Order\Payment $payment,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Checkout\Model\Session $checkoutSession,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CreditmemoService $creditmemoService,
        CreditmemoFactory $creditmemoFactory,
        Invoice $invoiceModel,
        CreditmemoRepositoryInterface $creditmemoRepository,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        paymentData $paymentHelper,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        OrderManagement $orderManagement
    ) {
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->objectManager = $objectManager;
        $this->orderModel = $orderModel;
        $this->helperConfig = $helperConfig;
        $this->helper = $helper;
        $this->transactionSearchResult = $transactionSearchResult;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->payment = $payment;
        $this->orderSender = $orderSender;
        $this->checkoutSession = $checkoutSession;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->creditmemoService = $creditmemoService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->invoiceModel = $invoiceModel;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->quoteManagement = $quoteManagement;
        $this->paymentHelper = $paymentHelper;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->orderManagement = $orderManagement;
    }

    public function pingback($getData)
    {
        $realIp = $this->helper->getRealUserIp();
        $pingback = new \Paymentwall_Pingback($getData, $realIp);
        if (!$pingback->validate(true)) {
            $result = $pingback->getErrorSummary();
            return $result;
        }

        $referenceId = $pingback->getProductId();
        if (str_contains($referenceId, Paymentwall::NEW_CHECKOUT_FLOW_MERCHANT_ORDER_ID_PREFIX) && !self::isRefundPingback($pingback)) {
            $quoteId = str_replace(Paymentwall::NEW_CHECKOUT_FLOW_MERCHANT_ORDER_ID_PREFIX, '', $referenceId);
            $quote = $this->quoteFactory->create()->load($quoteId);
            if (!$quote) {
                return;
            }
            $paymentMethod = $quote->getPayment()->getMethod();
            if (!$this->isPaymentwallPaymentMethod($paymentMethod)) {
                return;
            }

            if ($quote->getIsActive()) {
                $orderModel = $this->quoteManagement->submit($quote);
            } else {
                $order = $this->orderModel->loadByAttribute('quote_id', $referenceId);
                $orderId = $order->getId();

                if (!$orderId) {
                    $orderModel = $this->quoteManagement->submit($quote);
                } elseif ($order->getState() == Order::STATE_PROCESSING
                    || $order->getState() == Order::STATE_COMPLETE) {
                    return self::PINGBACK_OK;
                }
            }
        } else {
            $orderModel = $this->orderModel;
            $this->getOrder($orderModel, $getData);
        }

        $this->checkoutSession->setLastOrderId($orderModel->getId());

        if (($orderModel->getId())) {
            $method = $orderModel->getPayment()->getMethodInstance()->getCode();

            $realIp = $this->helper->getRealUserIp();
            $pingback = new \Paymentwall_Pingback($getData, $realIp);

            if ($method == Paymentwall::PAYMENT_METHOD_CODE) {
                $result = $this->pwLocalPingback($orderModel, $pingback);
            } elseif ($method == Brick::PAYMENT_METHOD_CODE) {
                $result = $this->brickPingback($orderModel, $pingback);
            }

            return $result;
        }

        return self::PINGBACK_OK;
    }

    protected function getOrder(&$orderModel, $pingbackParams)
    {

        if (strpos($pingbackParams['goodsid'], 'brick_') === false) {
            $orderIncrementId = $pingbackParams['goodsid'];
            $orderModel->loadByIncrementId($orderIncrementId);
            return;
        }

        $transactionId = substr($pingbackParams['ref'], 1);
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('txn_id', $transactionId)->create();
        $transactions = $this->transactionRepository->getList($searchCriteria);

        foreach ($transactions->getItems() as $transaction) {
            $orderModel->load($transaction->getOrderId());
            if ($orderModel->getPayment()->getMethodInstance()->getCode() != Brick::PAYMENT_METHOD_CODE) {
                continue;
            }
            break;
        }
    }

    public function pwLocalPingback($orderModel, $pingback)
    {
        if (self::isRefundPingback($pingback)) {
            return $this->handlePwLocalRefundPingback($orderModel, $pingback);
        }

        return $this->handlePwLocalPaymentPingback($orderModel, $pingback);
    }

    /**
     * @param Order $orderModel
     * @param $pingback
     * @return string
     */
    protected function handlePwLocalRefundPingback(Order $orderModel, $pingback)
    {
        try {
            if ($this->isRefundWithoutTicket($pingback)) {
                return $this->handleRefundPingbackFromMA($orderModel, $pingback);
            }
            $refundTxnId = $pingback->getParameter('merchant_refund_id');

            $creditMemo = $this->getCreditMemo($refundTxnId);
            $creditMemo->setState(Creditmemo::STATE_REFUNDED);
            $this->creditmemoRepository->save($creditMemo);

            if (self::isCompletedRefundOrder($orderModel)) {
                $orderModel->setState(Order::STATE_CLOSED);
                $orderModel->save();
            }
            return self::PINGBACK_OK;
        } catch (\Exception $e) {
            return self::PINGBACK_NOK;
        }

    }

    /**
     * @param Order $order
     * @param $pingback
     * @return string
     */
    protected function handleRefundPingbackFromMA(Order $order, $pingback)
    {
        try {
            $creditMemo = $this->createCreditMemo($order, $pingback);
            if (empty($creditMemo)) {
                throw new \Exception();
            }
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                $invoiceIncrementId = $invoice->getIncrementId();
            }

            $invoiceobj = $this->invoiceModel->loadByIncrementId($invoiceIncrementId);
            // Don't set invoice if you want to do offline refund
            $creditMemo->setInvoice($invoiceobj);

            $this->creditmemoService->refund($creditMemo);
            return self::PINGBACK_OK;
        } catch (\Exception $e) {
            return self::PINGBACK_NOK;
        }
    }

    /**
     * @param Order $order
     * @param $pingback
     * @return Creditmemo
     */
    protected function createCreditMemo(Order $order, $pingback)
    {
        $amount = $this->calculateCreditemoAmount($order, $pingback);
        if (empty($amount)) {
            return null;
        }

        $refundItems = [];
        // Must have, if omit this step creditMemoService will get all item quantity as refund quantity
        foreach ($order->getAllItems() as $orderItem) {
            $refundItems[$orderItem->getId()] = 0;
        }

        // Must have, if omit this shipping_amount creditMemoService will get shipping_amount as apart of refund amount
        $creditmemo = $this->creditmemoFactory->createByOrder($order, [
            'qtys' => $refundItems,
            'adjustment_positive' => $amount,
            'shipping_amount' => 0,
            'adjustment_negative' => 0
        ]);

        return $creditmemo;
    }

    /**
     * @param Order $order
     * @param $pingback
     * @return float|null
     */
    protected function calculateCreditemoAmount(Order $order, $pingback)
    {
        if (!self::isPartialRefundPingback($pingback)) {
            return $order->getBaseTotalPaid();
        }

        if (empty($pingback->getParameter('refund_amount'))) {
            return null;
        }

        $refundAmountInPaidCurrency = $pingback->getParameter('refund_amount');
        $chargeId = substr($pingback->getParameter('ref'), 1);
        $totalAmountPaidForGateway = $this->helper->getPaymentAmount($chargeId);

        if (empty($totalAmountPaidForGateway)) {
            return null;
        }

        return round($refundAmountInPaidCurrency / $totalAmountPaidForGateway * $order->getBaseTotalPaid(), 2);
    }

    /**
     * @param $pingback
     * @return bool
     */
    protected function isRefundWithoutTicket($pingback)
    {
        if (empty($pingback->getParameter('merchant_refund_id'))) {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     * @return bool
     */
    public static function isCompletedRefundOrder(Order $order)
    {
        if ($order->getTotalRefunded() != $order->getTotalPaid()) {
            return false;
        }

        foreach($order->getCreditmemosCollection() as $creditMemo) {
            if ($creditMemo->getState() != Creditmemo::STATE_REFUNDED) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $pingback
     * @return bool
     */
    public static function isRefundPingback($pingback)
    {
        $pingbackType = $pingback->getType();
        if ($pingbackType == self::FULL_REFUND_TYPE || $pingbackType == self::PARTIAL_REFUND_TYPE) {
            return true;
        }

        return false;
    }

    /**
     * @param $pingback
     * @return bool
     */
    public static function isPartialRefundPingback($pingback)
    {
        return $pingback->getType() == self::PARTIAL_REFUND_TYPE;
    }

    /**
     * @param $refundTxnId
     * @return \Magento\Sales\Api\Data\CreditmemoInterface|null
     */
    protected function getCreditMemo($refundTxnId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'transaction_id',
                $refundTxnId
            )->create()
        ;

        $creditMemoSearchResult = $this->creditmemoRepository->getList($searchCriteria);

        if ($creditMemoSearchResult->getTotalCount() == 0) {
            return null;
        }

        return current($creditMemoSearchResult->getItems());
    }

    protected function handlePwLocalPaymentPingback($orderModel, $pingback)
    {
        if ($pingback->isDeliverable()) {
            $orderStatus = $orderModel::STATE_PROCESSING;
            $orderModel->setStatus($orderStatus);
            $orderModel->save();
            $this->createOrderInvoice($orderModel, $pingback);
        } elseif ($pingback->isCancelable()) {
            $orderStatus = $orderModel::STATE_CANCELED;
            $orderModel->setStatus($orderStatus);
            $orderModel->save();
        }

        $this->checkoutSession->setForceOrderMailSentOnSuccess(true);
        $this->orderSender->send($orderModel, true);
        return self::PINGBACK_OK;
    }

    public function brickPingback($orderModel, $pingback)
    {
        $result = self::PINGBACK_OK;

        try {
            if (self::isFullRefundPingback($pingback)) {
                return $this->handleBrickRefundPingback($orderModel);
            }

            return $this->handleBrickPingback($orderModel, $pingback);
        } catch (\Exception $e) {
            $result = $e->getMessage();
        }
        return $result;
    }

    /**
     * @param Order $orderModel
     * @param $pingback
     * @return string
     */
    protected function handleBrickPingback(Order $orderModel, $pingback)
    {
        try {
            if ($orderModel->getState() == Order::STATE_CLOSED) {
                return 'Order was closed';
            }

            $orderProcessingStatus = $orderModel::STATE_PROCESSING;

            if ($pingback->isDeliverable()) {
                if ($orderModel->getState() == $orderProcessingStatus) {
                    return self::PINGBACK_OK;
                }

                $orderInvoices = $orderModel->getInvoiceCollection();
                foreach ($orderInvoices as $invoice) {
                    $invoice->pay();
                    $invoice->setTransactionId($pingback->getReferenceId());
                    $invoice->save();
                }

                $transactions = $this->transactionSearchResult->addOrderIdFilter($orderModel->getId());
                $transactions->getItems();
                foreach ($transactions as $trans) {
                    $trans->close();
                }

                $orderStatus = $orderProcessingStatus;
                $orderModel->addStatusToHistory($orderStatus, "Brick payment successful.");
            } elseif (self::isRiskReviewDeclinedPingback($pingback)) {
                $orderStatus = $orderModel::STATE_CANCELED;
                $orderModel->addStatusToHistory($orderStatus, "Payment canceled.");
            }

            if (!empty($orderStatus)) {
                $orderModel->setStatus($orderStatus)->setState($orderStatus);
                $orderModel->setTotalPaid($orderModel->getGrandTotal());
                $orderModel->setBaseTotalPaid($orderModel->getBaseGrandTotal());
                $orderModel->save();
            }

            return self::PINGBACK_OK;
        } catch (\Exception $e) {
            return self::PINGBACK_NOK;
        }
    }

    /**
     * Handling refund from Paymentwall MA
     * @param Order $order
     * @return string
     */
    protected function handleBrickRefundPingback(Order $order)
    {
        try {
            $orderClosedStatus = Order::STATE_CLOSED;
            if ($order->getState() == $orderClosedStatus) {
                return self::PINGBACK_OK;
            }
            $creditMemo = $this->createCreditMemoForBrickRefund($order);

            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                $invoiceIncrementId = $invoice->getIncrementId();
            }

            $invoiceobj = $this->invoiceModel->loadByIncrementId($invoiceIncrementId);
            // Don't set invoice if you want to do offline refund
            $creditMemo->setInvoice($invoiceobj);

            $this->creditmemoService->refund($creditMemo);

            $order->addStatusToHistory($orderClosedStatus, "Brick Refund successful.");
            $order->setState($orderClosedStatus);
            $order->save();

            return self::PINGBACK_OK;
        } catch (\Exception $e) {
            return self::PINGBACK_NOK;
        }
    }

    /**
     * @param Order $order
     * @return Creditmemo
     */
    protected function createCreditMemoForBrickRefund(Order $order)
    {
        $refundItems = [];
        // Must have, if omit this step creditMemoService will get all item quantity as refund quantity
        foreach ($order->getAllItems() as $orderItem) {
            $refundItems[$orderItem->getId()] = 0;
        }

        // Must have, if omit this shipping_amount creditMemoService will get shipping_amount as apart of refund amount
        $creditmemo = $this->creditmemoFactory->createByOrder($order, [
            'qtys' => $refundItems,
            'adjustment_positive' => $order->getBaseTotalPaid(),
            'shipping_amount' => 0,
            'adjustment_negative' => 0
        ]);

        return $creditmemo;
    }

    /**
     * @param $pingback
     * @return bool
     */
    public static function isFullRefundPingback($pingback)
    {
        return $pingback->getType() == \Paymentwall_Pingback::PINGBACK_TYPE_NEGATIVE;
    }

    /**
     * @param $pingback
     * @return bool
     */
    public static function isRiskReviewDeclinedPingback($pingback)
    {
        return $pingback->getType() == \Paymentwall_Pingback::PINGBACK_TYPE_RISK_REVIEWED_DECLINED;
    }

    public function createOrderInvoice(\Magento\Sales\Model\Order $order, $pingback)
    {
        try {
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->setState(self::STATE_PAID);
                $invoice->setTransactionId($pingback->getReferenceId());
                $invoice->save();

                $transactionSave = $this->dbTransaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();

                $order->addStatusHistoryComment(__('Created invoice #%1. Paymentwall transaction reference ID: %2', [$invoice->getId(), $pingback->getReferenceId()]))->setIsCustomerNotified(true)->save();

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
            $payment = $this->payment;
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

    /**
     * @param $paymentMethod
     * @return bool
     */
    private function isPaymentwallPaymentMethod($paymentMethod)
    {
        if ($paymentMethod == Brick::PAYMENT_METHOD_CODE || $paymentMethod == self::PAYMENTWALL_METHOD_CODE) {
            return true;
        }

        return false;
    }

}
