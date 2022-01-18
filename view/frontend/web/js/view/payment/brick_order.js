window.processBrickPlaceOrder = function (paymentResult) {
    window.brickCheckout = paymentResult
    document.getElementById('brick-place-order').click();
}

window.preValidateBrickCheckout = function () {
    document.getElementById('brick-place-order').click();
}

