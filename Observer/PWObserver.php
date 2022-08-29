<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;
use Paymentwall\Paymentwall\Data\ShipmentData;
use Paymentwall\Paymentwall\Service\DeliveryConfirmation\DeliveryConfirmationServiceAbstract;

class PWObserver extends DeliveryConfirmationServiceAbstract implements ObserverInterface
{
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

        if (($paymentMethod == self::PWLOCAL_METHOD || $paymentMethod == self::BRICK)
            && ($order->getState() == 'complete')) {
            if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
                return;
            }

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
                $productType = self::TYPE_PHYSICAL;
            } else {
                $shipmentCreatedAt = $order->getCreatedAt();
                $shippingData = $order->getBillingAddress()->getData();
                $productType = self::TYPE_DIGITAL; // digital products don't have shipment
            }

            $pwShipmentData = new ShipmentData();
            $pwShipmentData->setCarrierType($carrierName)
                ->setTrackingCode($trackNumber)
                ->setProductType($productType)
                ->setShipmentCreatedAt($shipmentCreatedAt)
                ->setPaymentId($this->getPwPaymentId($order->getId()))
                ->setPaymentMethod($paymentMethod);

            $params = $this->prepareDeliveryParams($order, $shippingData, $pwShipmentData, self::STATUS_DELIVERED);

            return $this->sendDeliveryConfirmation($paymentMethod, $params);
        }

        return;
    }
}
