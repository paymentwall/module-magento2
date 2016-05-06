<?php
namespace Paymentwall\Paymentwall\Model;

class Helper extends \Magento\Framework\App\Helper\AbstractHelper
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
        if (!class_exists('Paymentwall_Config')) {
            $config = \Magento\Framework\App\Filesystem\DirectoryList::getDefaultConfig();
            require_once(BP . '/' . $config['lib_internal']['path'] . "/paymentwall-php/lib/paymentwall.php");
        }
        \Paymentwall_Config::getInstance()->set([
            'api_type' => \Paymentwall_Config::API_GOODS,
            'public_key' => $this->config->getValue('payment/paymentwall/api_key'),
            'private_key' => $this->config->getValue('payment/paymentwall/secret_key')
        ]);
    }

    public function getConfig($name)
    {
        return $this->config->getValue("payment/paymentwall/{$name}");
    }
}

?>