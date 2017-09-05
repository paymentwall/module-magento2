<?php
namespace Paymentwall\Paymentwall\Gateway\Http\Client;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Paymentwall_Charge;
use Paymentwall_Config;

class ClientMock implements ClientInterface
{
    const SUCCESS = 1;
    const FAILURE = 0;

    /**
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;

    private $adapter;

    private $config;

    private $order;

    private $checkoutSession;
    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger,
        Paymentwall_Charge $paymentwallCharge,
        ConfigInterface $config,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->logger = $logger;
        $this->adapter = $paymentwallCharge;
        $this->config = $config;
        $this->order = $order;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $body = $transferObject->getBody();

        \Paymentwall_Config::getInstance()->set([
            'api_type' => \Paymentwall_Config::API_GOODS,
            'public_key' => $this->config->getValue('test_mode') ? $this->config->getValue('public_test_key') : $this->_config->getValue('public_key'),
            'private_key' => $this->config->getValue('test_mode') ? $this->config->getValue('private_test_key') : $this->_config->getValue('private_key')
        ]);

        $chargeData = array_merge(
            $body['cardInfo'],
            $body['userProfile'],
            $body['extraData'],
            ['secure' => $body['isSecureEnabled'] && 1==1]
        );
        $this->adapter->create($chargeData);
        $responseData = json_decode($this->adapter->getRawResponseData(), true);
        $response = [
            'response' => json_decode($this->adapter->getPublicData(),true),
            'responseData' => json_decode($this->adapter->getRawResponseData(), true),
            'isSuccessful' => $this->adapter->isSuccessful(),
            'isCaptured' => $this->adapter->isCaptured()
        ];

        if (!empty($responseData['secure'])) {
            $brickSessionData = array('orderIncrementId' => $body['orderIncrementId']);
            $this->checkoutSession->setBrickSessionData($brickSessionData);
        }

        return $response;
    }



}
