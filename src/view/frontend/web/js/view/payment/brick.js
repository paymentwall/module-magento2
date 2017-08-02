define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'paymentwall_brick',
                component: 'Paymentwall_Paymentwall/js/view/payment/method-renderer/brick'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);