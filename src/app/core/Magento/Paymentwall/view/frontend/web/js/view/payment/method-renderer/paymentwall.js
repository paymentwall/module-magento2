define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Magento_Paymentwall/payment/form'
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
                return JSON.parse(localStorage['mage-cache-storage'])['checkout-data']['validatedEmailValue'];
            },

            placeOrder: function () {
                jQuery("#frmPaymentwall").submit();
            },
        });
    }
);