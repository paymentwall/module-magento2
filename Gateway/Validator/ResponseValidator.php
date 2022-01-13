<?php
namespace Paymentwall\Paymentwall\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;

class ResponseValidator extends AbstractValidator
{
    /**
     * Performs validation
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {

        $response = $validationSubject['response'];
        $responseData = $response['payment_details'];

        if ($responseData['charge_is_captured'] && !$responseData['charge_is_under_review']) {
            return $this->createResult(
                true,
                []
            );
        }

        if ($responseData['charge_is_under_review']) {
            throw new CouldNotSaveException(
                __('#brick_under_review#')
            );
        }

        throw new CouldNotSaveException(
            __('Payment failed!')
        );
    }
}
