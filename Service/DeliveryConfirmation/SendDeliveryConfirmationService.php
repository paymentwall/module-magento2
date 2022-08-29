<?php

namespace Paymentwall\Paymentwall\Service\DeliveryConfirmation;

class SendDeliveryConfirmationService
{
    const PWLOCAL_METHOD = 'paymentwall';

    protected $_helper;

    public function send(string $paymentMethod, array $params)
    {
        $this->initGateway($paymentMethod);

        $delivery = new \Paymentwall_GenerericApiObject('delivery');

        return $delivery->post($params);
    }

    protected function initGateway($paymentMethod)
    {
        if ($paymentMethod == self::PWLOCAL_METHOD) {
            $this->_helper->getInitConfig();
        } else {
            $this->_helper->getInitBrickConfig();
        }
    }
}