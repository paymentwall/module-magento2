define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/checkout-data',
        'Magento_Customer/js/customer-data'
    ],
    function (Component,checkout,storage) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/form'
            },

            initObservable: function () {
                this._super()
                    .observe([]);
                return this;
            },

            getCode: function () {
                return 'paymentwall';
            },

            getStoreUrl: function () {
                return _.map(window.checkoutConfig.storeUrl, function (value, key) {
                    return value;
                });
            },

            getSubmitUrl: function () {
                return this.getStoreUrl() + 'paymentwall/index/index';
            },

            getHtml: function () {
                return "Payment via Paymentwall";
            },

            getValidatedEmailValue: function () {
                return checkout.getValidatedEmailValue();
            },

            getBillingValue: function () {
                return JSON.stringify(checkout.getBillingAddressFromData());
            },

            placeOrder: function () {
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
                storage.set('cart',cart);
                document.getElementById("frmPaymentwall").submit();
            },
        });
    }
);