<?php

namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderShipmentTrackObserver extends DeliveryConfirmationAbstract implements ObserverInterface
{
    const PWLOCAL_METHOD    = 'paymentwall';
    const BRICK             = 'brick';

    protected $logger;
    protected $transactionSearchResultInF;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultInterfaceFactory
    ) {
        $this->logger = $logger;
        $this->_helper = $helperConfig;
        $this->transactionSearchResultInF = $transactionSearchResultInterfaceFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $tracking = $observer->getEvent()->getTrack();
        $shipment = $tracking->getShipment();
        $order = $shipment->getOrder();

        $paymentMethod = $order->getPayment()->getMethod();
        if (($paymentMethod == self::PWLOCAL_METHOD
                || $paymentMethod == self::BRICK) && ($order->getState() == 'complete')) {
            if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
                return;
            }

            if ($order->hasShipments()) {
                $shipmentCreatedAt = $shipment->getCreatedAt();
                $shippingData = $shipment->getShippingAddress()->getData();
                $prodtype = self::PHYSICAL_PRODUCT;
            } else {
                $shipmentCreatedAt = $order->getCreatedAt();
                $shippingData = $order->getBillingAddress()->getData();
                $prodtype = self::DIGITAL_PRODUCT; // digital products don't have shipment
            }

            $trackingData = $tracking->toArray();
            $pwShipmentData = new ShipmentData();
            $pwShipmentData->setCarrierType($trackingData['carrier_code'])
                ->setTrackingCode($trackingData['track_number'])
                ->setProductType($prodtype)
                ->setShipmentCreatedAt($shipmentCreatedAt)
                ->setPaymentId($this->getPwPaymentId($order->getId()))
                ->setPaymentMethod($paymentMethod);

            $params = $this->prepareDeliveryParams($order, $shippingData, $pwShipmentData, "order_shipped");
            return $this->sendDeliveryConfirmation($paymentMethod, $params);
        }
    }
}
