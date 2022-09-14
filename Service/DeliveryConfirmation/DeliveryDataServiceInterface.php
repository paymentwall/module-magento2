<?php

namespace Paymentwall\Paymentwall\Service\DeliveryConfirmation;

interface DeliveryDataServiceInterface
{
    public function prepareDeliveryConfirmationParams($order, $shipmentAddressInfo, $status, $tracking) : array;
}