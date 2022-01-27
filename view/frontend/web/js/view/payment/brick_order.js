window.processBrickPlaceOrder = function (paymentResult) {
    window.brickCheckout = paymentResult
    document.getElementById('brick-place-order').click();
}

window.preValidateBrickCheckout = function () {
    // if place Order button is not disabled:
    if (!document.getElementById('brick-place-order').classList.contains("disabled")) {
        document.getElementById('brick-place-order').click();
    }
}

