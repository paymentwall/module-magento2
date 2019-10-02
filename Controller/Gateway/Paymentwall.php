<?php
namespace Paymentwall\Paymentwall\Controller\Gateway;

use Magento\Checkout\Model\Session\SuccessValidator;

class Paymentwall extends \Magento\Checkout\Controller\Onepage
{

    public function execute()
    {
        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get(SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        if ($this->getRequest()->isAjax()) {
            $order    = $session->getLastRealOrder();
            $customer = $this->_customerSession->getCustomer();
            $widget   = $this->model->generateWidget($order, $customer);

            return $this->resultJsonFactory->create()->setData(['url' => $widget->getUrl()]);
        }

        return $this->resultPageFactory->create();
    }
}
