<?php
namespace Paymentwall\Paymentwall\Controller\Index;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Payment\Model\IframeConfigProvider;
use Magento\Quote\Api\CartManagementInterface;

class Brick extends \Magento\Framework\App\Action\Action
{
    protected $cartManagement;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Checkout\Model\Type\Onepage
     */
    protected $onepageCheckout;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    public function __construct(
        Context $context,
        \Magento\Framework\Model\Context $contextModel,
        Registry $coreRegistry,
        CartManagementInterface $cartManagement,
        Onepage $onepageCheckout,
        JsonHelper $jsonHelper,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->eventManager = $context->getEventManager();
        $this->cartManagement = $cartManagement;
        $this->onepageCheckout = $onepageCheckout;
        $this->jsonHelper = $jsonHelper;
        $this->_objectManager = $objectManager;
        parent::__construct($context);
        $this->_checkoutSession = $this->_objectManager->get('Magento\Checkout\Model\Session');

        $this->brickModel = new \Paymentwall\Paymentwall\Model\Brick(
            $contextModel, $coreRegistry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, $countryFactory, $objectManager
        );
    }

    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $result = new DataObject();
        $response = $this->getResponse();

                if(empty($params['additional_data']['brick_secure_token']) && empty($params['additional_data']['brick_charge_id'])) {
                    if (!empty($params['method'])) {

                        $this->onepageCheckout->getCheckoutMethod();
                        $orderId = $this->cartManagement->placeOrder($this->_getCheckout()->getQuote()->getId());
                        if (!empty($orderId)) {
                            $chargeResult = $this->brickModel->charge($this->getRequest()->getParams(), $orderId);
                            $result->setData('success', true);
                            $result->setData('result', $chargeResult);
                        } else {
                            $result->setData('error', true);
                            $result->setData('error_messages','error');
                        }
                    } else {
                        $result->setData('result', array(
                            'result' => 'error',
                            'message' => __('Please choose a payment method.')
                        ));
                    }
                } else {

                    $brickSession = $this->_checkoutSession->getBrickSessionData();
                    if (!empty($brickSession['orderId'])) {
                        $chargeResult = $this->brickModel->charge($this->getRequest()->getParams(), $brickSession['orderId']);
                        $result->setData('success', true);
                        $result->setData('result',$chargeResult);
                    } else {
                        $result->setData('error', true);
                        $result->setData('error_messages','error');
                    }
                }

        if ($response instanceof Http) {
            $response->representJson($this->jsonHelper->jsonEncode($result));
        }
    }

    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

}
