<?php

namespace Paymentwall\Paymentwall\Controller\Onepage;

class Success extends \Magento\Checkout\Controller\Onepage
{

    public function execute()
    {
        $defaultRedirect = $this->resultRedirectFactory->create()->setPath('checkout/cart');
        $quoteId = $this->getRequest()->getParam('quote-id');
        if (empty($quoteId)) {
            return $defaultRedirect;
        }

        $checkoutSession = $this->_objectManager->get('\Magento\Checkout\Model\Session');

        if ($checkoutSession->getPaymentwallCustomerCheckoutQuoteId() !== md5($quoteId)) {
            return $defaultRedirect;
        }

        $quote = $this->quoteRepository->get($quoteId);
        if (empty($quote->getId())) {
            return $defaultRedirect;
        }

        $quote->setIsActive(0);
        $quote->setTotalsCollectedFlag(false);
        $this->quoteRepository->save($quote);

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->clearQuote();

        $resultPage = $this->resultPageFactory->create();

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$checkoutSession->getLastOrderId()],
                'order' => $checkoutSession->getLastRealOrder()
            ]
        );

        return $resultPage;
    }
}
