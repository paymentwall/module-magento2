<?php
namespace Paymentwall\Paymentwall\Controller\Index;


class Pingback extends \Magento\Framework\App\Action\Action
{

    protected $_modelPingback;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Paymentwall\Paymentwall\Model\Pingback $modelPingback
    )
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->_modelPingback = $modelPingback;
    }

    public function execute()
    {
        $this->getResponse()->setBody($this->_modelPingback->pingback($this->request->getParams()));
    }
}