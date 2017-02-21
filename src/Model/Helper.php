<?php
namespace Paymentwall\Paymentwall\Model;

class Helper extends \Magento\Framework\App\Helper\AbstractHelper
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
        if (!class_exists('Paymentwall_Config')) {
            $config = \Magento\Framework\App\Filesystem\DirectoryList::getDefaultConfig();
            require_once(BP . '/' . $config['lib_internal']['path'] . "/paymentwall-php/lib/paymentwall.php");
        }
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
                'private_key' => $this->_config->getValue('payment/paymentwall_brick/secret_key')
            ]);
        } else {
            \Paymentwall_Config::getInstance()->set([
                'api_type' => \Paymentwall_Config::API_GOODS,
                'public_key' => $this->_config->getValue('payment/paymentwall_brick/public_key'),
                'private_key' => $this->_config->getValue('payment/paymentwall_brick/private_key')
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->_config->getValue("payment/{$type}/{$name}");
    }
}
