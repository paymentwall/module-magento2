<?php
namespace Paymentwall\Paymentwall\Controller\Onepage;

class Success extends \Magento\Checkout\Controller\Onepage
{
    public function execute()
    {
        $session = $this->getOnepage()->getCheckout();
        $session->clearQuote();

        $result = $this->resultRawFactory->create();
        $url = $this->urlBuilder->getUrl('checkout/onepage/success');
        $result->setContents('<script type="text/javascript">window.parent.location = \'' . $url . '\';</script>');

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order_ids' => [$session->getLastOrderId()]]
        );

        return $result;
    }
}
