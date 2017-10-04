<?php
namespace Paymentwall\Paymentwall\Controller\Index;

class Pingback extends \Magento\Framework\App\Action\Action
{

    protected $modelPingback;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Paymentwall\Paymentwall\Model\Pingback $modelPingback
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->modelPingback = $modelPingback;
    }

    public function execute()
    {
        $this->getResponse()->setBody($this->modelPingback->pingback($this->request->getParams()));
    }
}
