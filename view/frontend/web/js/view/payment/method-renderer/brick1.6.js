/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/customer-email-validator',
        'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator/credit-card-type',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'brickOrderjs'
    ],
    function (ko, $, Component, ccvalidator, customerEmailValidator, creditCardTypes, quote, shippingService, brickOrderjs ) {

        return Component.extend({
            defaults: {
                template: 'Paymentwall_Paymentwall/payment/ccform16'
            },
            brick_transaction_id: '',
            card_type: '',
            plan_id: '',
            card_last_four: '',
            brick_risk: '',
            is_under_review: false,
            is_captured: false,

            initialize: function () {
                this._super();
                this.initBrick();
            },

            initBrick: function() {
                let initBrick = setInterval( () => {
                    let doc = $('#iframe-brick-container');
                    if (doc.length ) {
                        doc.css('width', '100%');
                        doc.css('min-height', '400px');

                        clearInterval(initBrick);
                    }
                }, 500);
            },

            placeOrder: function (data, event) {
                let chargeResult = window.brickCheckout

                this.card_type = chargeResult.card_type;
                this.card_last_four = chargeResult.card_last_four;
                this.brick_transaction_id = chargeResult.brick_transaction_id;
                this.brick_risk = chargeResult.brick_risk;
                this.is_under_review = chargeResult.is_under_review;
                this.is_captured = chargeResult.is_captured;

                var self = this;
                this._super();
            },

            styleTransactionSuccess()
            {
                $('.transaction-code-block span').css('line-height', '20px');
            },

            getCode: function () {
                return 'brick';
            },

            isActive: function () {
                return true;
            },

            hasVerification: function () {
                return true;
            },

            getData: function () {
                return {
                    'method': this.getCode(),
                    "additional_data": {
                        "brick_transaction_id": this.brick_transaction_id,
                        "card_type": this.card_type,
                        "card_last_four": this.card_last_four,
                        "brick_risk": this.brick_risk,
                        "is_captured": this.is_captured,
                        "is_under_review": this.is_under_review,
                    }
                };
            },

            context: function() {
                return this;
            },

        });
    }
);
