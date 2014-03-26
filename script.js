jQuery(document).ready(function($) {
	var timer_update_billing_subs;
	var timer_update_shipping_subs;
	var mds_ajax_billing_suburb;
	var mds_ajax_shipping_suburb;

	function update_billing_subs() {
		if (mds_ajax_billing_suburb)
			mds_ajax_billing_suburb.abort();

		if (jQuery('#billing_state').val()==''){

			jQuery('#billing_city').empty();
			jQuery('#billing_city').append('<option value="">Select town first...</option>');
		} else {

			jQuery('#billing_city').empty();
			jQuery('#billing_city').append('<option value="">Loading...</option>');

			var town = jQuery('#billing_state').val();

			var data = {
				action		: 'mds_collivery_generate_suburbs',
				security	: woocommerce_params.update_order_review_nonce,
				town		: town,
			};

			mds_ajax_billing_suburb = jQuery.ajax({
				type : 'POST',
				url : woocommerce_params.ajax_url,
				data : data,
				success : function(my_response) {
					jQuery('#billing_city').empty();
					jQuery('#billing_city').append( my_response );
				}
			});
		}
	}

	function update_shipping_subs() {
		if (mds_ajax_shipping_suburb)
			mds_ajax_shipping_suburb.abort();

		if (jQuery('#shipping_state').val()==''){

			jQuery('#shipping_city').empty();
			jQuery('#shipping_city').append('<option value="">Select town first...</option>');
		} else {

			jQuery('#shipping_city').empty();
			jQuery('#shipping_city').append('<option value="">Loading...</option>');

			var town = jQuery('#shipping_state').val();

			var data = {
				action		: 'mds_collivery_generate_suburbs',
				security	: woocommerce_params.update_order_review_nonce,
				town		: town,
			};

			mds_ajax_shipping_suburb = jQuery.ajax({
				type : 'POST',
				url : woocommerce_params.ajax_url,
				data : data,
				success : function(my_response) {
					jQuery('#shipping_city').empty();
					jQuery('#shipping_city').append( my_response );
				}
			});
		}
	}

	jQuery('select#billing_state').live('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_billing_subs);
			timer_update_billing_subs = setTimeout(update_billing_subs, '1000');
		}
	});

	jQuery('select#shipping_state').live('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_shipping_subs);
			timer_update_shipping_subs = setTimeout(update_shipping_subs, '1000');
		}
	});

	jQuery('select#billing_state').live('change', function() {
		clearTimeout(timer_update_billing_subs);
		update_billing_subs();
	});

	jQuery('select#shipping_state').live('change', function() {
		clearTimeout(timer_update_shipping_subs);
		update_shipping_subs();
	});
});