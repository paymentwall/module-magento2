<?php

namespace Paymentwall\Paymentwall\Service\DeliveryConfirmation;

class DeliveryDataService implements DeliveryDataServiceInterface
{

    const TYPE_PHYSICAL = 'physical';
    const TYPE_DIGITAL = 'digital';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_ORDER_SHIPPED = 'order_shipped';

    const NA_VALUE = 'N/A';

    protected $_pwHelper;
    protected $_helperConfig;

    public function __construct(\Paymentwall\Paymentwall\Helper\Helper $pwHelper,
        \Paymentwall\Paymentwall\Helper\Config $helperConfig
    )
    {
        $this->_pwHelper = $pwHelper;
        $this->_helperConfig = $helperConfig;
    }

    /**
     * @param $order
     * @return string
     */
    private function getType($order) : string
    {
        if ($order->hasShipments()) {
            return self::TYPE_PHYSICAL;
        }

        return self::TYPE_DIGITAL;
    }

    public function prepareDeliveryConfirmationParams($order, $shipmentAddressInfo, $status, $tracking) : array
    {
        $commonParams = [
            'status' => $status,
            'estimated_delivery_datetime' => time(),
            'estimated_update_datetime' => time(),
            'refundable' => true,
        ];

        $deliveryConfirmationParams = array_merge($commonParams, $this->preparePaymentInfo($order), $this->prepareShippingInfo($shipmentAddressInfo));

        if (!empty($tracking)) {
            return array_merge($deliveryConfirmationParams, $this->prepareCarrierTrackingInfo($tracking));
        }

        return $deliveryConfirmationParams;
    }

    /**
     * @param $order
     * @return array
     */
    private function preparePaymentInfo($order) : array
    {
        $orderId = $order->getId();

        return [
            'payment_id' => $this->_pwHelper->getPwPaymentId($orderId),
            'merchant_reference_id' => $order->getIncrementId(),
            'is_test' => $this->_helperConfig->getConfig('test_mode', $order->getPayment()->getMethod()),
            'type' => $this->getType($order)
        ];
    }

    /**
     * @param array $shippingAddressInfo
     * @return array
     */
    private function prepareShippingInfo(array $shippingAddressInfo) : array
    {
        return [
            'shipping_address[country] ' => !empty($shippingAddressInfo['country_id']) ? $shippingAddressInfo['country_id'] : self::NA_VALUE,
            'shipping_address[city] ' => !empty($shippingAddressInfo['city']) ? $shippingAddressInfo['city'] : self::NA_VALUE,
            'shipping_address[zip] ' => !empty($shippingAddressInfo['postcode']) ? $shippingAddressInfo['postcode'] : self::NA_VALUE,
            'shipping_address[state] ' => !empty($shippingAddressInfo['region']) ? $shippingAddressInfo['region'] : self::NA_VALUE,
            'shipping_address[street] ' => !empty($shippingAddressInfo['street']) ? $shippingAddressInfo['street'] : self::NA_VALUE,
            'shipping_address[phone] ' => !empty($shippingAddressInfo['telephone']) ? $shippingAddressInfo['telephone'] : self::NA_VALUE,
            'shipping_address[firstname] ' => !empty($shippingAddressInfo['firstname']) ? $shippingAddressInfo['firstname'] : self::NA_VALUE,
            'shipping_address[lastname] ' => !empty($shippingAddressInfo['lastname']) ? $shippingAddressInfo['lastname'] : self::NA_VALUE,
            'shipping_address[email]' => !empty($shippingAddressInfo['email']) ? $shippingAddressInfo['email'] : self::NA_VALUE,
        ];
    }

    /**
     * @param $tracking
     * @return array
     */
    private function prepareCarrierTrackingInfo($tracking) : array
    {
        return [
            'carrier_tracking_id' => $tracking->getTrackNumber(),
            'carrier_type' => $tracking->getCarrierCode()
        ];
    }
}
