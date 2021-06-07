<?php
namespace Paymentwall\Paymentwall\Gateway\Http\Client;

use Magento\Framework\App\ObjectManager;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class ClientMock implements ClientInterface
{

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $body = $transferObject->getBody();

        $response = [
            'success' => 1,
            'payment_details' => [
                'card' => [
                    'type' => $body['card_info']['card_type'],
                    'last4' => $body['card_info']['card_last4'],
                ],
                'id' => $body['transaction_id'],
                'charge_is_captured' => $body['charge_state']['is_captured'],
                'charge_is_under_review' => $body['charge_state']['is_under_review']
            ]
        ];

        if (!empty($body['risk'])) {
            $response['payment_details']['risk'] = $body['risk'];
        }

        return $response;

    }
}
