<?php
namespace Paymentwall\Paymentwall\Model\Ui;

class BrickConfigProvider
{
    protected $_ccConfig;
    protected $_config;

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

    public function getConfig()
    {
        $config = [];
        $methodCode = 'paymentwall_brick';

        $config = array_merge_recursive($config, [
            'payment' => [
                'ccform' => [
                    'availableTypes' => [$methodCode => $this->getCcAvailableTypes()],
                    'months' => [$methodCode => $this->getCcMonths()],
                    'years' => [$methodCode => $this->getCcYears()],
                    'hasVerification' => [$methodCode => true],
                    'hasSsCardType' => [$methodCode => false],
                    'ssStartYears' => [$methodCode => $this->getSsStartYears()],
                    'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()],
                    'public_key' => $this->_config->getValue('payment/paymentwall_brick/test_mode') ? $this->_config->getValue('payment/paymentwall_brick/public_test_key') : $this->_config->getValue('payment/paymentwall_brick/public_key')
                ],
                $methodCode => [
                    'storeUrl' => $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore(1)->getBaseUrl()
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