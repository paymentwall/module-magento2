<?php
namespace Magento\Paymentwall\Controller\Index;
if (!class_exists('Paymentwall_Config')) {
    require_once("paymentwall-php/lib/paymentwall.php");
}

class Pingback extends \Magento\Framework\App\Action\Action
{
    const STATE_PROCESSING = 'processing';
    const STATE_CANCELED = 'canceled';
    const STATE_COMPLETE = 'complete';
    const PINGBACK_OK = 'OK';

    public function __construct(
        \Magento\Framework\App\Action\Context $context
    )
    {
        parent::__construct($context);
        $this->order = $this->_objectManager->get('Magento\Sales\Model\Order');
        $this->helper = $this->_objectManager->get('Magento\Paymentwall\Model\Helper');
    }

    public function execute()
    {
        $this->helper->getInitConfig();
        $pingback = new \Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
        if ($pingback->validate()) {
            $orderId = $pingback->getProductId();
            $orderStatus = self::STATE_CANCELED;
            if ($pingback->isDeliverable()) {
                $orderStatus = self::STATE_PROCESSING;
            } elseif ($pingback->isCancelable()) {
                $orderStatus = self::STATE_CANCELED;
            }
            $this->order->loadByIncrementId($orderId);
            $this->order->setStatus($orderStatus);
            $this->order->save();
            $result = self::PINGBACK_OK;
        } else {
            $result = $pingback->getErrorSummary();
        }
        echo $result;
        die;
    }
}