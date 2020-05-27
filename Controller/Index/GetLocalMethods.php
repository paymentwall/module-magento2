<?php
namespace Paymentwall\Paymentwall\Controller\Index;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Checkout\Model\Cart;
use \Paymentwall\Paymentwall\Model\Paymentwall;

class GetLocalMethods extends Action
{

    protected $cart;
    protected $paymentModel;

    public function __construct(
        Context $context,
        Cart $cart,
        Paymentwall $paymentModel
    ) {
        parent::__construct($context);
        $this->cart = $cart;
        $this->paymentModel = $paymentModel;
    }

    public function execute()
    {
        $this->getResponse()->setBody($this->paymentModel->getLocalMethods(json_decode($this->getRequest()->getContent(), true)));
    }
}
