<?php

namespace Paymentwall\Paymentwall\Controller\Index;

/**
 * Class Pingback
 *
 * @package Paymentwall\Paymentwall\Controller\Index
 */
class Pingback extends \Magento\Framework\App\Action\Action
{
    /**
     * Action exexution.
     */
    public function execute()
    {
        $pingbackModel = $this->_objectManager->get('Paymentwall\Paymentwall\Model\Pingback');

        echo $pingbackModel->pingback($_GET);
        die;
    }
}
