<?php
namespace Paymentwall\Paymentwall\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $config;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    ) {
        $this->config = $config;
    }

    public function getInitConfig()
    {
        \Paymentwall_Config::getInstance()->set([
            'api_type' => \Paymentwall_Config::API_GOODS,
            'public_key' => $this->config->getValue('payment/paymentwall/api_key'),
            'private_key' => $this->config->getValue('payment/paymentwall/secret_key')
        ]);
    }

    public function getInitBrickConfig($isPingback = false)
    {
        $testMode = $this->config->getValue('payment/brick/test_mode');
        $privateTestKey = $this->config->getValue('payment/brick/private_test_key');
        $secretKey = $this->config->getValue('payment/brick/secret_key');
        if ($isPingback) {
            \Paymentwall_Config::getInstance()->set([
                'private_key' => $testMode ? $privateTestKey : $secretKey
            ]);
        } else {
            $publicTestKey = $this->config->getValue('payment/brick/public_test_key');
            $publicKey = $this->config->getValue('payment/brick/public_key');
            $privateKey = $this->config->getValue('payment/brick/private_key');
            \Paymentwall_Config::getInstance()->set([
                'api_type' => \Paymentwall_Config::API_GOODS,
                'public_key' => $testMode ? $publicTestKey : $publicKey,
                'private_key' => $testMode ? $privateTestKey : $privateKey
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->config->getValue("payment/{$type}/{$name}");
    }
}
