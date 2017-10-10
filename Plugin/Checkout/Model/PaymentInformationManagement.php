<?php
namespace Paymentwall\Paymentwall\Plugin\Checkout\Model;

use Magento\Framework\Exception\CouldNotSaveException;

class PaymentInformationManagement
{
    public function aroundSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        callable $proceed,
        ...$args
    ) {
        try {
            $result = $proceed(...$args);
        } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
            throw new CouldNotSaveException(
                __($e->getPrevious()->getMessage()),
                $e->getPrevious()
            );
        }

        return $result;
    }
}
