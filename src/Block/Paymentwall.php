<?php

namespace Paymentwall\Paymentwall\Block;

/**
 * Class Paymentwall
 *
 * @package Paymentwall\Paymentwall\Block
 */
class Paymentwall extends \Magento\Framework\View\Element\Template
{
    /**
     * @return mixed
     */
    public function getWidget()
    {
        return $this->getData('widget');
    }
}
