<?php
namespace Paymentwall\Paymentwall\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Paymentwall\Paymentwall\Gateway\Response\FraudHandler;

class Info extends ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return __($field);
    }

}
