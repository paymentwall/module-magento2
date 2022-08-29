<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Paymentwall\Paymentwall\Service\DeliveryConfirmation\DeliveryDataService;
use Paymentwall\Paymentwall\Service\DeliveryConfirmation\DeliveryConfirmationClientService;

class PWObserver implements ObserverInterface
{
    const BRICK             = 'brick';
    const PWLOCAL_METHOD    = 'paymentwall';

    protected $deliveryConfirmationClientService;
    protected $_helper;
    protected $_pwHelper;
    protected $deliveryDataService;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Paymentwall\Paymentwall\Helper\Config    $helperConfig,
        DeliveryConfirmationClientService         $deliveryConfirmationClientService,
        \Paymentwall\Paymentwall\Helper\Helper    $pwHelper,
        DeliveryDataService                       $deliveryDataService
    ) {
        $this->_objectManager = $objectManager;
        $this->_helper = $helperConfig;
        $this->deliveryConfirmationClientService = $deliveryConfirmationClientService;
        $this->_pwHelper = $pwHelper;
        $this->deliveryDataService = $deliveryDataService;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //Observer execution code...
        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();

        if (($paymentMethod != self::PWLOCAL_METHOD && $paymentMethod != self::BRICK) || $order->getState() != Order::STATE_COMPLETE) {
            return;
        }

        if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
            return;
        }

        $tracking = null;
        if ($order->hasShipments()) {
            $shipment = $this->getShipmentFromOrder($order);

            $tracking = $this->getTrackFromShipment($shipment);

            $shippingAddressInfo = $shipment->getShippingAddress()->getData();
        } else {
            $shippingAddressInfo = $order->getBillingAddress()->getData();
        }

        $params = $this->deliveryDataService->prepareDeliveryConfirmationParams($order, $shippingAddressInfo, DeliveryDataService::STATUS_DELIVERED, $tracking);

        return $this->deliveryConfirmationClientService->send($paymentMethod, $params);
    }

    /**
     * @param $order
     * @return mixed|null
     */
    private function getShipmentFromOrder($order)
    {
        $shipmentsCollection = $order->getShipmentsCollection();
        $shipments = $shipmentsCollection->getItems();

        return array_pop($shipments);
    }

    private function getTrackFromShipment($shipment)
    {
        $track = null;

        $tracksCollection = $shipment->getTracksCollection();
        if (empty($tracksCollection)) {
            return $track;
        }

        $trackings = $tracksCollection->getItems();
        if (empty($trackings) || !is_array($trackings)) {
            return $track;
        }

        return array_pop($trackings);
    }
}
