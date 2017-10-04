<?php
namespace Paymentwall\Paymentwall\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $config;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    )
    {
        $this->config = $config;
    }

    public function getInitConfig()
    {
        \Paymentwallconfig::getInstance()->set([
            'api_type' => \Paymentwallconfig::API_GOODS,
            'public_key' => $this->config->getValue('payment/paymentwall/api_key'),
            'private_key' => $this->config->getValue('payment/paymentwall/secret_key')
        ]);
    }

    public function getInitBrickConfig($isPingback = false)
    {
        if ($isPingback) {
            \Paymentwallconfig::getInstance()->set([
                'private_key' => $this->config->getValue('payment/brick/test_mode') ? $this->config->getValue('payment/brick/private_test_key') : $this->config->getValue('payment/brick/secret_key')
            ]);
        } else {
            \Paymentwallconfig::getInstance()->set([
                'api_type' => \Paymentwallconfig::API_GOODS,
                'public_key' => $this->config->getValue('payment/brick/test_mode') ? $this->config->getValue('payment/brick/public_test_key') : $this->config->getValue('payment/brick/public_key'),
                'private_key' => $this->config->getValue('payment/brick/test_mode') ? $this->config->getValue('payment/brick/private_test_key') : $this->config->getValue('payment/brick/private_key')
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->config->getValue("payment/{$type}/{$name}");
    }

}
