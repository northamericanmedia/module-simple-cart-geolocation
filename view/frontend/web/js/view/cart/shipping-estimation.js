define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'uiRegistry',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/checkout-data-resolver'
], function (
    $,
    quote,
    registry,
    checkoutData,
    checkoutDataResolver
) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this._super();

                registry.async('checkoutProvider')(function (checkoutProvider) {
                    let address = checkoutProvider.get('shippingAddress');
                    if (
                        !quote.isVirtual() &&
                        !address.postcode &&
                        !quote.shippingAddress().postcode &&
                        window.checkoutConfig.geolocationUrl
                    ) {
                        checkoutDataResolver.resolveEstimationAddress();
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    $.ajax({
                                        url: window.checkoutConfig.geolocationUrl,
                                        method: 'POST',
                                        data: {
                                            form_key: window.checkoutConfig.formKey,
                                            latitude: parseFloat(position.coords.latitude),
                                            longitude: parseFloat(position.coords.longitude)
                                        },
                                        success: function (data) {
                                            let shippingAddress = {};
                                            shippingAddress.country_id = data.country_id || '';
                                            shippingAddress.region = data.region || '';
                                            shippingAddress.region_id = data.region_id || '';
                                            shippingAddress.postcode = data.postcode || '';
                                            checkoutProvider.set(
                                                'shippingAddress',
                                                $.extend({}, checkoutProvider.get('shippingAddress'), shippingAddress)
                                            );
                                        },
                                        error: function (error) {
                                            console.error('Geolocation lookup failed', error);
                                        }
                                    });
                                },
                                (error) => {
                                    switch (error.code) {
                                        case error.PERMISSION_DENIED:
                                            console.log("User denied the request for Geolocation.");
                                            break;
                                        case error.POSITION_UNAVAILABLE:
                                            console.log("Location information is unavailable.");
                                            break;
                                        case error.TIMEOUT:
                                            console.log("The request to get user location timed out.");
                                            break;
                                        case error.UNKNOWN_ERROR:
                                            console.log("An unknown error occurred.");
                                            break;
                                    }
                                }
                            );
                        }
                    }
                });

                return this;
            },
        });
    };
});
