<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Paymentwall\Paymentwall\Block;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * @api
 * @since 100.0.2
 */
class Registration extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Customer\Model\Registration
     */
    protected $registration;

    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $accountManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Address\Validator
     */
    protected $addressValidator;

    protected $_quoteRepository;

    protected $helper;

    /**
     * @param Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Model\Registration $registration
     * @param \Magento\Customer\Api\AccountManagementInterface $accountManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Address\Validator $addressValidator
     * @param array $data
     * @codeCoverageIgnore
     */
    public function __construct(
        Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Registration $registration,
        \Magento\Customer\Api\AccountManagementInterface $accountManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Address\Validator $addressValidator,
        array $data = [],
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        CollectionFactory $orderCollection,
        \Paymentwall\Paymentwall\Helper\Helper $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->registration = $registration;
        $this->accountManagement = $accountManagement;
        $this->orderRepository = $orderRepository;
        $this->addressValidator = $addressValidator;
        parent::__construct($context, $data);
        $this->_quoteRepository = $quoteRepository;
        $this->orderCollection = $orderCollection->create();
        $this->helper = $helper;
    }

    /**
     * Retrieve current email address
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getEmailAddress()
    {
        $quote = $this->getQuote();
        if (!$quote) {
            return '';
        }

        return $quote->getCustomerEmail();
    }

    /**
     * Retrieve account creation url
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getCreateAccountUrl()
    {
        if ($this->wasOrderCreatedByQuote()) {
            return $this->getUrl('checkout/account/delegateCreate');
        }

        return $this->getUrl('customer/account/create');
    }

    /**
     * {@inheritdoc}
     */
    public function toHtml()
    {
        if ($this->customerSession->isLoggedIn()
            || !$this->registration->isAllowed()
            || !$this->accountManagement->isEmailAvailable($this->getEmailAddress())
        ) {
            return '';
        }
        return parent::toHtml();
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function wasOrderCreatedByQuote() {
        $quote = $this->getQuote();
        if (!$quote) {
            return false;
        }

        $order = $this->helper->getOrderByQuoteId($quote->getId());
        $orderId = $order->getId();
        if (empty($orderId)) {
            return false;
        }

        $this->checkoutSession->setLastOrderId($orderId);
        return true;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getQuote()
    {
        $quoteId = $this->getRequest()->getParam('quote-id');
        if (!$quoteId) {
            return null;
        }
        $quote = $this->_quoteRepository->get($quoteId);
        if (!$quote->getId()) {
            return null;
        }

        return $quote;
    }
}
