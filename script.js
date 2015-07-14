jQuery(document).ready(function($) {
    var timer_update_billing_towns;
    var timer_update_shipping_towns;
    var timer_update_billing_subs;
    var timer_update_shipping_subs;
    var timer_update_billing_location_type;
    var timer_update_shipping_location_type;
    var mds_ajax_billing_province;
    var mds_ajax_shipping_province;
    var mds_ajax_location_type;
    var mds_ajax_billing_state;
    var mds_ajax_shipping_state;

    add_location_type_to_session();

    function update_billing_towns() {
        if (mds_ajax_billing_province) {
            mds_ajax_billing_province.abort();
        }
        
        // if no province is selected, empty the town selection (state override)
        jQuery('#billing_state').empty();
        if (jQuery('#billing_province').val() === '') {
            jQuery('#billing_state').append('<option value="">Select a province first...</option>');
        }
        // load towns from billing selection
        else {
            jQuery('#billing_state').append('<option value="">Loading...</option>');
            
            var province = jQuery('#billing_province').val();
            var type = 'billing_';
            
            var data = {
                action: 'mds_collivery_generate_towns',
                security: woocommerce_params.update_order_review_nonce,
                province: province,
                type: type
            };
            
            mds_ajax_billing_province = jQuery.ajax({
                type: 'POST',
                url: woocommerce_params.ajax_url,
                data: data,
                success: function(my_response) {
                    jQuery('#billing_state').empty();
                    jQuery('#billing_state').append('<option value="">Select town...</option>');
                    jQuery('#billing_state').append(my_response);
                }
            });
        }
    }
    
    function update_shipping_towns() {
        if (mds_ajax_shipping_province) {
            mds_ajax_shipping_province.abort();
        }
        
        // if no province is selected, empty the town selection (state override)
        jQuery('#shipping_state').empty();
        if (jQuery('#shipping_province').val() === '') {
            jQuery('#shipping_state').append('<option value="">Select a province first...</option>');
        }
        // load towns from shipping selection
        else {
            jQuery('#shipping_state').append('<option value="">Loading...</option>');
            
            var province = jQuery('#shipping_province').val();
            var type = 'shipping_';
            
            var data = {
                action: 'mds_collivery_generate_towns',
                security: woocommerce_params.update_order_review_nonce,
                province: province,
                type: type
            };
            
            mds_ajax_shipping_province = jQuery.ajax({
                type: 'POST',
                url: woocommerce_params.ajax_url,
                data: data,
                success: function(my_response) {
                    jQuery('#shipping_state').empty();
                    jQuery('#shipping_state').append('<option value="">Select town...</option>');
                    jQuery('#shipping_state').append(my_response);
                    
                    // if a value is pre-selected, trigger update_billing_surbs as well
                    if ( jQuery('#shipping_state').val() !== '' ) {
                        update_shipping_subs();
                    }
                }
            });
        }
    }

	function update_billing_subs() {
		if (mds_ajax_billing_state)
			mds_ajax_billing_state.abort();

		if (jQuery('#billing_state').val() === '') {

			jQuery('#billing_city').empty();
			jQuery('#billing_city').append('<option value="">Select a city first...</option>');
		} else {

			jQuery('#billing_city').empty();
			jQuery('#billing_city').append('<option value="">Loading...</option>');

			var town = jQuery('#billing_state').val();
			var type = 'billing_';

			var data = {
				action: 'mds_collivery_generate_suburbs',
				security: woocommerce_params.update_order_review_nonce,
				town: town,
				type: type,
			};

			mds_ajax_billing_state = jQuery.ajax({
				type: 'POST',
				url: woocommerce_params.ajax_url,
				data: data,
				success: function(my_response) {
					jQuery('#billing_city').empty();
                    jQuery('#billing_city').append('<option value="">Select suburb...</option>');
					jQuery('#billing_city').append(my_response);
				}
			});
		}
	}

	function update_shipping_subs() {

		if (mds_ajax_shipping_state)
			mds_ajax_shipping_state.abort();

		if (jQuery('#shipping_state').val() === '') {
			jQuery('#shipping_city').empty();
			jQuery('#shipping_city').append('<option value="">---Please select a city first---</option>');
		} else {
			jQuery('#shipping_city').empty();
			jQuery('#shipping_city').append('<option value="">Loading...</option>');

			var town = jQuery('#shipping_state').val();
			var type = 'shipping_';

			var data = {
				action: 'mds_collivery_generate_suburbs',
				security: woocommerce_params.update_order_review_nonce,
				town: town,
				type: type,
			};

			mds_ajax_shipping_state = jQuery.ajax({
				type: 'POST',
				url: woocommerce_params.ajax_url,
				data: data,
				success: function(my_response) {
					jQuery('#shipping_city').empty();
                    jQuery('#shipping_city').append('<option value="">Select suburb...</option>');
					jQuery('#shipping_city').append(my_response);
				}
			});
		}
	}
    
    jQuery('#billing_province').live('keydown', function(e) {
        var keyCode = e.keyCode || e.which;
        if (keyCode !== 9) {
            clearTimeout(timer_update_billing_towns);
            timer_update_billing_towns = setTimeout(update_billing_towns, 1000);
        }
    });
    
    jQuery('#shipping_province').live('keydown', function(e) {
        var keyCode = e.keyCode || e.which;
        if (keyCode !== 9) {
            clearTimeout(timer_update_shipping_towns);
            timer_update_shipping_towns = setTimeout(update_shipping_towns, 1000);
        }
    });

	function add_location_type_to_session() {
		if (mds_ajax_location_type) {
			mds_ajax_billing_state.abort();
		}

		var shipping_location_type = jQuery('#shipping_location_type').val();
		var billing_location_type = jQuery('#billing_location_type').val();

		if($("input[name='ship-to-different-address']").prop("checked")) {
			var use_location_type = 'shipping_location_type';
		} else {
			var use_location_type = 'billing_location_type';
		}

		var data = {
			'action': 'add_location_type_to_session',
			'use_location_type': use_location_type,
			'shipping_location_type': shipping_location_type,
			'billing_location_type': billing_location_type
		};

		mds_ajax_location_type = jQuery.ajax({
			url: woocommerce_params.ajax_url,
			data: data,
			type: 'post'
		});
	}

	jQuery('#shipping_location_type').on('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_shipping_location_type);
			timer_update_billing_subs = setTimeout(add_location_type_to_session, '2000');
		}
	});

	jQuery('#billing_location_type').on('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_billing_location_type);
			timer_update_billing_subs = setTimeout(add_location_type_to_session, '2000');
		}
	});

	jQuery('#billing_state').on('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode !== 9) {
			clearTimeout(timer_update_billing_subs);
			timer_update_billing_subs = setTimeout(update_billing_subs, '2000');
		}
	});

	jQuery('#shipping_state').on('keydown', function(e) {
		var keyCode = e.keyCode || e.which;

		if (keyCode != 9) {
			clearTimeout(timer_update_shipping_subs);
			timer_update_shipping_subs = setTimeout(update_shipping_subs, '2000');
		}
	});
    
    jQuery('#billing_province').live('change', function() {
        clearTimeout(timer_update_billing_towns);
        update_billing_towns();
    });
    
    jQuery('#shipping_province').live('change', function() {
        clearTimeout(timer_update_shipping_towns);
        update_shipping_towns();
    });

	jQuery('#shipping_location_type').on('change', function() {
		clearTimeout(timer_update_shipping_location_type);
		add_location_type_to_session();
	});

	jQuery('#billing_location_type').on('change', function() {
		clearTimeout(timer_update_billing_location_type);
		add_location_type_to_session();
	});

	jQuery('#billing_state').on('change', function() {
		clearTimeout(timer_update_billing_subs);
		update_billing_subs();
	});

	jQuery('#shipping_state').on('change', function() {
		clearTimeout(timer_update_shipping_subs);
		update_shipping_subs();
	});

    update_billing_towns();
    update_shipping_towns();
	update_billing_subs();
	update_shipping_subs();
});
