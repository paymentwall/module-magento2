define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/checkout-data',
        'Magento_Customer/js/customer-data',
        'Magento_CheckoutAgreements/js/model/agreement-validator',
        'Magento_Checkout/js/model/customer-email-validator'
    ],
    function (Component,checkout,customerData,agreementValidator,customerEmailValidator) {
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
                if (!customerEmailValidator.validate()) {
                    return false;
                }
                var cart1 = customerData.get('cart')();
                cart1['items'] = [];
                cart1['summary_count'] = 0;
                customerData.set('cart',cart1);
                if (agreementValidator.validate())
                    document.getElementById("frmPaymentwall").submit();
            },
        });
    }
);