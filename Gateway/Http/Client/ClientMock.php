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
        $testMode = $this->config->getValue('test_mode');
        $publicTestKey = $this->config->getValue('public_test_key');
        $publicKey = $this->config->getValue('public_key');
        $privateTestKey = $this->config->getValue('private_test_key');
        $privateKey = $this->config->getValue('private_key');
        \Paymentwall_Config::getInstance()->set([
            'api_type' => \Paymentwall_Config::API_GOODS,
            'public_key' => $testMode ? $publicTestKey : $publicKey,
            'private_key' => $testMode ? $privateTestKey : $privateKey
        ]);

        $chargeData = array_merge(
            $body['cardInfo'],
            $body['userProfile'],
            $body['extraData'],
            ['secure' => $body['isSecureEnabled'] && 1==0]
        );
        $this->adapter->create($chargeData);
        $responseData = json_decode($this->adapter->getRawResponseData(), true);
        $response = [
            'response' => json_decode($this->adapter->getPublicData(), true),
            'responseData' => json_decode($this->adapter->getRawResponseData(), true),
            'isSuccessful' => $this->adapter->isSuccessful(),
            'isCaptured' => $this->adapter->isCaptured()
        ];

        if (!empty($responseData['secure'])) {
            $brickSessionData = ['orderIncrementId' => $body['orderIncrementId']];
            $this->checkoutSession->setBrickSessionData($brickSessionData);
        }

        return $response;
    }
}
