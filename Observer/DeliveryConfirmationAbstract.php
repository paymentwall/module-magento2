<?php

namespace Paymentwall\Paymentwall\Observer;

abstract class DeliveryConfirmationAbstract
{
    protected $transactionSearchResultInF;
    protected $_helper;

    const PWLOCAL_METHOD    = 'paymentwall';
    const BRICK             = 'brick';
    const PHYSICAL_PRODUCT = 'physical';
    const DIGITAL_PRODUCT = 'digital';

    protected function prepareDeliveryParams($order, array $shippingData, ShipmentData $shipmentData, string $status)
    {
        $params = [
            'payment_id' => $shipmentData->getPaymentId(),
            'merchant_reference_id' => $order->getIncrementId(),
            'type' => $shipmentData->getProductType(),
            'status' => $status,
            'estimated_delivery_datetime' => $shipmentData->getShipmentCreatedAt(),
            'estimated_update_datetime' => $shipmentData->getShipmentCreatedAt(),
            'refundable' => true,
            'shipping_address[country] ' => $shippingData['country_id'],
            'shipping_address[city] ' => $shippingData['city'],
            'shipping_address[zip] ' => $shippingData['postcode'],
            'shipping_address[state] ' => !empty($shippingData['region']) ? $shippingData['region'] : 'N/A',
            'shipping_address[street] ' => $shippingData['street'],
            'shipping_address[phone] ' => $shippingData['telephone'],
            'shipping_address[firstname] ' => $shippingData['firstname'],
            'shipping_address[lastname] ' => $shippingData['lastname'],
            'shipping_address[email]' => $shippingData['email'],
            'is_test' => $this->_helper->getConfig('test_mode', $shipmentData->getPaymentMethod())
        ];

        if ($shipmentData->getProductType() == 'physical') {
            $params['carrier_tracking_id'] = $shipmentData->getTrackingCode();
            $params['carrier_type'] = $shipmentData->getCarrierType();
        }

        return $params;
    }

    protected function sendDeliveryConfirmation(string $paymentMethod, array $params)
    {
        if ($paymentMethod == self::PWLOCAL_METHOD) {
            $this->_helper->getInitConfig();
        } else {
            $this->_helper->getInitBrickConfig();
        }

        $delivery = new \Paymentwall_GenerericApiObject('delivery');
        $response = $delivery->post($params);
        return $response;
    }

    protected function getPwPaymentId($orderId)
    {
        $transactionsCollection = $this->transactionSearchResultInF->create()->addOrderIdFilter($orderId);
        $transactionItems = $transactionsCollection->getItems();
        $paymentId = '';
        foreach ($transactionItems as $trans) {
            $transData = $trans->getData();
            if ($transData['txn_id']) {
                $paymentId = $transData['txn_id'];
            }
        }

        return $paymentId;
    }
}
