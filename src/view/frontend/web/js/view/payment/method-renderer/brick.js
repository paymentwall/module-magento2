define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'https://api.paymentwall.com/brick/brick.1.3.js'
    ],
    function (Component, $, placeOrderAction, quote) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/brick'
            },

            getCode: function () {
                return 'paymentwall_brick';
            },

            isActive: function () {
                return true;
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getPublicKey: function () {
                return window.checkoutConfig.payment.ccform.public_key;
            },

            initBrick: function () {
                this.brick = new Brick({
                    public_key: this.getPublicKey(),
                    form: {formatter: false}
                }, 'custom');
            },

            placeOrder: function (data, event) {
                if (this.validate()) {
                    var cardInfo = this.prepareCardInfo(data);

                    quote.guestEmail = this.getCustomerEmail();

                    this.brick.tokenizeCard(
                        cardInfo,
                        function (response) {
                            if (response.type == 'Error') {
                                alert("Brick Error(s):\nCode [" + response.code + "]: " + response.error);
                                return false;
                            } else {
                                var paymentData = {
                                    "method": 'paymentwall_brick',
                                    "po_number": null,
                                    "additional_data": {
                                        "paymentwall_pwbrick_token": response.token,
                                        "paymentwall_pwbrick_fingerprint": Brick.getFingerprint()
                                    }
                                };
                                // Call origin function
                                var placeOrder = placeOrderAction(paymentData, true);

                                $.when(placeOrder).fail(function (errors) {
                                    alert(errors.responseJSON.message);
                                    return false;
                                }).done(function (result) {
                                    
                                });
                                return true;
                            }
                        }
                    );

                }
                return false;
            },
            prepareCardInfo: function (data) {
                return {
                    card_number: data.creditCardNumber(),
                    card_expiration_month: data.creditCardExpMonth(),
                    card_expiration_year: data.creditCardExpYear(),
                    card_cvv: data.creditCardVerificationNumber(),
                };
            },
            getCustomerEmail: function () {
                return JSON.parse(localStorage['mage-cache-storage'])['checkout-data']['validatedEmailValue'];
            },
        });
    }
);
