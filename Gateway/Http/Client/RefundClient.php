<?php

namespace Paymentwall\Paymentwall\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use \Magento\Framework\App\RequestInterface;

class RefundClient implements ClientInterface
{
    protected $helperConfig;

    protected $request;

    public function __construct(
        \Paymentwall\Paymentwall\Helper\Config $helperConfig,
        RequestInterface $request
    )
    {
        $this->helperConfig = $helperConfig;
        $this->request = $request;
    }
    public function placeRequest(TransferInterface $transferObject)
    {
        $data = $transferObject->getBody();

        $response = [];

        if ($this->isCalledFromPingback()) {
            $response['success'] = 1;
            return $response;
        }

        try {
            $this->helperConfig->getInitBrickConfig(true);

            $chargeid = $data['charge_id'];
            $charge = new \Paymentwall_Charge($chargeid);

            $charge->refund();
            $response = [
                'charge_id' => $data['charge_id']
            ];
            $response = array_merge($response, json_decode($charge->getPublicData(), true));

        } catch (\Exception $e) {
            $message = "Something went wrong during Gateway request.";
            $response['error']['message'] = $message;
            $response['success'] = 0;
        }

        return $response;
    }

    protected function isCalledFromPingback()
    {
        return $this->request->getRouteName() == 'paymentwall'
            && $this->request->getModuleName() == 'paymentwall'
            && $this->request->getActionName() == 'pingback';
    }
}
