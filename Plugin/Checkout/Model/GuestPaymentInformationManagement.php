<?php
namespace Paymentwall\Paymentwall\Plugin\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;

class GuestPaymentInformationManagement
{
    protected $prdMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $prdMetadata
    ) {
        $this->prdMetadata = $prdMetadata;
    }

    public function aroundSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\GuestPaymentInformationManagement $subject,
        callable $proceed,
        ...$args
    ) {
        try {
            $result = $proceed(...$args);
            return $result;
        } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
            $version = $this->prdMetadata->getVersion();
            if (version_compare($version, '2.1', '>')) {
                throw new CouldNotSaveException(
                    __($e->getPrevious()->getMessage()),
                    $e->getPrevious()
                );
            } else {
                throw new CouldNotSaveException(
                    __($e->getMessage()),
                    $e
                );
            }
        }
    }
}
