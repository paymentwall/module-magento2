<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;

class PWObserver implements ObserverInterface
{
    const PWLOCAL_METHOD            = 'paymentwall';

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
        $this->_helper = $this->_objectManager->get('Paymentwall\Paymentwall\Helper\Config');
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->_helper->getConfig('delivery_confirmation_aip'))
            return;

        //Observer execution code...
        $order = $observer->getEvent()->getOrder();
        if($order->getPayment()->getMethod() == self::PWLOCAL_METHOD && $order->getState() == 'complete') {
            $orderId = $order->getId();
            $payment = $order->getPayment();
            $allItems = array();
            $productTypes = array();
            foreach ($order->getAllItems() as $item) {
                $productTypes[] = $item->getProductType();
            }
            $productTypes = array_unique($productTypes);
            $productTypes = implode(",",$productTypes);

            if($order->hasShipments()) {
                $shipmentsCollection = $order->getShipmentsCollection();
                $shipments = $shipmentsCollection->getItems();
                $shipment = array_shift($shipments);
                $shipmentCreatedAt = $shipment->getCreatedAt();
                $shippingData = $shipment->getShippingAddress()->getData();
                $prodtype = 'physical';
            } else {
                $shipmentCreatedAt = $order->getCreatedAt();
                $shippingData = $order->getBillingAddress()->getData();
                $prodtype = 'digital'; // digital products don't have shipment
            }

            $transactionsCollection = $this->_objectManager->create('\Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory')->create()->addOrderIdFilter($orderId);
            $transactionItems = $transactionsCollection->getItems();
            $payment_id = '';
            foreach($transactionItems as $trans) {
                $transData = $trans->getData();
                if($transData['txn_id'])
                    $payment_id = $transData['txn_id'];
            }

            $this->_helper->getInitConfig();

            $params = array(
                'payment_id' => $payment_id,
                'merchant_reference_id' => $order->getIncrementId(),
                'type' => $prodtype,
                'status' => 'delivered',
                'estimated_delivery_datetime' => $shipmentCreatedAt,
                'estimated_update_datetime' => $shipmentCreatedAt,
                'refundable' => true,
                'shipping_address[country] ' => $shippingData['country_id'],
                'shipping_address[city] ' => $shippingData['city'],
                'shipping_address[zip] ' => $shippingData['postcode'],
                'shipping_address[state] ' => $shippingData['region'],
                'shipping_address[street] ' => $shippingData['street'],
                'shipping_address[phone] ' => $shippingData['telephone'],
                'shipping_address[firstname] ' => $shippingData['firstname'],
                'shipping_address[lastname] ' => $shippingData['lastname'],
                'shipping_address[email]' => $shippingData['email'],
                'is_test' => $this->_helper->getConfig('test_mode')
            );

            $delivery = new \Paymentwall_GenerericApiObject('delivery');
            $response = $delivery->post($params);
            return $response;
        }
    }
}