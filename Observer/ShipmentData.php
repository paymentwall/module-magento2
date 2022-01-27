<?php

namespace Paymentwall\Paymentwall\Observer;

class ShipmentData
{
    private $trackingCode;
    private $carrierType;
    private $shipmentCreatedAt;
    private $productType;
    private $paymentId;
    private $paymentMethod;

    public function setTrackingCode($trackingCode)
    {
        $this->trackingCode = $trackingCode;
        return $this;
    }

    public function getTrackingCode()
    {
        return $this->trackingCode;
    }

    public function setCarrierType($carrierType)
    {
        $this->carrierType = $carrierType;
        return $this;
    }

    public function getCarrierType()
    {
        return $this->carrierType;
    }

    public function setShipmentCreatedAt($shipmentCreatedAt)
    {
        $this->shipmentCreatedAt = $shipmentCreatedAt;
        return $this;
    }

    public function getShipmentCreatedAt()
    {
        return $this->shipmentCreatedAt;
    }

    public function setProductType($productType)
    {
        $this->productType = $productType;
        return $this;
    }

    public function getProductType()
    {
        return $this->productType;
    }

    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    public function getPaymentId()
    {
        return $this->paymentId;
    }


    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

}
