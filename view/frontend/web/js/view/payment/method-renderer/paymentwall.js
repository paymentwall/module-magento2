define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Paymentwall_Paymentwall/js/action/redirect-to-widget',
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'jquery',
        'Magento_Checkout/js/model/error-processor',
    ],
    function (Component, redirectToWidget, quote, storage, url, fullScreenLoader, messageList, $, errorProcessor) {
        'use strict';

        const PW_LOCAL_METHOD_LS_KEY = 'pw_onestepcheckout_chosen_local_method';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/form',
                transactionResult: '',
                localMethods: [],
                chosenLocalMethod: '',
                billingCountryId: '',
            },

            initialize: function () {
                this._super();

                quote.paymentMethod.subscribe(function (paymentMethod) {
                    var chosenLocalMethod = localStorage.getItem(PW_LOCAL_METHOD_LS_KEY);

                    if (chosenLocalMethod && (!paymentMethod || paymentMethod.method != this.getCode())) {
                        localStorage.setItem(PW_LOCAL_METHOD_LS_KEY, null);
                        this.chosenLocalMethod(null);
                    }
                }, this);

                this.chosenLocalMethod.subscribe(function (localMethod) {
                    var paymentMethod = quote.paymentMethod();
                    var chosenLocalMethod = localStorage.getItem(PW_LOCAL_METHOD_LS_KEY);

                    if (localMethod) {
                        if (localMethod != chosenLocalMethod) {
                            localStorage.setItem(PW_LOCAL_METHOD_LS_KEY, localMethod);
                        }
                        if (!paymentMethod || paymentMethod.method != this.getCode()) {
                            quote.paymentMethod({
                                title: this.getTitle(),
                                method: this.getCode()
                            });
                        }
                    } else if (paymentMethod && paymentMethod.method == this.getCode()) {
                        quote.paymentMethod(null);
                    }
                }, this);

                var chosenLocalMethod = localStorage.getItem(PW_LOCAL_METHOD_LS_KEY);
                this.chosenLocalMethod(chosenLocalMethod);

                this.getUserCountry();
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'transactionResult',
                        'localMethods',
                        'chosenLocalMethod',
                        'billingCountryId'
                    ]);

                return this;
            },

            getCode: function () {
                return 'paymentwall';
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {

                    }
                };
            },

            beforePlaceOrder: function (data) {
                if (quote.billingAddress() === null && typeof data.details.billingAddress !== 'undefined') {
                    this.setBillingAddress(data.details, data.details.billingAddress);
                }
            },

            afterPlaceOrder: function () {
                localStorage.setItem(PW_LOCAL_METHOD_LS_KEY, null);
                redirectToWidget.execute(this.chosenLocalMethod(), this.isNewWindow(this.chosenLocalMethod()));
            },

            setBillingAddress: function (customer, address) {
                var billingAddress = {
                    street: [address.streetAddress],
                    city: address.locality,
                    postcode: address.postalCode,
                    countryId: address.countryCodeAlpha2,
                    email: customer.email,
                    firstname: customer.firstName,
                    lastname: customer.lastName,
                    telephone: customer.phone
                };

                billingAddress['region_code'] = address.region;
                billingAddress = createBillingAddress(billingAddress);
                quote.billingAddress(billingAddress);
            },

            getPaymentwallLocalMethods: function (countryCode) {
                var self = this;
                var totals = quote.getTotals()();
                var payLoad = {
                    country_code: countryCode,
                    currencyCode: this.getInitQuoteData()['quote_currency_code'],
                    amount: (totals ? totals : quote)['grand_total']
                };
                var paymentCode = this.getCode();

                fullScreenLoader.startLoader();

                var onFail = function (response) {
                    self.localMethods.splice(0);
                    self.chosenLocalMethod(null);
                    self.isPlaceOrderActionAllowed(false);
                };

                storage.post(
                    url.build('paymentwall/index/getlocalmethods/'),
                    JSON.stringify(payLoad),
                    true
                ).done(
                    function (response) {
                        var json = JSON.parse(response);
                        if (
                            typeof json != 'object'
                            || typeof json.data == 'undefined'
                            || !Array.isArray(json.data)
                            || $.isEmptyObject(json.data)
                        ) {
                            return onFail(response);
                        }
                        self.localMethods.splice(0);
                        self.localMethods.push(...json.data);

                        var isValidMethod = self.isValidLocalMethod(self.chosenLocalMethod());
                        if (!isValidMethod) {
                            self.chosenLocalMethod(null);
                        }

                        self.isPlaceOrderActionAllowed(true);
                    }
                ).fail(
                    function (response) {
                        onFail(response);
                    }
                )
                .always(
                    function () {
                        fullScreenLoader.stopLoader();
                    }
                );
            },

            getUserCountry: function () {
                var self = this;

                fullScreenLoader.startLoader();

                var onFail = function (response) {
                    console.log(response);
                };

                storage.post(
                    url.build('paymentwall/index/getusercountry/'),
                    null,
                    true
                ).done(
                    function (response) {
                        var json = JSON.parse(response);
                        if (
                            typeof json != 'object'
                            || typeof json.data == 'undefined'
                            || !json.data
                        ) {
                            return onFail(response);
                        }
                        // self.billingCountryId(json.data);
                        self.getPaymentwallLocalMethods(json.data);
                    }
                ).fail(
                    function (response) {
                        onFail(response);
                    }
                )
                .always(
                    function () {
                        fullScreenLoader.stopLoader();
                    }
                );
            },

            getInitQuoteData: function () {
                return window.checkoutConfig.quoteData;
            },

            isValidLocalMethod: function (method) {
                if (method) {
                    for (var localMethod of this.localMethods()) {
                        if (method == localMethod.id) {
                            return true;
                        }
                    }

                    return false;
                }

                return true;
            },

            isNewWindow: function (method) {
                if (method) {
                    for (var localMethod of this.localMethods()) {
                        if (method == localMethod.id && localMethod.new_window) {
                            return true;
                        }
                    }
                }

                return false;
            },

            isOscEnabled: function () {
                return typeof window.checkoutConfig.oscConfig != 'undefined';
            },

            pwPlaceOrder: function () {
                var pwPlaceOrderBtn = $('.pw-place-order-btn');

                $(document).one("click", ".pw-place-order-btn", getPaymentwallWidget)

                function getPaymentwallWidget()
                {
                    var paymentMethod = $('input[name="pwLocalMethod"]:checked').val();
                    var billingAddressData = $('div[class="payment-method _active"]').find('form').serialize();
                    var isBillingSameShipping = $('#billing-address-same-as-shipping-shared').is(":checked");
                    var email = $('#customer-email').val();
                    var dataPrepared = {
                        payment_method: paymentMethod,
                        email: email,
                        billing_address_data: (!isBillingSameShipping) ? billingAddressData : null,
                    };

                    $.ajax({
                        showLoader: true,
                        url: url.build('paymentwall/index/getpaymentwallwidget/'),
                        data: {
                            data: dataPrepared
                        },
                        type: "POST",
                        dataType: 'json'
                    }).done(function (response) {
                        $(this).attr('disabled', true)
                        window.location.href = response
                    });
                }

            }
        });
    }
);
