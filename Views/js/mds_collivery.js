jQuery(document).ready(function () {

	jQuery('#collection_town').change(function () {
		var data = {
			action: 'suburbs_admin',
			town: jQuery("#collection_town option:selected").val()
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function (result) {
			jQuery('#collection_suburb').html(result);
		});
	});

	jQuery('#destination_town').change(function () {
		var data = {
			action: 'suburbs_admin',
			town: jQuery("#destination_town option:selected").val()
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function (result) {
			jQuery('#destination_suburb').html(result);
		});
	});

	// Used to show the collection company input if the address is not private
	jQuery('.collection_which_company').click(function () {
		if (jQuery(this).val() != 'private') {
			jQuery("#collection_hide_company").css('display', 'block');
		}
		else {
			jQuery("#collection_hide_company").css('display', 'none');
		}
		set_parallel();
	});

	// Used to show the destination company input if the address is not private
	jQuery('.destination_which_company').click(function () {
		if (jQuery(this).val() != 'private') {
			jQuery("#destination_hide_company").css('display', 'block');
		}
		else {
			jQuery("#destination_hide_company").css('display', 'none');
		}
		set_parallel();
	});

	// Used to show the collection company address form
	jQuery('.which_collection_address').click(function () {
		if (jQuery('#api_quote').data('validator') != undefined) {
			jQuery('#api_quote').data('validator').resetForm();
			jQuery('#api_quote').data('validator', null);
		}

		if (jQuery(this).val() == 'default') {
			jQuery("#which_collection_hide_default").css('display', 'block');
			jQuery("#which_collection_hide_saved").css('display', 'none');
		}
		else {
			jQuery("#which_collection_hide_default").css('display', 'none');
			jQuery("#which_collection_hide_saved").css('display', 'block');
		}
		reset_parallel();
		set_parallel();
	});

	// Used to show the collection company address form
	jQuery('.which_destination_address').click(function () {
		if (jQuery('#api_quote').data('validator') != undefined) {
			jQuery('#api_quote').data('validator').resetForm();
			jQuery('#api_quote').data('validator', null);
		}

		if (jQuery(this).val() == 'default') {
			jQuery("#which_destination_hide_default").css('display', 'block');
			jQuery("#which_destination_hide_saved").css('display', 'none');
		}
		else {
			jQuery("#which_destination_hide_default").css('display', 'none');
			jQuery("#which_destination_hide_saved").css('display', 'block');
		}
		reset_parallel();
		set_parallel();
	});

	jQuery('#collivery_to').click(function () {
		var data = {
			action: 'contacts_admin',
			address_id: jQuery("#collivery_to option:selected").val()
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function (result) {
			jQuery('#contact_to').html(result);
		});
	});

	jQuery('#collivery_from').click(function () {
		var data = {
			action: 'contacts_admin',
			address_id: jQuery("#collivery_from option:selected").val()
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function (result) {
			jQuery('#contact_from').html(result);
		});
	});

	// Used to show the collection company input if the address is not private
	jQuery('#get_quote').click(function (event) {
		event.preventDefault();
		if (form_validate()) {
			var datastring = jQuery("#api_quote").serialize();
			jQuery.ajax({
				type: "POST",
				url: ajaxurl,
				data: 'action=quote_admin&' + datastring,
				success: function (data) {
					jQuery("#api_results").html(data);
				},
				error: function (data) {
					jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
				},
				beforeSend: function () {
					jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
				}
			});
		}
	});

	// Used to process the delivery and then change order status
	jQuery('#accept_quote').click(function (event) {
		event.preventDefault();
		if (form_validate()) {
			var datastring = jQuery("#api_quote").serialize();
			jQuery.ajax({
				type: "POST",
				url: ajaxurl,
				data: 'action=accept_admin&' + datastring,
				success: function (data) {
					jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">' + data.message + '</div>');

					if (data.redirect == 1) {
						setTimeout(function () {
							window.location.href = jQuery("#api_quote").attr('action');
						}, 5000);
					}
				},
				error: function (data) {
					jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">There was an error with the ajax request, please refresh the page and try again and if the problem is not rectified please report the problem to integration@collivery.co.za</div>');
				},
				beforeSend: function () {
					jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
				}
			});
		}
	});

	// Used to update all the towns, location types and services
	jQuery('#update').click(function (event) {
		event.preventDefault();
		jQuery.ajax({
			type: "GET",
			url: base_url + "index.php?option=com_virtuemart&view=mds&task=update",
			success: function (data) {
				jQuery("#api_results").html(data);
			},
			error: function (data) {
				jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
			},
			beforeSend: function () {
				jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
			}
		});
	});

	// Used for setting elements heights the same as the biggest of the bunch
	jQuery('.parallel').each(function () {
		var tallest_elem = 0;
		jQuery(this).find('.parallel_target').each(function (i) {
			tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
		});

		jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
	});

	// Used for styling labels so they all line up correctly
	jQuery("form").each(function () {
		var w = 0;
		jQuery("label", this).each(function () {
			if (jQuery(this).width() > w) {
				w = jQuery(this).width();
			}
		});

		if (w > 0) {
			var percent_width = (w + 5) + "px";
			jQuery("label", this).each(function () {
				jQuery(this).css('width', percent_width);
				jQuery(this).css('display', 'inline-block');
			});
		}
	});
});

jQuery(window).load(function () {
	// Create item fields
	jQuery('#create_fields').click(function () {
		var item = jQuery(".itemized_package_node tr:last");
		var num = jQuery('.package_items tr:last').index() + 2;

		// change all the input attributes with next number up
		item.clone().find("input").each(function () {
			jQuery(this).attr({
				'id': function (_, id) {
					return 'packages[' + num + '][' + id + ']'
				},
				'name': function (_, name) {
					return 'parcels[' + num + '][' + name + ']'
				}
			});
		}).end().appendTo('.package_items');

		// Give the tr an id for removing later
		jQuery('.package_items tr:last').each(function () {
			jQuery(this).attr('id', 'item' + num);
		});

		// Increase parcel count
		jQuery('#parcel_count').val(num);

		// remove the tr
		jQuery('.package_items tr:last').find("a").click(function (event) {
			event.preventDefault();
			jQuery('#item' + num).remove();
			jQuery('#parcel_count').val(jQuery('#parcel_count').val() - 1); // Minus a parcel
			reset_parallel();
			set_parallel();
		});

		set_parallel();
	});

	// Used for setting elements heights the same as the biggest of the bunch
	jQuery('.parallel').each(function () {
		var tallest_elem = 0;
		jQuery(this).find('.parallel_target').each(function (i) {
			tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
		});

		jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
	});

	var logic = function(currentDateTime){
		var d = new Date();
		var n = d.getDay();
		if( currentDateTime.getDay() == n ) {
			this.setOptions({
				minDate: 0,
				format: "Y-m-d H:s",
				maxTime: '15:01',
				allowTimes:[
					'08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'
				],
				dayOfWeek:[
					"Mo", "Di", "Mi",
					"Do", "Fr",
				],
				minTime:0
			});
		} else {
			this.setOptions({
				minDate: 0,
				format: "Y-m-d H:s",
				maxTime: '15:01',
				allowTimes:[
					'08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'
				],
				dayOfWeek:[
					"Mo", "Di", "Mi",
					"Do", "Fr",
				],
				minTime:'8:00'
			});
		}
	};

	jQuery('#datetimepicker4').datetimepicker({
		onChangeDateTime:logic,
		onShow:logic
	});
});

// Function here to set class parallel height evenly
function set_parallel() {
	jQuery('.parallel').each(function () {
		var tallest_elem = 0;
		jQuery(this).find('.parallel_target').each(function (i) {
			tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
		});

		jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
	});
}

// Function here to set class parallel height evenly
function reset_parallel() {
	jQuery('.parallel').each(function () {
		jQuery(this).find('.parallel_target').css({'min-height': 0});
	});
}

function form_validate() {
	var which_collection_address = jQuery("input:radio[name=which_collection_address]:checked").val();
	var which_destination_address = jQuery("input:radio[name=which_destination_address]:checked").val();

	// Validate all except chosen from address
	if (which_collection_address == 'default' && which_destination_address == 'default') {
		var validator = jQuery("#api_quote").validate({
			ignore: ":hidden:not(select)",
			rules: {
				destination_street: "required",
				destination_full_name: "required",
				service: "required",
				destination_cellphone: {
					required: true,
					minlength: 10
				},
				destination_email: {
					required: true,
					email: true
				},
				collection_street: "required",
				collection_full_name: "required",
				collection_cellphone: {
					required: true,
					minlength: 10
				},
				collection_email: {
					required: true,
					email: true
				}
			}
		});
	}
	else if (which_collection_address == 'default' && which_destination_address != 'default') {
		var validator = jQuery("#api_quote").validate({
			ignore: ":hidden:not(select)",
			rules: {
				collivery_to: "required",
				contact_to: "required",
				service: "required",
				collection_street: "required",
				collection_full_name: "required",
				collection_cellphone: {
					required: true,
					minlength: 10
				},
				collection_email: {
					required: true,
					email: true
				}
			}
		});
	}
	else if (which_collection_address != 'default' && which_destination_address == 'default') {
		var validator = jQuery("#api_quote").validate({
			ignore: ":hidden:not(select)",
			rules: {
				collivery_from: "required",
				contact_from: "required",
				destination_street: "required",
				destination_full_name: "required",
				service: "required",
				destination_cellphone: {
					required: true,
					minlength: 10
				},
				destination_email: {
					required: true,
					email: true
				}
			}
		});
	}
	else {
		var validator = jQuery("#api_quote").validate({
			ignore: ":hidden:not(select)",
			rules: {
				service: "required",
				collivery_to: "required",
				contact_to: "required",
				collivery_from: "required",
				contact_from: "required"
			}
		});
	}

	validator.form();

	if (validator.numberOfInvalids() == 0) {
		return true;
	}
	else {
		return false;
	}
}

// remove parcel
function remove_parcel(id) {
	jQuery('#item' + id).remove();
}

jQuery(function(){

	(function($){
		$.fn.hideParent = function(parent, hide){
			if(hide === true)
				this.parents(parent).fadeOut('fast');
			else
				this.parents(parent).fadeIn('fast');

			this.css({border:'1px solid #FFFF00'});
			return this;
		};

	})(jQuery);

	var shippingMode = jQuery('select[name="woocommerce_mds_collivery_method_free"]');
	var percentageDiscount = jQuery('input[name="woocommerce_mds_collivery_shipping_discount_percentage"]');

	shippingMode.change(function(){
		var mode = shippingMode.val();
		switch(mode){
			case 'no':
			case 'yes':
				percentageDiscount.hideParent('tr', true);
				break;
			case 'discount':
				percentageDiscount.hideParent('tr', false);
				break;
			default:
		}
	});

	shippingMode.change();
});