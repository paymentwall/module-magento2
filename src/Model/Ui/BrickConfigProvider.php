<?php
namespace Paymentwall\Paymentwall\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class BrickConfigProvider
 */
final class BrickConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paymentwall_brick';

    public function __construct(
        \Magento\Payment\Model\CcConfig $ccConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\ObjectManagerInterface $objectManager
    )
    {
        $this->_ccConfig = $ccConfig;
        $this->_config = $config;
        $this->_objectManager = $objectManager;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {

        $methodCode = self::CODE;
        $config = [];

        $config = array_merge_recursive($config, [
            'payment' => [
                'ccform' => [
                    'availableTypes' => [$methodCode => $this->getCcAvailableTypes()],
                    'months' => [$methodCode => $this->getCcMonths()],
                    'years' => [$methodCode => $this->getCcYears()],
                    'hasVerification' => [$methodCode => true],
                    'hasSsCardType' => [$methodCode => false],
                    'ssStartYears' => [$methodCode => $this->getSsStartYears()],
                    'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()]
                ],
                $methodCode => [
                    'public_key' => $this->_config->getValue('payment/paymentwall_brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/public_test_key') : $this->_config->getValue('payment/paymentwall_brick/public_key'),
                    'isActive' => true
                ]
            ]
        ]);
        return $config;
    }

    protected function getCcMonths()
    {
        return $this->_ccConfig->getCcMonths();
    }

    protected function getCcYears()
    {
        return $this->_ccConfig->getCcYears();
    }

    protected function getSsStartYears()
    {
        return $this->_ccConfig->getSsStartYears();
    }

    protected function getCvvImageUrl()
    {
        return $this->_ccConfig->getCvvImageUrl();
    }

    protected function getCcAvailableTypes()
    {
        $types = $this->_ccConfig->getCcAvailableTypes();
        $availableTypes = $this->_config->getValue('payment/paymentwall_brick/cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach (array_keys($types) as $code) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }
        return $types;
    }
}
