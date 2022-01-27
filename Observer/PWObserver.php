<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;

class PWObserver extends DeliveryConfirmationAbstract implements ObserverInterface
{
    const PWLOCAL_METHOD    = 'paymentwall';
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
                || $paymentMethod == self::BRICK) && ($order->getState() == 'complete')) {
            if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
                return;
            }
            $orderId = $order->getId();

            $trackNumber = '';
            $carrierName = '';
            if ($order->hasShipments()) {
                $shipmentsCollection = $order->getShipmentsCollection();
                $shipments = $shipmentsCollection->getItems();
                $shipment = array_shift($shipments);
                $shipmentCreatedAt = $shipment->getCreatedAt();
                $tracksCollection = $shipment->getTracksCollection();

                foreach ($tracksCollection->getItems() as $track) {
                    $trackNumber = $track->getTrackNumber();
                    $carrierName = $track->getTitle();
                }
                $shippingData = $shipment->getShippingAddress()->getData();
                $prodtype = self::PHYSICAL_PRODUCT;
            } else {
                $shipmentCreatedAt = $order->getCreatedAt();
                $shippingData = $order->getBillingAddress()->getData();
                $prodtype = self::DIGITAL_PRODUCT; // digital products don't have shipment
            }

            $pwShipmentData = new ShipmentData();
            $pwShipmentData->setCarrierType($carrierName)
                ->setTrackingCode($trackNumber)
                ->setProductType($prodtype)
                ->setShipmentCreatedAt($shipmentCreatedAt)
                ->setPaymentId($this->getPwPaymentId($order->getId()))
                ->setPaymentMethod($paymentMethod);

            $params = $this->prepareDeliveryParams($order, $shippingData, $pwShipmentData, "delivered");
            return $this->sendDeliveryConfirmation($paymentMethod, $params);
        }
    }
}
