<?php

namespace Paymentwall\Paymentwall\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class RefundResponseValidator extends AbstractValidator
{
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response'];

        if (!empty($response['success'])) {
            return $this->createResult(true);
        }

        if (!empty($response['error']['message'])) {
            throw new \InvalidArgumentException($response['error']['message']);
        }

        throw new \InvalidArgumentException("Processing refund failed!, please try again");

    }
}
