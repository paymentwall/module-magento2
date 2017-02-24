define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/checkout-data'
    ],
    function (Component,checkout) {
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
                document.getElementById("frmPaymentwall").submit();
            },
        });
    }
);