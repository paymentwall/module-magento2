<?php
namespace Paymentwall\Paymentwall\Helper;

use Magento\Store\Model\ScopeInterface;

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
            'public_key' => $this->config->getValue('payment/paymentwall/api_key', ScopeInterface::SCOPE_STORE),
            'private_key' => $this->config->getValue('payment/paymentwall/secret_key', ScopeInterface::SCOPE_STORE)
        ]);
    }

    public function getInitBrickConfig($isPingback = false)
    {
        $testMode = $this->config->getValue('payment/brick/test_mode', ScopeInterface::SCOPE_STORE);
        $privateTestKey = $this->config->getValue('payment/brick/private_test_key', ScopeInterface::SCOPE_STORE);
        $secretKey = $this->config->getValue('payment/brick/secret_key', ScopeInterface::SCOPE_STORE);
        if ($isPingback) {
            \Paymentwall_Config::getInstance()->set([
                'private_key' => $testMode ? $privateTestKey : $secretKey
            ]);
        } else {
            $publicTestKey = $this->config->getValue('payment/brick/public_test_key', ScopeInterface::SCOPE_STORE);
            $publicKey = $this->config->getValue('payment/brick/public_key', ScopeInterface::SCOPE_STORE);
            $privateKey = $this->config->getValue('payment/brick/private_key', ScopeInterface::SCOPE_STORE);
            \Paymentwall_Config::getInstance()->set([
                'api_type' => \Paymentwall_Config::API_GOODS,
                'public_key' => $testMode ? $publicTestKey : $publicKey,
                'private_key' => $testMode ? $privateTestKey : $privateKey
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->config->getValue("payment/{$type}/{$name}", ScopeInterface::SCOPE_STORE);
    }
}
