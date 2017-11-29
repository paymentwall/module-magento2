<?php
namespace Paymentwall\Paymentwall\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;

class ResponseValidator extends AbstractValidator
{
    const ONE_TIME_TOKEN_INVALID = 'One-time token is invalid.';
    /**
     * Performs validation
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];
        $responseData = $response['responseData'];

        if ($response['isSuccessful'] && empty($responseData['secure'])) {
            return $this->createResult(
                true,
                []
            );
        } elseif (!empty($responseData['secure'])) {
            throw new CouldNotSaveException(
                __('###secure###'.$responseData['secure']['formHTML'])
            );
        } else {
            if ($response['response']['error']['message'] == self::ONE_TIME_TOKEN_INVALID) {
                $response['response']['error']['message'] .= " Please refill credit card info.";
            }
            throw new CouldNotSaveException(
                __($response['response']['error']['message'])
            );
        }
    }
}
