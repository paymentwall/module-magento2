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
            throw new CouldNotSaveException(
                __($response['response']['error']['message'])
            );
        }
    }

}
