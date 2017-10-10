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
                    var error = JSON.parse(response.responseText);
                    if (error.message.indexOf('###secure###') >= 0) {
                        var secureForm = error.message.replace('###secure###','');
                        mconfirm({
                            content: "Please verify 3D-secure to continue checkout",
                            actions: {
                                confirm: function () {
                                    var win = window.open("", "Brick: Verify 3D secure", "toolbar=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, width=1024, height=720");
                                    win.document.body.innerHTML += secureForm;
                                    win.document.forms[0].submit();
                                }
                            }
                        });
                    } else {
                        malert({
                            content: error.message
                        });
                    }
                }
            }
        };
    }
);
