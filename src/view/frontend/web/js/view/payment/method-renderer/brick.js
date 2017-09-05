/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'brickjs',
        'Magento_Payment/js/model/credit-card-validation/validator',

        'Magento_Checkout/js/model/customer-email-validator'
    ],
    function (ko, $, Component, brickjs, ccvalidator, customerEmailValidator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/ccform'
            },
            brick_token: '',
            brick_fingerprint: '',
            brick_secure_token: '',
            brick_charge_id: '',

            initialize: function () {
                this.brick = new Brick({
                    public_key: this.getPublicKey(),
                    form: {formatter: false}
                }, 'custom');

                var self = this;

                this._super();

                this.creditCardNumber.subscribe(function (value) {
                    self.generateBrickToken();
                });

                this.creditCardExpYear.subscribe(function (value) {
                    self.generateBrickToken();
                });

                this.creditCardExpMonth.subscribe(function (value) {
                    self.generateBrickToken();
                });

                this.creditCardVerificationNumber.subscribe(function (value) {
                    self.generateBrickToken();
                });
            },

            placeOrder: function (data, event) {
                var self = this;
                this._super();
                window.addEventListener("message", function (e) { self.threeDSecureMessageHandle(e) }, false);
                var i = setInterval(function(){
                    if(self.isPlaceOrderActionAllowed() === true) {
                        self.brick_charge_id = '';
                        self.brick_secure_token = '';
                        clearInterval(i);
                    }
                }, 300);

            },

            threeDSecureMessageHandle: function (event) {
                var origin = event.origin || event.originalEvent.origin;
                if (origin !== "https://api.paymentwall.com") {
                    return;
                }
                var brickData = JSON.parse(event.data);
                if (brickData && brickData.event == '3dSecureComplete') {
                    this.brick_secure_token = brickData.data.secure_token;
                    this.brick_charge_id = brickData.data.charge_id;
                    this.placeOrder();

                }
            },

            generateBrickToken: function() {
                var self = this;
                if (this.creditCardNumber().length >= 13 && this.creditCardExpYear() != undefined && this.creditCardExpMonth() != undefined && this.creditCardVerificationNumber().length > 2) {
                    this.brick.tokenizeCard(
                        {
                            card_number: this.creditCardNumber(),
                            card_expiration_month: this.creditCardExpMonth(),
                            card_expiration_year: this.creditCardExpYear(),
                            card_cvv: this.creditCardVerificationNumber(),
                        },
                        function (response) {
                            if (response.type == 'Error') {
                                self.brick_token = '';
                                self.brick_fingerprint = '';
                            } else {
                                self.brick_token = response.token;
                                self.brick_fingerprint = Brick.getFingerprint();
                            }
                        }
                    );
                } else {
                    this.brick_token = '';
                    this.brick_fingerprint = '';
                }
            },

            getPublicKey: function () {
                return window.checkoutConfig.payment.paymentwall_brick.public_key;
            },

            getCode: function() {
                return 'paymentwall_brick';
            },

            isActive: function () {
                return true;
            },

            hasVerification: function () {
                return true;
            },


            getData: function() {
                return {
                    'method': this.getCode(),
                    "additional_data": {
                        "pwbrick_token": this.brick_token,
                        "pwbrick_fingerprint": this.brick_fingerprint,
                        "brick_secure_token": this.brick_secure_token,
                        "brick_charge_id": this.brick_charge_id
                    }
                };
            },

            beforePlaceOrder: function (data) {
                if (quote.billingAddress() === null && typeof data.details.billingAddress !== 'undefined') {
                    this.setBillingAddress(data.details, data.details.billingAddress);
                }

            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

        });
    }
);