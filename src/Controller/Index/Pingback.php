<?php
namespace Paymentwall\Paymentwall\Controller\Index;


class Pingback extends \Magento\Framework\App\Action\Action
{

    protected $_modelPingback;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Paymentwall\Paymentwall\Model\Pingback $modelPingback
    )
    {
        parent::__construct($context);
        $this->_modelPingback = $modelPingback;
    }

    public function execute()
    {
        echo $this->_modelPingback->pingback($_GET);
        exit();
    }
}