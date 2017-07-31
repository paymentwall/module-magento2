/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Paymentwall_Paymentwall/js/action/redirect-to-widget',
        'Magento_Checkout/js/model/quote',
    ],
    function (Component, redirectToWidget, quote) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/form',
                transactionResult: ''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function() {
                return 'paymentwall';
            },

            getData: function() {
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
                redirectToWidget.execute();
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
        });
    }
);