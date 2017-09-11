<?php
namespace Paymentwall\Paymentwall\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $_config;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    )
    {
        $this->_config = $config;
    }

    public function getInitConfig()
    {
        \Paymentwall_Config::getInstance()->set([
            'api_type' => \Paymentwall_Config::API_GOODS,
            'public_key' => $this->_config->getValue('payment/paymentwall/api_key'),
            'private_key' => $this->_config->getValue('payment/paymentwall/secret_key')
        ]);
    }

    public function getInitBrickConfig($isPingback = false)
    {
        if ($isPingback) {
            \Paymentwall_Config::getInstance()->set([
                'private_key' => $this->_config->getValue('payment/brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/private_test_key') : $this->_config->getValue('payment/paymentwall_brick/private_key')
            ]);
        } else {
            \Paymentwall_Config::getInstance()->set([
                'api_type' => \Paymentwall_Config::API_GOODS,
                'public_key' => $this->_config->getValue('payment/brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/public_test_key') : $this->_config->getValue('payment/paymentwall_brick/public_key'),
                'private_key' => $this->_config->getValue('payment/brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/private_test_key') : $this->_config->getValue('payment/paymentwall_brick/private_key')
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->_config->getValue("payment/{$type}/{$name}");
    }

}
