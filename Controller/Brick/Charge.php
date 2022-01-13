<?php
namespace Paymentwall\Paymentwall\Controller\Brick;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Checkout\Model\Cart;
use Magento\Framework\Controller\ResultFactory;
use Paymentwall\Paymentwall\Model\Brick;
use \Paymentwall\Paymentwall\Model\Paymentwall;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Charge extends Action implements CsrfAwareActionInterface
{
    protected $cart;
    protected $brickModel;
    protected $urlBuilder;

    public function __construct(
        Context $context,
        Cart $cart,
        Brick $brickModel,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        parent::__construct($context);
        $this->cart = $cart;
        $this->brickModel = $brickModel;
        $this->urlBuilder = $urlBuilder;
    }

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $this->getResponse()->setBody(json_encode($this->brickModel->charge($params)));
    }
}
