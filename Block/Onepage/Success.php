<?php

namespace Paymentwall\Paymentwall\Block\Onepage;

use Magento\Customer\Model\Context;
use \Magento\Sales\Model\Order;
use Paymentwall\Paymentwall\Model\Brick;
use Paymentwall\Paymentwall\Model\Pingback;

class Success extends \Magento\Framework\View\Element\Template
{

    protected $_quoteRepository;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param array $data
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        array $data = [],
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->_orderFactory = $orderFactory;
        parent::__construct($context, $data);
        $this->_quoteRepository = $quoteRepository;
    }

    /**
     * @return string
     * @since 100.2.0
     */
    public function getContinueUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }

    public function isPaymentwallPaymentMethod()
    {
        $quoteId = $this->getRequest()->getParam('quote-id');
        if (!$quoteId) {
            return false;
        }
        $quote = $this->_quoteRepository->get($quoteId);

        $paymentMethod = $quote->getPayment()->getMethod();
        if ($paymentMethod == Brick::PAYMENT_METHOD_CODE || $paymentMethod == Pingback::PAYMENTWALL_METHOD_CODE) {
            return true;
        }

        return false;
    }
}
