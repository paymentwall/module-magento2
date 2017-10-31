<?php
namespace Paymentwall\Paymentwall\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class DataAssignObserver extends AbstractDataAssignObserver
{
    protected $prdMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $prdMetadata
    ) {
        $this->prdMetadata = $prdMetadata;
    }
    
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);

        $version = $this->prdMetadata->getVersion();
        if (version_compare($version, '2.1', '>')) {
            $paymentInfo = $this->readPaymentModelArgument($observer);
        } else {
            $paymentInfo = $method->getInfoInstance();
        }

        $additionalData = $data->getData('additional_data');
        if (!is_array($additionalData)) {
            return;
        }

        if (!empty($additionalData['extension_attributes'])) {
            unset($additionalData['extension_attributes']);
        }

        foreach ($additionalData as $key => $val) {
            $paymentInfo->setAdditionalInformation($key, $val);
        }
    }
}
