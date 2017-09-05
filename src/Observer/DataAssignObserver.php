<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);

        $paymentInfo = $this->readPaymentModelArgument($observer);

        $additionalData = $data->getData('additional_data');
        if (!is_array($additionalData)) {
            return;
        }

        if (!empty($additionalData['extension_attributes'])) {
            unset($additionalData['extension_attributes']);
        }

        foreach ($additionalData as $key=>$val) {
            $paymentInfo->setAdditionalInformation($key,$val);
        }

    }
}
