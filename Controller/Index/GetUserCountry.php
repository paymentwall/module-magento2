<?php
namespace Paymentwall\Paymentwall\Controller\Index;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Paymentwall\Paymentwall\Model\Paymentwall;

class GetUserCountry extends Action
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
        $this->getResponse()->setBody($this->paymentModel->getCountryByRemoteAddress());
    }
}
