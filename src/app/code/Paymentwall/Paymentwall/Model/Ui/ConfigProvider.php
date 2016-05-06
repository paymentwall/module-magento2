<?php

namespace Paymentwall\Paymentwall\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paymentwall';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlInterface,
        array $data = []
    )
    {
        $this->_storeManager = $storeManager;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $storeUrl = $this->_storeManager->getStore()->getBaseUrl();
        return [
            'storeUrl' => [
                'url' => $storeUrl
            ]
        ];
    }
}
