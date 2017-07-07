<?php

namespace Paymentwall\Paymentwall\Model;

class PWLocal
{
    const STATE_PENDING_PAYMENT = 'pending_payment';
    const PAYMENT_METHOD = 'paymentwall';

    public function __construct(
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
        \Paymentwall\Paymentwall\Model\Helper $helper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\Quote\Address\Rate $shippingRate,
        \Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Framework\App\Config\ScopeConfigInterface $scope,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
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
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->shippingRate = $shippingRate;
        $this->_objectManager = $objectManager;
		$this->scope = $scope;
        $this->orderCollectionFactory = $orderCollectionFactory;

        $this->helper = $helper;
        $this->customerSession = $customerSession;
    }

    public function getWidget($params)
    {
        $this->helper->getInitConfig();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer=$this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($params['email']);// load customet by email address



        $userProfileData = array();

        $widget = new \Paymentwall_Widget(
            $customer->getEntityId(), // id of the end-user who's making the payment
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
                    'test_mode' => $this->helper->getConfig('test_mode'),
                    'success_url' => $this->_storeManager->getStore()->getBaseUrl().'checkout/onepage/success',
                ],
                $this->getUserProfileData($customer)
            )
        );
        return $widget->getHtmlCode(['width' => '100%', 'height' => '650px']);
    }

    public function getEmailCustomer()
    {
        $email = null;
        if ($this->customerSession->isLoggedIn()) {
            $email = $this->customerSession->getCustomer()->getEmail();
        }
        return $email;
    }

    public function getShipping()
    {
        if($this->cart->getQuote()->isVirtual()) {
            $shippingData = $this->cart->getQuote()->getBillingAddress()->getData();
            $customerSession = $this->_objectManager->get('Magento\Customer\Model\Session');
            if ($customerSession->isLoggedIn() && !$shippingData['firstname'] && !$shippingData['street']) {
                $billingID =  $customerSession->getCustomer()->getDefaultBilling();
                $address = $this->_objectManager->create('Magento\Customer\Model\Address')->load($billingID);
                $addressData = $address->getData();
                $shippingData['firstname'] = $addressData['firstname'];
                $shippingData['lastname'] = $addressData['lastname'];
                $shippingData['postcode'] = $addressData['postcode'];
                $shippingData['region'] = $addressData['region'];
                $shippingData['region_id'] = $addressData['region_id'];
                $shippingData['street'] = $addressData['street'];
                $shippingData['city'] = $addressData['city'];
                $shippingData['country_id'] = $addressData['country_id'];
                $shippingData['telephone'] = $addressData['telephone'];
            }
        } else
            $shippingData = $this->cart->getQuote()->getShippingAddress()->getData();
        $shipping = [
            'firstname' => $shippingData['firstname'],
            'lastname' => $shippingData['lastname'],
            'street' => $shippingData['street'],
            'city' => $shippingData['city'],
            'country_id' => $shippingData['country_id'],
            'region' => $shippingData['region'],
            'region_id' => $shippingData['region_id'],
            'postcode' => $shippingData['postcode'],
            'telephone' => $shippingData['telephone'],
            'fax' => $shippingData['fax'],
            'shipping_method' => $shippingData['shipping_method'],
            'save_in_address_book' => $shippingData['save_in_address_book']
        ];
        return $shipping;
    }


    public function getProducts()
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

    public function getUserProfileData($customer)
    {
        $shippingData = $this->cart->getQuote()->getShippingAddress()->getData();
        $data = [
            'customer[city]' => $shippingData['city'],
            'customer[state]' => $shippingData['region'],
            'customer[address]' => $shippingData['street'],
            'customer[country]' => $shippingData['country_id'],
            'customer[zip]' => $shippingData['postcode'],
            'customer[firstname]' => $shippingData['firstname'],
            'customer[lastname]' => $shippingData['lastname']
        ];
        if ($this->helper->getConfig('user_profile_api')) {
            $countOrders = 0;
            $totalAmount = 0;
            $salesOrderCollection = $this->orderCollectionFactory->create();
            if ($customer->getEntityId()) {
                $salesOrderCollection->addFieldToFilter('customer_id', $customer->getEntityId());
                $items = $salesOrderCollection->getItems();
                $USDcurrency = $this->_objectManager->create('Magento\Directory\Model\CurrencyFactory')->create()->load('USD');
                foreach($items as $ord) {
                    if ($ord->getPayment()->getMethod()=='paymentwall') {
                        if ($ord->getData('status')=='complete')
                            $countOrders++;
                        $orderGrandTotal = $ord->getGrandTotal();
                        if ($ord->getOrderCurrencyCode()!='USD') {
                            $orderGrandTotal = $this->currencyConvert($orderGrandTotal,$ord->getOrderCurrency(),$USDcurrency);
                        }
                        $totalAmount += $orderGrandTotal;
                    }
                }
            }
            $data = array_merge(
                $data,
                [
                    'history[payments_amount]' => $totalAmount,
                    'history[delivered_products]' => $countOrders
                ]
            );
        }
        return $data;
    }

    public function createMageOrder($orderData)
    {
        $store = $this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer=$this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customet by email address

        //check the customer
        if(!$customer->getEntityId()){
            //If not avilable then create this customer
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($orderData['email']);
            $customer->save();
        }
        //init the quote
        $cartId = $this->cartManagementInterface->createEmptyCart();
        $cart = $this->cartRepositoryInterface->get($cartId);
        $cart->setStore($store);
        // if you have already buyer id then you can load customer directly
        $customer= $this->customerRepository->getById($customer->getEntityId());
        $cart->setCurrency();
        $cart->assignCustomer($customer); //Assign quote to customer
        //add items in quote
        foreach($orderData['items'] as $item){
            $product = $this->_product->create()->load($item['product_id']);
            $cart->addProduct(
                $product,
                intval($item['qty'])
            );
        }
        //Set Address to quote @todo add section in order data for seperate billing and handle it
        $cart->getBillingAddress()->addData($orderData['billing_address']);
        $cart->getShippingAddress()->addData($orderData['shipping_address']);
        // Collect Rates and Set Shipping & Payment Method
        $this->shippingRate
            ->setCode('freeshipping_freeshipping')
            ->getPrice(1);
        $shippingAddress = $cart->getShippingAddress();
        //@todo set in order data
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($orderData['shipping_address']['shipping_method']); //shipping method
		//$cart->setPaymentMethod(self::PAYMENT_METHOD);
        //@todo insert a variable to affect the invetory
        $cart->setInventoryProcessed(false);
        // Set sales order payment
        $cart->getPayment()->importData(['method' => self::PAYMENT_METHOD]);
        // Collect total and saeve
        $cart->collectTotals();
        // Submit the quote and create the order
        $cart->save();
        $cart = $this->cartRepositoryInterface->get($cart->getId());
        $order_id = $this->cartManagementInterface->placeOrder($cart->getId());

        if (($order_id)) {
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($order_id);
            $order->setEmailSent(1);
            $result['order_id'] = $order->getRealOrderId();
            $result['total_paid'] = $order->getData('total_due');
            $result['status'] = 1;

            $order->setStatus(self::STATE_PENDING_PAYMENT);
            $order->setState(self::STATE_PENDING_PAYMENT);
            $order->save();

            $checkoutSession = $this->_objectManager->get('Magento\Checkout\Model\Session');
            $allItems = $checkoutSession->getQuote()->getAllVisibleItems();
            foreach ($allItems as $item) {
                $itemId = $item->getItemId();
                $this->cart->removeItem($itemId)->save();
            }
        } else {
            $result = ['status' => 0, 'message' => 'Create order has been error'];
        }
        return $result;
    }

    public function currencyConvert($amount, $fromCurrency = null, $toCurrency = null)
    {
        if (!$fromCurrency){
            $fromCurrency = $this->_storeManager->getStore()->getBaseCurrency();
        }
        if (!$toCurrency){
            $toCurrency = $this->_storeManager->getStore()->getCurrentCurrency();
        }
        if (is_string($fromCurrency)) {
            $rateToBase = $this->_currencyFactory->create()->load($fromCurrency)->getAnyRate($this->_storeManager->getStore()->getBaseCurrency()->getCode());
        } elseif ($fromCurrency instanceof \Magento\Directory\Model\Currency) {
            $rateToBase = $fromCurrency->getAnyRate($this->_storeManager->getStore()->getBaseCurrency()->getCode());
        }
        $rateFromBase = $this->_storeManager->getStore()->getBaseCurrency()->getRate($toCurrency);
        if($rateToBase && $rateFromBase){
            $amount = $amount * $rateToBase * $rateFromBase;
        } else {
            throw new InputException(__('Please correct the target currency.'));
        }
        return $amount;

    }

}