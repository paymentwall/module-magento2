define(
    [
        'mage/url'
    ],
    function (url) {
        'use strict';

        return {
            redirectUrl: window.checkoutConfig.payment.paymentwall.defaultWidgetPageUrl,

            /**
             * Provide redirect to page
             */
            execute: function () {
                fullScreenLoader.startLoader();

                storage.get(this.redirectUrl)
                .success(function (response) {
                    var url = response.url;

                    $('.page-wrapper').append('<div class="payment-wall-modal"><iframe src="' + url + '" frameborder="0" width="100%" height="650px"></iframe></div>');
                    $('body').addClass('_has-modal');
                })
                .fail(function () {
                    window.location.replace(url.build(this.redirectUrl));
                });

            }
        };
    }
);
