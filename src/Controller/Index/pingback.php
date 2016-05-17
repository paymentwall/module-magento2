<?php
namespace Paymentwall\Paymentwall\Controller\Index;


class Pingback extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context
    )
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $pingbackModel = $this->_objectManager->get('Paymentwall\Paymentwall\Model\Pingback');
        echo $pingbackModel->pingback($_GET);
        die;
    }
}