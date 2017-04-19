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
        if (!class_exists('Paymentwall_Config')) {
            $config = \Magento\Framework\App\Filesystem\DirectoryList::getDefaultConfig();
            require_once(BP . '/' . $config['lib_internal']['path'] . "/paymentwall-php/lib/paymentwall.php");
        }
        if ($isPingback) {
            \Paymentwall_Config::getInstance()->set([
                'private_key' => $this->_config->getValue('payment/paymentwall_brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/private_test_key') : $this->_config->getValue('payment/paymentwall_brick/private_key')
            ]);
        } else {
            \Paymentwall_Config::getInstance()->set([
                'api_type' => \Paymentwall_Config::API_GOODS,
                'public_key' => $this->_config->getValue('payment/paymentwall_brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/public_test_key') : $this->_config->getValue('payment/paymentwall_brick/public_key'),
                'private_key' => $this->_config->getValue('payment/paymentwall_brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/private_test_key') : $this->_config->getValue('payment/paymentwall_brick/private_key')
            ]);
        }
    }

    public function getConfig($name, $type = 'paymentwall')
    {
        return $this->_config->getValue("payment/{$type}/{$name}");
    }

    public function getUserRealIP() {
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else
            $ipaddress = getenv('REMOTE_ADDR');

        return $ipaddress;
    }
}
