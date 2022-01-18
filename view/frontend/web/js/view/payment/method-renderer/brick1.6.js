/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/customer-email-validator',
        'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator/credit-card-type',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'brickOrderjs'
    ],
    function (ko, $, Component, ccvalidator, additionalValidators, customerEmailValidator, creditCardTypes, quote, shippingService, brickOrderjs ) {

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

            validate: function () {
                return true;
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
                if (this.validate() &&
                    additionalValidators.validate() &&
                    ko.observable(quote.billingAddress()) != null
                ) {
                    let brickIframeContent = $("#iframe-brick-container").contents()
                    // need to enable brick form for users:
                    brickIframeContent.find("#brick-form-shield").css("display", "none");
                    brickIframeContent.find("#brick-payments-container").css("opacity", "1");
                    brickIframeContent.find("#validate-brick-before-process").slideUp()

                    let chargeResult = window.brickCheckout
                    if (chargeResult) {
                        this.card_type = chargeResult.card_type;
                        this.card_last_four = chargeResult.card_last_four;
                        this.brick_transaction_id = chargeResult.brick_transaction_id;
                        this.brick_risk = chargeResult.brick_risk;
                        this.is_under_review = chargeResult.is_under_review;
                        this.is_captured = chargeResult.is_captured;

                        this._super();
                    }
                    return false;
                }
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
