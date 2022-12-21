<?php
namespace Paymentwall\Paymentwall\Controller\Index;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Paymentwall\Paymentwall\Model\Paymentwall;

class GetPaymentwallWidget extends Action
{
    protected $paymentModel;
    protected $remoteAddress;

    public function __construct(
        Context $context,
        Paymentwall $paymentModel
    ) {
        parent::__construct($context);
        $this->paymentModel = $paymentModel;
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setBody(json_encode([]));
            return;
        }

        $data = $this->getRequest()->getPost('data');
        $paymentwallWidget = $this->paymentModel->getPaymentWidget($data);
        $this->getResponse()->setBody(json_encode($paymentwallWidget['widget_url']));
    }
}
