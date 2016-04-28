<?php
namespace Magento\Paymentwall\Block;

use Magento\Paymentwall\Model\Helper;

class Paymentwall extends \Magento\Framework\View\Element\Template
{
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getWidget()
    {
        return $this->getData('widget');
    }
}
 