jQuery(document).ready(function($) {
    var timer_update_billing_subs;
    var timer_update_shipping_subs;
    var mds_ajax_billing_suburb;
    var mds_ajax_shipping_suburb;
    var mds_ajax_update_price;

    function update_billing_subs() {
	if (mds_ajax_billing_suburb)
	    mds_ajax_billing_suburb.abort();

	if (jQuery('#billing_town').val() == '') {

	    jQuery('#billing_suburb').empty();
	    jQuery('#billing_suburb').append('<option value="">Select town first...</option>');
	} else {

	    jQuery('#billing_suburb').empty();
	    jQuery('#billing_suburb').append('<option value="">Loading...</option>');

	    var town = jQuery('#billing_town').val();
	    var type = 'billing_';

	    var data = {
		action: 'mds_collivery_generate_suburbs',
		security: woocommerce_params.update_order_review_nonce,
		town: town,
		type: type,
	    };

	    mds_ajax_billing_suburb = jQuery.ajax({
		type: 'POST',
		url: woocommerce_params.ajax_url,
		data: data,
		success: function(my_response) {
		    jQuery('#billing_suburb').empty();
		    jQuery('#billing_suburb').append(my_response);
		}
	    });
	}
    }

    function update_shipping_subs() {
	if (mds_ajax_shipping_suburb)
	    mds_ajax_shipping_suburb.abort();

	if (jQuery('#shipping_town').val() == '') {
	    jQuery('#shipping_suburb').empty();
	    jQuery('#shipping_suburb').append('<option value="">---Please select a town first---</option>');
	} else {
	    jQuery('#shipping_suburb').empty();
	    jQuery('#shipping_suburb').append('<option value="">Loading...</option>');

	    var town = jQuery('#shipping_town').val();
	    var type = 'shipping_';

	    var data = {
		action: 'mds_collivery_generate_suburbs',
		security: woocommerce_params.update_order_review_nonce,
		town: town,
		type: type,
	    };

	    mds_ajax_shipping_suburb = jQuery.ajax({
		type: 'POST',
		url: woocommerce_params.ajax_url,
		data: data,
		success: function(my_response) {
		    jQuery('#shipping_suburb').empty();
		    jQuery('#shipping_suburb').append(my_response);
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

jQuery(window).load(function()
{
    // This function is used to display shipping prices for logged in users.
    // Seems to be a bug in woocommerce that does not display the prices unless you change the city.

    var shipping_methods = [];
    jQuery('select.shipping_method, input[name^=shipping_method][type=radio]:checked, input[name^=shipping_method][type=hidden]').each(function(index, input) {
	shipping_methods[ jQuery(this).data('index') ] = jQuery(this).val();
    });

    var payment_method = jQuery('#order_review input[name=payment_method]:checked').val();
    var country = jQuery('#billing_country').val();
    var state = jQuery('#billing_state').val();
    var postcode = jQuery('input#billing_postcode').val();
    var city = jQuery('input#billing_city').val();
    var address = jQuery('input#billing_address_1').val();
    var address_2 = jQuery('input#billing_address_2').val();

    if (jQuery('#ship-to-different-address input').is(':checked') || jQuery('#ship-to-different-address input').size() == 0) {
	var s_country = jQuery('#shipping_country').val();
	var s_state = jQuery('#shipping_state').val();
	var s_postcode = jQuery('input#shipping_postcode').val();
	var s_city = jQuery('input#shipping_city').val();
	var s_address = jQuery('input#shipping_address_1').val();
	var s_address_2 = jQuery('input#shipping_address_2').val();
    } else {
	var s_country = country;
	var s_state = state;
	var s_postcode = postcode;
	var s_city = city;
	var s_address = address;
	var s_address_2 = address_2;
    }

    jQuery('#order_methods, #order_review').block({message: null, overlayCSS: {background: '#fff url(' + wc_checkout_params.ajax_loader_url + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6}});

    var data = {
	action: 'woocommerce_update_order_review',
	security: wc_checkout_params.update_order_review_nonce,
	shipping_method: shipping_methods,
	payment_method: payment_method,
	country: country,
	state: state,
	postcode: postcode,
	city: city,
	address: address,
	address_2: address_2,
	s_country: s_country,
	s_state: s_state,
	s_postcode: s_postcode,
	s_city: s_city,
	s_address: s_address,
	s_address_2: s_address_2,
	post_data: jQuery('form.checkout').serialize()
    };

    xhr = jQuery.ajax({
	type: 'POST',
	url: wc_checkout_params.ajax_url,
	data: data,
	success: function(response) {
	    if (response) {
		var order_output = jQuery(jQuery.parseHTML(response.trim()));
		jQuery('#order_review').html(order_output.html());
		jQuery('body').trigger('updated_checkout');
	    }
	}
    });
});