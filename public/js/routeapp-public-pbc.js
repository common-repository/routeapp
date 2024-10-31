(function ($) {
    "use strict";


    jQuery(function ($) {
        const UPDATED_CART_TOTALS_EVENT = 'updated_cart_totals';
        const UPDATED_CHECKOUT_EVENT = 'updated_checkout';
        const UPDATE_CHECKOUT_EVENT = 'update_checkout';
        const CHECKED_SHIPPING_METHOD = '#shipping_method li .shipping_method:checked';
        const ROUTE_WIDGET_ID = '#RouteWidget';
        const PROTECTION_COOKIE = '.routeapp-checkbox-cookie';

        const RouteConfig = {
            env: '.routeapp-env',
            ajax_url: '.routeapp-ajax-url',
            store_domain: '.routeapp-store-domain',
            store_name: '.routeapp-store-name',
            currency: '.routeapp-currency',
            merchant_id: '.routeapp-merchant-id',
            subtotal: '.routeapp-subtotal',
            app_endpoint_widget_check: 'woo_check_widget',
            app_endpoint_widget_update: 'woo_get_ajax_data',
            is_cart_page: window.location.pathname === "/cart/",
            invalid_shipping_method: '.routeapp-invalid-shipping-method',
            checkbox: $(PROTECTION_COOKIE).length ? parseCheckboxValue() : Route.Coverage.ActiveByDefault,
        };

        let Protect = window.Route.Protection;

        function parseCheckboxValue() {
            return $(PROTECTION_COOKIE).val()==='Route.Coverage.ActiveByDefault' ? Route.Coverage.ActiveByDefault : Route.Coverage.InactiveByDefault;
        }

        function getRouteappAjaxUrl() {
            return $(RouteConfig.ajax_url).length ?
                $(RouteConfig.ajax_url).val() : (
                    typeof window.wc_add_to_cart_params != "undefined" ?
                        window.wc_add_to_cart_params.ajax_url :
                        window.wc_routeapp_ajaxurl
                );
        }

        function triggerCheckoutUpdate() {
            $("body").trigger(UPDATE_CHECKOUT_EVENT);
        }

        function triggerCartUpdate() {
            $($(document.body)
                .find('[name="update_cart"]'))
                .prop('disabled', false)
                .trigger('click');
        }

        function updateFee() {
            let checkbox = RouteConfig.checkbox == Route.Coverage.ActiveByDefault;
            $.ajax({
                type: "POST",
                url: getRouteappAjaxUrl(),
                data: {
                    "action": RouteConfig.app_endpoint_widget_update,
                    "checkbox": checkbox
                },
                success: function () {
                    RouteConfig.is_cart_page ? triggerCartUpdate() : triggerCheckoutUpdate();
                }
            });
        }

        function renderWidget(subtotal) {
            if (document.getElementsByClassName("route-div").length > 1){
                let elems = document.getElementsByClassName("route-div");
                let elementsSize = elems.length - 1;
                for (let idx = 0; idx < elementsSize; idx++) {
                    elems[0].remove();
                }
            }
            let environment = Route.Environment.Production;
            if ($(RouteConfig.env).val()!== 'Route.Environment.Production') {
                environment = Route.Environment.Stage;
            }
            Protect.render({
                storeDomain: $(RouteConfig.store_domain).val(),
                subtotal: subtotal,
                currency: $(RouteConfig.currency).val(),
                environment: environment,
                status: RouteConfig.checkbox,
                merchantId: $(RouteConfig.merchant_id).val(),
                storeName: $(RouteConfig.store_name).val(),
                app: {
                  name: "@route/woocommerce-widget",
                  version: "1.0.0", 
                  platformId: "woocommerce",
                },
            });
        }

        function checkWidgetShow() {
            let shipping_method = $(CHECKED_SHIPPING_METHOD).length ? $(CHECKED_SHIPPING_METHOD).val() : false;
            jQuery.ajax({
                type: "POST",
                url: getRouteappAjaxUrl(),
                data: {
                    "action": "get_route_checkout",
                    shipping_method
                },
                success: function(result) {
                    if (result['routeapp-subtotal']) {
                        $(ROUTE_WIDGET_ID).show();
                        renderWidget(result['routeapp-subtotal']);
                    } else {
                        $(ROUTE_WIDGET_ID).hide();
                    }
                }
            });
        }

        //listeners
        $(document.body).on( UPDATED_CART_TOTALS_EVENT, checkWidgetShow);
        $(document.body).on( UPDATED_CHECKOUT_EVENT, checkWidgetShow);

        if (!$(RouteConfig.invalid_shipping_method).length) {
            renderWidget($(RouteConfig.subtotal).val());
            Protect.on('status_change', (event) => {
                RouteConfig.checkbox = event.to===1 ? Route.Coverage.ActiveByDefault : Route.Coverage.InactiveByDefault;
                updateFee();
            });
        }
    });
})(jQuery);
