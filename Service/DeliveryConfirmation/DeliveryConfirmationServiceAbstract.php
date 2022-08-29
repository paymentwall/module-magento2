<?php

namespace Paymentwall\Paymentwall\Service\DeliveryConfirmation;

use Paymentwall\Paymentwall\Data\ShipmentData;

abstract class DeliveryConfirmationServiceAbstract
{
    protected $transactionSearchResultInF;
    protected $_helper;

    const PWLOCAL_METHOD    = 'paymentwall';
    const BRICK             = 'brick';
    const TYPE_PHYSICAL = 'physical';
    const TYPE_DIGITAL = 'digital';
    const STATUS_ORDER_SHIPPED = 'order_shipped';
    const STATUS_DELIVERED = 'delivered';

    protected function prepareDeliveryParams($order, array $shippingData, ShipmentData $shipmentData, string $status)
    {
        $prepareDeliveryDataService = new PrepareDeliveryDataService();

        return $prepareDeliveryDataService->getDeliveryParams($order, $shippingData, $shipmentData, $status);
    }

    protected function sendDeliveryConfirmation(string $paymentMethod, array $params)
    {
        $deliveryConfirmationService = new SendDeliveryConfirmationService();

        return $deliveryConfirmationService->send($paymentMethod, $params);
    }

    /**
     * @param $orderId
     * @return string
     */
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
