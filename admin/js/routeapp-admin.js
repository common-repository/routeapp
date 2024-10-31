(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	function setRouteCookie(name, value, expire)
	{
		let date = new Date();
		date.setTime(date.getTime() + (expire * 24 * 360000));
		document.cookie = name + "=" + value + "; expires=" + date.toGMTString()+"; path=/";
	}

	function getRouteCookie(cname)
	{
		let name = cname + "=";
		let allCookieArray = document.cookie.split(';');
		for(let i=0; i<allCookieArray.length; i++)
		{
			let temp = allCookieArray[i].trim();
			if (temp.indexOf(name)==0)
				return temp.substring(name.length,temp.length);
		}
		return "";
	}

	$(document).ready(function () {
		if (!getRouteCookie('route_installed') && document.getElementById("route_counter") !== null) {

			setRouteCookie('route_installed', 1, 360);

			let intervalId = setInterval(function () {
				let seconds = document.getElementById("route_counter").innerText;
				if(seconds > 0) {
					document.getElementById("route_counter").innerText = seconds - 1;
				}else{
					clearInterval(intervalId)
					window.location = $("#route_counter").data('redirect-src');
				}
			},1000)
		}else{
			$('#route_counter').parents('.notice-success').hide()
		}

		$(document).on('click', '#add-route-fee', function (e) {
			e.preventDefault();

			var data = {
				action: 'routeapp_add_admin_fee',
				security: woocommerce_admin_meta_boxes.order_item_nonce,
				order_id: woocommerce_admin_meta_boxes.post_id
			};

			$.post(
				woocommerce_admin_meta_boxes.ajax_url,
				data,
				function( response )
				{
					console.log(response);
					// Errors
					if( !response.success )
					{
						// No data came back, maybe a security error
						if( !response.data )
							console.log( 'AJAX ERROR: no response' );
						// Error from PHP
						else
							console.log( 'Response Error: ' + response.data.error );
					}
					// Success
					else {
            $('.calculate-action').click();
            console.log( 'Response Success: ' + response.data );
          }
				}
			);

		});

		$(document).on('click', '#remove-route-fee', function (e) {
			e.preventDefault();

			var data = {
				action: 'routeapp_remove_admin_fee',
				security: woocommerce_admin_meta_boxes.order_item_nonce,
				order_id: woocommerce_admin_meta_boxes.post_id
			};

			$.post(
				woocommerce_admin_meta_boxes.ajax_url,
				data,
				function( response )
				{
					console.log(response);
					// Errors
					if( !response.success )
					{
						// No data came back, maybe a security error
						if( !response.data )
							console.log( 'AJAX ERROR: no response' );
						// Error from PHP
						else
							console.log( 'Response Error: ' + response.data.error );
					}
					// Success
					else {
            $('.calculate-action').click();
            console.log( 'Response Success: ' + response.data );
          }
				}
			);

		});

	})

})( jQuery );
