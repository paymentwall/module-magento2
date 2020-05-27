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
            execute: function (localMethod, newWindow = false) {
                var redirectUrl = this.generateRedirectUrlByLocalMethod(localMethod, newWindow);
                window.location.replace(redirectUrl);
            },
            generateRedirectUrlByLocalMethod: function (localMethod, newWindow = false) {
                var redirectUrl = new URL(url.build(this.redirectUrl));
                var params = new URLSearchParams(redirectUrl.search);
                params.set('local_method', localMethod);
                if (newWindow) {
                    params.set('new_window', newWindow)
                }
                redirectUrl.search = '?' + params.toString();
                redirectUrl = redirectUrl.href;
                return redirectUrl;
            }
        };
    }
);
