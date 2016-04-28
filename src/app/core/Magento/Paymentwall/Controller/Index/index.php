<?php
namespace Magento\Paymentwall\Controller\Index;
if (!class_exists('Paymentwall_Config')) {
    require_once("paymentwall-php/lib/paymentwall.php");
}

class Index extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $product;
    protected $cart;

    const STATE_PENDING_PAYMENT = 'pending_payment';
    const PAYMENT_METHOD = 'paymentwall';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Data\Form\FormKey $formkey,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Checkout\Model\Cart $cartModel
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
        $this->helper = $this->_objectManager->get('Magento\Paymentwall\Model\Helper');
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
            $customerEmail = $this->getEmailCustomer() == null ? $email : $this->getEmailCustomer();

            $tempOrder = [
                'currency_id' => $currencyCode,
                'email' => $customerEmail,
                'shipping_address' => $this->getShipping(),
                'items' => $this->getProducts()
            ];

            $result = $this->createMageOrder($tempOrder);

            if ($result['status']) {
                $params = [
                    'email' => $customerEmail,
                    'orderId' => $result['order_id'],
                    'currency' => $currencyCode,
                    'total' => $result['total_paid']
                ];

                $widget = $this->getWidget($params);

                $resultPage->getConfig()->getTitle()
                    ->prepend(__('New order with Paymentwall: #' . $result['order_id']));
                $resultPage->getLayout()->getBlock('magento_paymentwall')->setData('widget', $widget);
            } else {
                $resultPage->getConfig()->getTitle()
                    ->prepend(__($result['message']));
            }
        }
        return $resultPage;
    }

    private function getWidget($params)
    {
        $this->helper->getInitConfig();
        $widget = new \Paymentwall_Widget(
            $params['email'], // id of the end-user who's making the payment
            $this->helper->getConfig('widget_code'), // widget code, e.g. p1; can be picked inside of your merchant account
            [ // product details for Non-Stored Product Widget Call. To let users select the product on Paymentwall's end, leave this array empty
                new \Paymentwall_Product(
                    $params['orderId'], // id of the product in your system
                    $params['total'], // price
                    $params['currency'], // currency code
                    "Order #" . $params['orderId'], // product name
                    \Paymentwall_Product::TYPE_FIXED
                )
            ],
            // additional parameters
            array_merge(
                [
                    'integration_module' => 'magento2',
                    'test_mode' => $this->helper->getConfig('test_mode')
                ],
                $this->getUserProfileData()
            )
        );
        return $widget->getHtmlCode(['width' => '100%', 'height' => '400px']);
    }

    private function getEmailCustomer()
    {
        $email = null;
        if ($this->customerSession->isLoggedIn()) {
            $email = $this->customerSession->getCustomer()->getEmail();
        }
        return $email;
    }

    private function getShipping()
    {
        $shippingData = $this->cart->getQuote()->getShippingAddress()->getData();
        $shipping = [
            'firstname' => $shippingData['firstname'],
            'lastname' => $shippingData['lastname'],
            'street' => $shippingData['street'],
            'city' => $shippingData['city'],
            'country_id' => $shippingData['country_id'],
            'region' => $shippingData['region'],
            'postcode' => $shippingData['postcode'],
            'telephone' => $shippingData['telephone'],
            'fax' => $shippingData['fax'],
            'shipping_method' => $shippingData['shipping_method'],
            'save_in_address_book' => $shippingData['save_in_address_book']
        ];
        return $shipping;
    }

    private function getProducts()
    {
        $products = [];
        $items = $this->cart->getItems()->toArray();

        if (!empty($items)) {
            foreach ($items['items'] AS $item) {
                $products[] = [
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price']
                ];
            }
        }
        return $products;
    }

    private function getUserProfileData()
    {
        $shippingData = $this->cart->getQuote()->getShippingAddress()->getData();
        return [
            'customer[city]' => $shippingData['city'],
            'customer[state]' => $shippingData['region'],
            'customer[address]' => $shippingData['street'],
            'customer[country]' => $shippingData['country_id'],
            'customer[zip]' => $shippingData['postcode'],
            'customer[firstname]' => $shippingData['firstname'],
            'customer[lastname]' => $shippingData['lastname']
        ];
    }

    public function createMageOrder($orderData)
    {
        $store = $this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customet by email address

        if (!$customer->getEntityId()) {
            //If not avilable then create this customer
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($orderData['email']);
            $customer->save();
        }
        $quote = $this->quote->create(); //Create object of quote

        $quote->setStore($store); //set store for which you create quote
        // if you have allready buyer id then you can load customer directly
        $customer = $this->customerRepository->getById($customer->getEntityId());

        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer

        //add items in quote
        foreach ($orderData['items'] as $item) {
            $product = $this->_product->load($item['product_id']);
            $product->setPrice($item['price']);
            $quote->addProduct(
                $product,
                intval($item['qty'])
            );
        }
        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);

        // Collect Rates and Set Shipping & Payment Method
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($orderData['shipping_address']['shipping_method']); //shipping method
        $quote->setPaymentMethod(self::PAYMENT_METHOD); //payment method
        $quote->setInventoryProcessed(false); //not effetc inventory
        $quote->save(); //Now Save quote and your quote is ready
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => self::PAYMENT_METHOD]);
        // Collect Totals & Save Quote
        $quote->collectTotals()->save();
        // Create Order From Quote
        $order = $this->quoteManagement->submit($quote);

        if (!empty($order)) {
            $order->setEmailSent(1);
            $result['order_id'] = $order->getRealOrderId();
            $result['total_paid'] = $order->getTotalPaid();
            $result['status'] = 1;

            $order->setStatus(self::STATE_PENDING_PAYMENT);
            $order->save();

            $this->cart->getQuote()->removeAllItems();
            $this->cart->getQuote()->delete();
            $this->cart->getQuote()->save();
        } else {
            $result = ['status' => 0, 'message' => 'Create order has been error'];
        }
        return $result;
    }
}
