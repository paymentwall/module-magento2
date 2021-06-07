<?php

namespace Paymentwall\Paymentwall\Controller\Brick;

class Form extends \Magento\Checkout\Controller\Onepage
{
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getLayout()->getUpdate()->removeHandle('default');

        return $resultPage;
    }
}
