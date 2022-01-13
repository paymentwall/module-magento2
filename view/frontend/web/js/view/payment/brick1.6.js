/*browser:true*/
/*global define*/
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
                type: 'brick',
                component: 'Paymentwall_Paymentwall/js/view/payment/method-renderer/brick1.6'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
