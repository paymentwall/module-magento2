<?php
namespace Paymentwall\Paymentwall\Controller\Onepage;

class Success extends \Magento\Checkout\Controller\Onepage
{
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
