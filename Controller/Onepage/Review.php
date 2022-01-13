<?php

namespace Paymentwall\Paymentwall\Controller\Onepage;

class Review extends \Magento\Checkout\Controller\Onepage
{
    public function execute()
    {
        $session = $this->getOnepage()->getCheckout();
        if (!$this->_objectManager->get('Magento\Checkout\Model\Session\SuccessValidator')->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $session->clearQuote();

        $resultPage = $this->resultPageFactory->create();

        return $resultPage;
    }
}
