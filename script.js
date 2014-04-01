jQuery(document).ready(function($) {
	var timer_update_billing_subs;
	var timer_update_shipping_subs;
	var mds_ajax_billing_suburb;
	var mds_ajax_shipping_suburb;
	var mds_ajax_update_price;

	function update_billing_subs() {
		if (mds_ajax_billing_suburb)
			mds_ajax_billing_suburb.abort();

		if (jQuery('#billing_town').val()==''){

			jQuery('#billing_suburb').empty();
			jQuery('#billing_suburb').append('<option value="">Select town first...</option>');
		} else {

			jQuery('#billing_suburb').empty();
			jQuery('#billing_suburb').append('<option value="">Loading...</option>');

			var town = jQuery('#billing_town').val();
			var type = 'billing_';

			var data = {
				action		: 'mds_collivery_generate_suburbs',
				security	: woocommerce_params.update_order_review_nonce,
				town		: town,
				type		: type,
			};

			mds_ajax_billing_suburb = jQuery.ajax({
				type : 'POST',
				url : woocommerce_params.ajax_url,
				data : data,
				success : function(my_response) {
					jQuery('#billing_suburb').empty();
					jQuery('#billing_suburb').append( my_response );
				}
			});
		}
	}

	function update_shipping_subs() {
		if (mds_ajax_shipping_suburb)
			mds_ajax_shipping_suburb.abort();

		if (jQuery('#shipping_town').val()==''){
			jQuery('#shipping_suburb').empty();
			jQuery('#shipping_suburb').append('<option value="">---Please select a town first---</option>');
		} else {
			jQuery('#shipping_suburb').empty();
			jQuery('#shipping_suburb').append('<option value="">Loading...</option>');

			var town = jQuery('#shipping_town').val();
			var type = 'shipping_';

			var data = {
				action		: 'mds_collivery_generate_suburbs',
				security	: woocommerce_params.update_order_review_nonce,
				town		: town,
				type		: type,
			};

			mds_ajax_shipping_suburb = jQuery.ajax({
				type : 'POST',
				url : woocommerce_params.ajax_url,
				data : data,
				success : function(my_response) {
					jQuery('#shipping_suburb').empty();
					jQuery('#shipping_suburb').append( my_response );
				}
			});
		}
	}

	jQuery('select#billing_town').live('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_billing_subs);
			timer_update_billing_subs = setTimeout(update_billing_subs, '1000');
		}
	});

	jQuery('select#shipping_town').live('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_shipping_subs);
			timer_update_shipping_subs = setTimeout(update_shipping_subs, '1000');
		}
	});

	jQuery('select#billing_town').live('change', function() {
		clearTimeout(timer_update_billing_subs);
		update_billing_subs();
	});

	jQuery('select#shipping_town').live('change', function() {
		clearTimeout(timer_update_shipping_subs);
		update_shipping_subs();
	});
	
	update_billing_subs();
	update_shipping_subs();
});