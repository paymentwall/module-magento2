<?php
namespace Paymentwall\Paymentwall\Block;

use \Magento\Framework\View\Element\Template\Context;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use \Paymentwall\Paymentwall\Model\Paymentwall as PaymentModel;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\View\Element\Template;

class Brick extends Template
{

    protected $checkoutSession;
    protected $customerSession;
    protected $request;
    private $config;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        PaymentModel $paymentModel,
        RequestInterface $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->paymentModel = $paymentModel;
        $this->request = $request;
        $this->config = $config;
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

        $quote = $this->checkoutSession->getQuote();
        $billing = $quote->getBillingAddress();
        $testMode = $this->config->getValue('payment/brick/test_mode', ScopeInterface::SCOPE_STORE);
        $publicTestKey = $this->config->getValue('payment/brick/public_test_key', ScopeInterface::SCOPE_STORE);
        $publicKey = $this->config->getValue('payment/brick/public_key', ScopeInterface::SCOPE_STORE);

        $paymentData = [
            'amount' => round(floatval($quote->getGrandTotal()), 2),
            'currency' => $quote->getQuoteCurrencyCode(),
            'public_key' => $testMode ? $publicTestKey : $publicKey
        ];
        $this->addData(
            [
                'billing' => $this->getBillingInfo($billing),
                'payment' => $paymentData,
                'container' => "<div id='brick-payments-container'></div>"
            ]
        );

        return true;
    }

    private function getBillingInfo(Address $billing)
    {
        $billingInfo = [
            'email' => $billing->getEmail(),
            'cardholder' => $billing->getFirstname(). ' ' .$billing->getLastname(),
            'zip' => $billing->getPostcode() ? $billing->getPostcode() : '',
            'address' => $billing->getStreet() ? $address = implode(', ',$billing->getStreet()) : '',
            'country' => $billing->getCountryId() ? $billing->getCountryId() : '',
            'city' => $billing->getCity() ? $billing->getCity() : '',
            'state' => $billing->getRegionCode() ? $billing->getRegionCode() : ''
        ];

        return $billingInfo;
    }
}
