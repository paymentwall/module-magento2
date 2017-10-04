<?php
namespace Paymentwall\Paymentwall\Block;

use Magento\Customer\Model\Context;
use Magento\Sales\Model\Order;

class Paymentwall extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /*
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Paymentwall\Paymentwall\Model\Paymentwall $paymentModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->paymentModel = $paymentModel;
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
        $order = $this->_checkoutSession->getLastRealOrder();
        $customer = $this->_customerSession->getCustomer();

        $this->addData(
            ['widget' => $this->paymentModel->generateWidget($order, $customer)->getHtmlCode(['width' => '100%', 'height' => '650px'])]
        );

        return true;
    }
}
