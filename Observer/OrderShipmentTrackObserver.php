<?php

namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Paymentwall\Paymentwall\Service\DeliveryConfirmation\DeliveryDataService;
use Paymentwall\Paymentwall\Service\DeliveryConfirmation\DeliveryConfirmationClientService;

class OrderShipmentTrackObserver implements ObserverInterface
{
    const BRICK             = 'brick';
    const PWLOCAL_METHOD    = 'paymentwall';

    protected $logger;
    protected $deliveryConfirmationClientService;
    protected $_helper;
    protected $_pwHelper;

    protected $deliveryDataService;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        \Paymentwall\Paymentwall\Helper\Helper $pwHelper,
        DeliveryConfirmationClientService $deliveryConfirmationClientService,
        DeliveryDataService $deliveryDataService
    ) {
        $this->logger = $logger;
        $this->_helper = $helperConfig;
        $this->_pwHelper = $pwHelper;
        $this->deliveryConfirmationClientService = $deliveryConfirmationClientService;
        $this->deliveryDataService = $deliveryDataService;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $tracking = $observer->getEvent()->getTrack();
        $shipment = $tracking->getShipment();
        $order = $shipment->getOrder();

        $paymentMethod = $order->getPayment()->getMethod();

        if (($paymentMethod != self::PWLOCAL_METHOD && $paymentMethod != self::BRICK) || $order->getState() != Order::STATE_COMPLETE) {
            return;
        }

        if (!$this->_helper->getConfig('delivery_confirmation_api', $paymentMethod)) {
            return;
        }

        $shippingAddressInfo = $shipment->getShippingAddress()->getData();

        $params = $this->deliveryDataService->prepareDeliveryConfirmationParams($order, $shippingAddressInfo, DeliveryDataService::STATUS_ORDER_SHIPPED, $tracking);

        return $this->deliveryConfirmationClientService->send($paymentMethod, $params);
    }
}
