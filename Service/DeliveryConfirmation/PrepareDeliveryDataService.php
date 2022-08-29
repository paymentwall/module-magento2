<?php

namespace Paymentwall\Paymentwall\Service\DeliveryConfirmation;

use Paymentwall\Paymentwall\Data\ShipmentData;

class PrepareDeliveryDataService
{
    const TYPE_PHYSICAL = 'physical';
    const NA_VALUE = 'N/A';

    protected $_helper;

    public function getDeliveryParams($order, array $shippingData, ShipmentData $shipmentData, string $status)
    {
        $params = [
            'payment_id' => $shipmentData->getPaymentId(),
            'merchant_reference_id' => $order->getIncrementId(),
            'type' => $shipmentData->getProductType(),
            'status' => $status,
            'estimated_delivery_datetime' => $shipmentData->getShipmentCreatedAt(),
            'estimated_update_datetime' => $shipmentData->getShipmentCreatedAt(),
            'refundable' => true,
            'shipping_address[country] ' => !empty($shippingData['country_id']) ? $shippingData['country_id'] : self::NA_VALUE,
            'shipping_address[city] ' => !empty($shippingData['city']) ? $shippingData['city'] : self::NA_VALUE,
            'shipping_address[zip] ' => !empty($shippingData['postcode']) ? $shippingData['postcode'] : self::NA_VALUE,
            'shipping_address[state] ' => !empty($shippingData['region']) ? $shippingData['region'] : self::NA_VALUE,
            'shipping_address[street] ' => !empty($shippingData['street']) ? $shippingData['street'] : self::NA_VALUE,
            'shipping_address[phone] ' => !empty($shippingData['telephone']) ? $shippingData['telephone'] : self::NA_VALUE,
            'shipping_address[firstname] ' => !empty($shippingData['firstname']) ? $shippingData['firstname'] : self::NA_VALUE,
            'shipping_address[lastname] ' => !empty($shippingData['lastname']) ? $shippingData['lastname'] : self::NA_VALUE,
            'shipping_address[email]' => !empty($shippingData['email']) ? $shippingData['email'] : self::NA_VALUE,
            'is_test' => $this->_helper->getConfig('test_mode', $shipmentData->getPaymentMethod())
        ];

        if ($shipmentData->getProductType() == self::TYPE_PHYSICAL) {
            $params['carrier_tracking_id'] = !empty($shipmentData->getTrackingCode()) ? $shipmentData->getTrackingCode() : self::NA_VALUE;
            $params['carrier_type'] = !empty($shipmentData->getCarrierType()) ? $shipmentData->getCarrierType() : self::NA_VALUE;
        }

        return $params;
    }
}