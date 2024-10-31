(function ($) {
    "use strict";
    jQuery(function ($) {

        let totalOrders = 0;
        let batchSize = 0;
        let offset = 0;
        let recoverFrom = '';
        let recoverTo = '';
        let waitTime = 10;
        let processedCount = 0;


        $('.route-datepicker').datepicker({
            dateFormat : 'yy-mm-dd'
        });

        $('.woocommerce-save-button').html('Sync Orders');

        let spinner = new jQuerySpinner({
            parentId: 'wpwrap'
        });

        // Function to process a batch of orders
        function processBatch() {
            $.ajax({
                url: routeapp_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'routeapp_process_orders_batch',
                    routeapp_order_recover_from: recoverFrom,
                    routeapp_order_recover_to: recoverTo,
                    batchSize: batchSize,
                    offset: offset,
                    nonce: routeapp_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        processedCount += response.data.processed;
                        offset += batchSize;
                        $('#message').html('<p> Orders processed: ' + totalOrders + '/' + processedCount + '</p>');

                        if (processedCount < totalOrders) {
                            setTimeout(processBatch, waitTime * 1000); // Delay before the next batch
                        } else {
                            $('#message').html('<p> All orders have been processed.' + totalOrders + '/' + processedCount + '</p>');
                            spinner.hide();
                        }
                    } else {
                        $('#message').html('<p> ' + response.data.message + '</p>');
                        spinner.hide();
                    }
                },
                error: function (error) {
                    $('#message').html('<p> An error occurred while processing the orders. </p>');
                    spinner.hide();
                }
            });
        }

        $('.woocommerce-save-button').click(function (e) {
            e.preventDefault();
            spinner.show();

            $.ajax({
                url: routeapp_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'routeapp_save_orders',
                    routeapp_order_recover_from: $('#routeapp_order_recover_from').val(),
                    routeapp_order_recover_to: $('#routeapp_order_recover_to').val(),
                    routeapp_order_recover_reconcile_backend: $('#routeapp_order_recover_reconcile_backend').is(':checked') ? 1 : 0,
                    nonce: routeapp_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        totalOrders = response.data.orderCount;
                        batchSize = response.data.batchSize;
                        recoverFrom = response.data.recoverFrom;
                        recoverTo = response.data.recoverTo;
                        waitTime = response.data.waitTime;
                        processedCount = 0;
                        offset = 0;
                        
                        $('#message').html('<p> Orders processed: ' + totalOrders + '/' + processedCount + '</p>');

                        processBatch();
                    } else {
                        $('#message').html('<p> Failed to initialize order processing.</p>');
                        spinner.hide();
                    }
                },
                error: function (error) {
                    $('#message').html('<p> An error occurred while initiating order processing.</p>');
                    spinner.hide();
                }
            });
        });


    });
})(jQuery);
