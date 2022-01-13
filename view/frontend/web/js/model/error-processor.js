define(
    [
        'mage/url',
        'Magento_Ui/js/model/messageList',
        'Magento_Ui/js/modal/alert',
        'Magento_Ui/js/modal/confirm',
    ],
    function (url, globalMessageList, malert, mconfirm) {
        'use strict';

        return {
            process: function (response, messageContainer) {
                messageContainer = messageContainer || globalMessageList;
                if (response.status == 401) {
                    window.location.replace(url.build('customer/account/login/'));
                } else {
                    var error = JSON.parse(response.responseText || response);
                    if (error.message && error.message.indexOf('#brick_under_review#') >= 0) {
                        window.location.replace(url.build('paymentwall/onepage/review'));
                    } else {
                        malert({
                            content: error.message || error.error
                        });
                    }
                }
            }
        };
    }
);
