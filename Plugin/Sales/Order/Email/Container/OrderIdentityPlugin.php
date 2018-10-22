<?php

namespace Paymentwall\Paymentwall\Plugin\Sales\Order\Email\Container;
use Paymentwall\Paymentwall\Model\Paymentwall;

class OrderIdentityPlugin
{
    /**
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param \Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject
     * @param callable $proceed
     * @return bool
     */
    public function aroundIsEnabled(\Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject, callable $proceed)
    {
        if ($this->checkoutSession->getQuote()->getPayment()->getMethod() == Paymentwall::PAYMENT_METHOD_CODE) {
            $forceOrderMailSentOnSuccess = $this->checkoutSession->getForceOrderMailSentOnSuccess();

            if (isset($forceOrderMailSentOnSuccess) && $forceOrderMailSentOnSuccess) {
                $this->checkoutSession->unsForceOrderMailSentOnSuccess();
                return $proceed();
            }
            return false;
        }
        return $proceed();
    }
}
