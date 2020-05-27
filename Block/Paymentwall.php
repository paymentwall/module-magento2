<?php
namespace Paymentwall\Paymentwall\Block;

use \Magento\Framework\View\Element\Template\Context;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Customer\Model\Session as CustomerSession;
use \Paymentwall\Paymentwall\Model\Paymentwall as PaymentModel;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\View\Element\Template;

class Paymentwall extends Template
{

    protected $checkoutSession;
    protected $customerSession;
    protected $request;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        PaymentModel $paymentModel,
        RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->paymentModel = $paymentModel;
        $this->request = $request;
    }

    /**
     * Render additional order information lines and return result html
     *
     * @return string
     */
    public function getAdditionalInfoHtml()
    {
        return $this->_layout->renderElement('order.success.additional.info');
    }

    /**
     * Initialize data and prepare it for output
     *
     * @return string
     */
    protected function _beforeToHtml()
    {
        $this->prepareBlockData();
        return parent::_beforeToHtml();
    }

    /**
     * Prepares block data
     *
     * @return void
     */
    protected function prepareBlockData()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $customer = $this->customerSession->getCustomer();
        $widget = $this->paymentModel->generateWidget($order, $customer, $this->request->getParam('local_method'));
            
        if ($this->request->getParam('new_window')) {
            $widget = '<script>window.location.href = "' . $widget->getUrl() . '"</script>';
        } else {
            $widget = $widget->getHtmlCode(['width' => '100%', 'height' => '650px']);
        }
        $this->addData(
            ['widget' => $widget]
        );

        return true;
    }
}
