<?php
namespace Paymentwall\Paymentwall\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paymentwall';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'defaultWidgetPageUrl' => 'paymentwall/gateway/paymentwall'
                ]
            ]
        ];
    }
}
