define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'https://api.paymentwall.com/brick/brick.1.3.js',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment-service',
        'Magento_Checkout/js/model/payment/method-converter',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/checkout-data',
        'Magento_Customer/js/customer-data',
        'Magento_CheckoutAgreements/js/model/agreement-validator'
    ],
    function (Component, $, quote, ccvalidator, brick, urlBuilder, storage, customer, paymentService, methodConverter, errorProcessor, malert, checkout, customerdata, agreementValidator) {
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
                if(agreementValidator.validate()) {
                    if (this.validate()) {
                        var cardInfo = this.prepareCardInfo(data);
                        var self = this;
                        quote.guestEmail = this.getValidatedEmailValue();

                        this.brick.tokenizeCard(
                            cardInfo,
                            function (response) {
                                if (response.type == 'Error') {
                                    malert({
                                        content: "Brick Error(s):\nCode [" + response.code + "]: " + response.error
                                    });
                                    return false;
                                } else {
                                    $('#paymentwall_pwbrick_token').val(response.token);
                                    var fingerPrint = Brick.getFingerprint();
                                    $('#paymentwall_pwbrick_fingerprint').val(fingerPrint);
                                    var paymentData = {
                                        "method": 'paymentwall_brick',
                                        "po_number": null,
                                        "additional_data": {
                                            "paymentwall_pwbrick_token": response.token,
                                            "paymentwall_pwbrick_fingerprint": fingerPrint,
                                        }
                                    };

                                    var serviceUrl, payload;

                                    if (!customer.isLoggedIn()) {
                                        serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/set-payment-information', {
                                            cartId: quote.getQuoteId()
                                        });
                                        payload = {
                                            cartId: quote.getQuoteId(),
                                            email: quote.guestEmail,
                                            paymentMethod: paymentData,
                                            billingAddress: quote.billingAddress()
                                        };
                                    } else {
                                        serviceUrl = urlBuilder.createUrl('/carts/mine/set-payment-information', {});
                                        payload = {
                                            cartId: quote.getQuoteId(),
                                            paymentMethod: paymentData,
                                            billingAddress: quote.billingAddress()
                                        };
                                    }

                                    storage.post(
                                        serviceUrl, JSON.stringify(payload)
                                    ).done(
                                        function (response) {
                                            self.chargeBrick(paymentData, self);
                                        }
                                    ).fail(
                                        function (response) {
                                            malert(response);
                                        }
                                    );
                                    window.addEventListener("message", function (e) { self.threeDSecureMessageHandle(e, self) }, false);
                                }
                            }
                        );

                    }
                    return false;
                }
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

            threeDSecureMessageHandle: function (event, brickObject) {
                var origin = event.origin || event.originalEvent.origin;
                if (origin !== "https://api.paymentwall.com") {
                    return;
                }
                var brickData = JSON.parse(event.data);
                if (brickData && brickData.event == '3dSecureComplete') {
                    var paymentData = {
                        "method": 'paymentwall_brick',
                        "po_number": null,
                        "additional_data": {
                            "paymentwall_pwbrick_token": $('#paymentwall_pwbrick_token').val(),
                            "paymentwall_pwbrick_fingerprint": $('#paymentwall_pwbrick_fingerprint').val(),
                            "brick_secure_token": brickData.data.secure_token,
                            "brick_charge_id": brickData.data.charge_id
                        }
                    };
                    brickObject.chargeBrick(paymentData, brickObject);

                }
            },

            getValidatedEmailValue: function () {
                return checkout.getValidatedEmailValue();
            },

            getStoreUrl: function () {
                return _.map(window.checkoutConfig.storeUrl, function (value, key) {
                    return value;
                });
            },

            chargeBrick: function (paymentData, brickObject) {
                var self = brickObject;
                $.ajax({
                    showLoader: true,
                    url: self.getStoreUrl() + 'paymentwall/Index/Brick',
                    data: paymentData,
                    type: "POST",
                }).done(function (resp) {
                    var cart = {
                        'data_id': null,
                        'extra_actions' : null,
                        'isGuestCheckoutAllowed' : null,
                        'items' : [],
                        'possible_onepage_checkout' : null,
                        'subtotal' : null,
                        'subtotal_excl_tax' : null,
                        'subtotal_incl_tax' : null,
                        'summary_count': null,
                        'website_id': null
                    };
                    customerdata.set('cart',cart);
                    if(resp.result.result == 'secure') {
                        var win = window.open("", "Brick: Verify 3D secure", "toolbar=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, width=1024, height=720");
                        var popup = win.document.body;
                        $(popup).append(resp.result.secure);
                        win.document.forms[0].submit();
                        return false;
                    } else if (resp.result.result == 'error') {
                        malert({
                            content: resp.result.message,
                            actions: {
                                always: function() {
                                    window.location.href = self.getStoreUrl() + 'checkout/cart/#payment';
                                }
                            }
                        });
                        return false;
                    }
                    window.location.href = self.getStoreUrl() + 'checkout/onepage/success';
                }).fail(function (resp) {
                    console.log(resp);
                });
            }
        });
    }
);
