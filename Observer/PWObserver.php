<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;

class PWObserver implements ObserverInterface
{
    const PWLOCAL_METHOD            = 'paymentwall';
    const BRICK             = 'brick';

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
    ) {
        $this->_objectManager = $objectManager;
        $this->_helper = $helperConfig;
        $this->transactionSearchResultInF = $transactionSearchResultInterfaceFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //Observer execution code...
        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if (($paymentMethod == self::PWLOCAL_METHOD
                || $paymentMethod == self::BRICK) && $order->getState() == 'complete') {
            if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
                return;
            }
            $orderId = $order->getId();

            if ($order->hasShipments()) {
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

            $transactionsCollection = $this->transactionSearchResultInF->create()->addOrderIdFilter($orderId);
            $transactionItems = $transactionsCollection->getItems();
            $payment_id = '';
            foreach ($transactionItems as $trans) {
                $transData = $trans->getData();
                if ($transData['txn_id']) {
                    $payment_id = $transData['txn_id'];
                }
            }

            if ($paymentMethod == self::PWLOCAL_METHOD) {
                $this->_helper->getInitConfig();
            } else {
                $this->_helper->getInitBrickConfig();
            }

            $params = [
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
                'shipping_address[state] ' => (!empty($shippingData['region']) ? $shippingData['region'] : 'N/A',
                'shipping_address[street] ' => $shippingData['street'],
                'shipping_address[phone] ' => $shippingData['telephone'],
                'shipping_address[firstname] ' => $shippingData['firstname'],
                'shipping_address[lastname] ' => $shippingData['lastname'],
                'shipping_address[email]' => $shippingData['email'],
                'is_test' => $this->_helper->getConfig('test_mode', $paymentMethod)
            ];

            $delivery = new \Paymentwall_GenerericApiObject('delivery');
            $response = $delivery->post($params);
            return $response;
        }
    }
}
