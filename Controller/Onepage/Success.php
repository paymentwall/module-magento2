<?php
namespace Paymentwall\Paymentwall\Controller\Onepage;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use \Magento\Framework\App\Action\Action;

class Success extends \Magento\Checkout\Controller\Onepage
{
    public function execute()
    {
        $quoteId = $this->getRequest()->getParam('quote-id');
        if ($quoteId) {
            $quote = $this->quoteRepository->get($quoteId);
            $quote->setIsActive(0);
            $quote->setTotalsCollectedFlag(false);
            $this->_objectManager->get(\Magento\Checkout\Model\Session::class)->replaceQuote($quote);
            $this->quoteRepository->save($quote);
        } else {
            if (!$this->_objectManager->get('Magento\Checkout\Model\Session\SuccessValidator')->isValid()) {
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        }

        $session = $this->getOnepage()->getCheckout();
        $session->clearQuote();

        $resultPage = $this->resultPageFactory->create();

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$session->getLastOrderId()],
                'order' => $session->getLastRealOrder()
            ]
        );

        return $resultPage;
    }
}
