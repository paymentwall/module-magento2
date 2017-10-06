<?php
namespace Paymentwall\Paymentwall\Helper;

use Magento\Sales\Model\Order;
use Magento\Framework\Exception\InputException;

class Helper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $objectManager;
    protected $storeManager;
    protected $customerSession;
    protected $orderCollection;
    protected $currencyFactory;
    protected $remoteAddress;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        parent::__construct($context);
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->orderCollection = $orderCollection;
        $this->currencyFactory = $currencyFactory;
        $this->remoteAddress = $remoteAddress;
    }

    public function getUserExtraData(\Magento\Sales\Model\Order $order, $paymentMethod = 'paymentwall')
    {
        $countOrders = 0;
        $totalAmount = 0;
        $customer_email = $order->getCustomerEmail();
        if (!empty($customer_email)) {
            $orderCollectionFactory = $this->orderCollection;
            $salesOrderCollection = $orderCollectionFactory->create();
            $joinCondition = 'order__payment.entity_id = main_table.entity_id';
            $salesOrderCollection
                ->join(['order__payment' => $salesOrderCollection->getTable('sales_order_payment')], $joinCondition)
                ->addFieldToFilter('customer_email', $customer_email)
                ->addFieldToFilter('order__payment.method', $paymentMethod)
                ->addFieldToFilter('status', Order::STATE_COMPLETE);
            $items = $salesOrderCollection->getItems();
            $USDcurrency = $this->currencyFactory->create()->load('USD');
            foreach ($items as $ord) {
                $countOrders++;
                $orderGrandTotal = $ord->getGrandTotal();
                if ($ord->getOrderCurrencyCode()!='USD') {
                    $orderGrandTotal = $this->currencyConvert($orderGrandTotal, $ord->getOrderCurrency(), $USDcurrency);
                }
                $totalAmount += $orderGrandTotal;
            }
        }
        $data = [
            'history[payments_amount]' => $totalAmount,
            'history[delivered_products]' => $countOrders,
        ];
        if (!empty($this->customerSession->getCustomer()->getEntityId())) {
            $customerCreatedTime = $this->customerSession->getCustomer()->getData('created_at');
            $data = array_merge(
                $data,
                [
                    'history[registration_date]' => strtotime($customerCreatedTime)
                ]
            );
        }
        return $data;
    }

    public function currencyConvert($amount, $fromCurrency = null, $toCurrency = null)
    {
        if (!$fromCurrency) {
            $fromCurrency = $this->storeManager->getStore()->getBaseCurrency();
        }
        if (!$toCurrency) {
            $toCurrency = $this->storeManager->getStore()->getCurrentCurrency();
        }
        if (is_string($fromCurrency)) {
            $currencyFactory = $this->currencyFactory;
            $rateToBase = $currencyFactory->create()->load($fromCurrency)
                ->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        } elseif ($fromCurrency instanceof \Magento\Directory\Model\Currency) {
            $rateToBase = $fromCurrency->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        }
        $rateFromBase = $this->storeManager->getStore()->getBaseCurrency()->getRate($toCurrency);
        if ($rateToBase && $rateFromBase) {
            $amount = $amount * $rateToBase * $rateFromBase;
        } else {
            throw new InputException(__('Please correct the target currency.'));
        }
        return $amount;
    }

    public function getBrickExtraData()
    {
        $obj = $this->remoteAddress;
        $customerId =  $obj->getRemoteAddress();
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomer()->getId();
        }
        return [
            'integration_module' => 'magento2',
            'uid' => $customerId,
        ];
    }
}
