<?php
namespace Paymentwall\Paymentwall\Helper;

use Magento\Sales\Model\Order;
use Magento\Framework\Exception\InputException;

class Helper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $objectManager;

    protected $storeManager;

    protected $_customerSession;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->_customerSession = $customerSession;
    }

    public function getUserExtraData(\Magento\Sales\Model\Order $order, $paymentMethod = 'paymentwall')
    {
        $countOrders = 0;
        $totalAmount = 0;
        $customer_email = $order->getCustomerEmail();
        if (!empty($customer_email)) {
            $orderCollectionFactory = $this->objectManager->create('Magento\Sales\Model\ResourceModel\Order\CollectionFactory');
            $salesOrderCollection = $orderCollectionFactory->create();
            $salesOrderCollection
                ->join(['order__payment' => $salesOrderCollection->getTable('sales_order_payment')], 'order__payment.entity_id = main_table.entity_id')
                ->addFieldToFilter('customer_email', $customer_email)
                ->addFieldToFilter('order__payment.method', $paymentMethod)
                ->addFieldToFilter('status', Order::STATE_COMPLETE);
            $items = $salesOrderCollection->getItems();
            $USDcurrency = $this->objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load('USD');
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
        if (!empty($this->_customerSession->getCustomer()->getEntityId())) {
            $data = array_merge(
                $data,
                [
                    'history[registration_date]' => strtotime($this->_customerSession->getCustomer()->getData('created_at'))
                ]
            );
        }
        return $data;
    }

    public function currencyConvert($amount, $fromCurrency = null, $toCurrency = null)
    {
        if (!$fromCurrency){
            $fromCurrency = $this->storeManager->getStore()->getBaseCurrency();
        }
        if (!$toCurrency){
            $toCurrency = $this->storeManager->getStore()->getCurrentCurrency();
        }
        if (is_string($fromCurrency)) {
            $currencyFactory = $this->objectManager->create('Magento\Directory\Model\CurrencyFactory');
            $rateToBase = $currencyFactory->create()->load($fromCurrency)->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        } elseif ($fromCurrency instanceof \Magento\Directory\Model\Currency) {
            $rateToBase = $fromCurrency->getAnyRate($this->storeManager->getStore()->getBaseCurrency()->getCode());
        }
        $rateFromBase = $this->storeManager->getStore()->getBaseCurrency()->getRate($toCurrency);
        if($rateToBase && $rateFromBase){
            $amount = $amount * $rateToBase * $rateFromBase;
        } else {
            throw new InputException(__('Please correct the target currency.'));
        }
        return $amount;
    }

    public function getBrickExtraData()
    {
        $obj = $this->objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        $customerId =  $obj->getRemoteAddress();
        if ($this->_customerSession->isLoggedIn()) {
            $customerId = $this->_customerSession->getCustomer()->getId();
        }
        return array(
            'integration_module' => 'magento2',
            'uid' => $customerId,
        );
    }
}
