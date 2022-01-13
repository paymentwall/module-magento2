window.processBrickPlaceOrder = function (paymentResult) {
    window.brickCheckout = paymentResult
    document.getElementById('brick-place-order').click();
}

