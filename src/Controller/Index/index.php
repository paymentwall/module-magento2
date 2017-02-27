<?php
namespace Paymentwall\Paymentwall\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $product;
    protected $cart;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductFactory $product,
        \Magento\Framework\Data\Form\FormKey $formkey,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Checkout\Model\Cart $cartModel,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\Quote\Address\Rate $shippingRate
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->_storeManager = $storeManager;
        $this->_product = $product;
        $this->_formkey = $formkey;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderService = $orderService;
        $this->cart = $cartModel;

        parent::__construct($context);

        $this->customerSession = $this->_objectManager->get('Magento\Customer\Model\Session');
        $this->helper = $this->_objectManager->get('Paymentwall\Paymentwall\Model\Helper');

        $this->pwLocalModel = new \Paymentwall\Paymentwall\Model\PWLocal(
            $resultPageFactory, $storeManager, $product, $formkey,
            $quote, $quoteManagement, $customerFactory,
            $customerRepository, $orderService, $cartModel,
            $this->helper, $this->customerSession, $cartRepositoryInterface, $cartManagementInterface, $shippingRate, $this->_objectManager);

    }


    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute()
    {

        if (!$this->cart->getQuote()->getId()) {
            $this->_redirect('/');
        } else {
            $resultPage = $this->resultPageFactory->create();
            $currencyCode = $this->cart->getQuote()->getStoreCurrencyCode();
            $email = $_POST['email'];
            $customerEmail = $this->pwLocalModel->getEmailCustomer() == null ? $email : $this->pwLocalModel->getEmailCustomer();

            $tempOrder = [
                'currency_id' => $currencyCode,
                'email' => $customerEmail,
                'shipping_address' => $this->pwLocalModel->getShipping(),
                'items' => $this->pwLocalModel->getProducts()
            ];

            $tempOrder['billing_address'] = $this->getRequest()->getParam('billing_data') ? json_decode($this->getRequest()->getParam('billing_data'),true) : $this->pwLocalModel->getShipping();
            $result = $this->pwLocalModel->createMageOrder($tempOrder);

            if ($result['status']) {
                $params = [
                    'email' => $customerEmail,
                    'orderId' => $result['order_id'],
                    'currency' => $currencyCode,
                    'total' => $result['total_paid']
                ];

                $widget = $this->pwLocalModel->getWidget($params);

                $resultPage->getConfig()->getTitle()
                    ->prepend(__('New order with Paymentwall: #' . $result['order_id']));
                $resultPage->getLayout()->getBlock('paymentwall_paymentwall')->setData('widget', $widget);
            } else {
                $resultPage->getConfig()->getTitle()
                    ->prepend(__($result['message']));
            }
        }
        return $resultPage;
    }

}
