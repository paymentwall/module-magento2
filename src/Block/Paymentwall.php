<?php
namespace Paymentwall\Paymentwall\Block;

use Paymentwall\Paymentwall\Model\Helper;

class Paymentwall extends \Magento\Framework\View\Element\Template
{
	public function _prepareLayout()
	{
		return parent::_prepareLayout();
	}

	public function getWidget(){
		return $this->getData('widget');
	}
}
 