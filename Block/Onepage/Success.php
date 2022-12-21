<?php

namespace Paymentwall\Paymentwall\Block\Onepage;

use Magento\Customer\Model\Context;
use \Magento\Sales\Model\Order;

class Success extends \Magento\Framework\View\Element\Template
{

    /**
     * @return string
     * @since 100.2.0
     */
    public function getContinueUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
