jQuery(document).ready(function () {
    jQuery.each(jQuery('.shortenedSelect option'), function(key, optionElement) {
        var curText = jQuery(optionElement).text();
        jQuery(this).attr('title', curText);

        var lengthToShortenTo = Math.round(parseInt(jQuery(this).parent('select').css('max-width'), 10) / 7.3);

        if (curText.length > lengthToShortenTo) {
            jQuery(this).text('... ' + curText.substring((curText.length - lengthToShortenTo), curText.length));
        }
    });

    jQuery('.shortenedSelect').change(function() {
        jQuery(this).attr('title', (jQuery(this).find('option:eq('+jQuery(this).get(0).selectedIndex +')').attr('title')));
    });

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

    jQuery('#delivery_town').change(function () {
        console.log('fetching suburbs');
        var data = {
            action: 'suburbs_admin',
            town: jQuery("#delivery_town option:selected").val()
        };

        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post(ajaxurl, data, function (result) {
            jQuery('#delivery_suburb').html(result);
        });
    });

    // Used to show the collection company address form
    jQuery('.which_address').change(function () {
        var div = jQuery(this).attr('rel');
        if (jQuery('#api_quote').data('validator') !== undefined) {
            jQuery('#api_quote').data('validator').resetForm();
            jQuery('#api_quote').data('validator', null);
        }

        if (jQuery(this).val() == 'default') {
            jQuery("#which_"+div+"_hide_default").css('display', 'block');
            jQuery("#which_"+div+"_hide_saved").css('display', 'none');
        }
        else {
            jQuery("#which_"+div+"_hide_default").css('display', 'none');
            jQuery("#which_"+div+"_hide_saved").css('display', 'block');
        }
    });

    jQuery('#collivery_to').change(function () {
        var data = {
            action: 'contacts_admin',
            address_id: jQuery("#collivery_to option:selected").val()
        };

        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post(ajaxurl, data, function (result) {
            jQuery('#contact_to').html(result);
        });
    });

    jQuery('#collivery_from').change(function () {
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
                error: function() {
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
            error: function() {
                jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
            },
            beforeSend: function () {
                jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
            }
        });
    });
});

jQuery(window).load(function () {
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

function form_validate() {
    var which_collection_address = jQuery("input:radio[name=which_collection_address]:checked").val();
    var which_delivery_address = jQuery("input:radio[name=which_delivery_address]:checked").val();

    // Validate all except chosen from address
    if (which_collection_address == 'default' && which_delivery_address == 'default') {
        var validator = jQuery("#api_quote").validate({
            ignore: ":hidden:not(select)",
            rules: {
                delivery_street: "required",
                delivery_full_name: "required",
                delivery_town: "required",
                delivery_suburb: "required",
                delivery_location_type: "required",
                service: "required",
                delivery_cellphone: {
                    required: true,
                    minlength: 10
                },
                delivery_email: {
                    required: true,
                    email: true
                },
                collection_town: "required",
                collection_suburb: "required",
                collection_location_type: "required",
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
    } else if (which_collection_address == 'default' && which_delivery_address != 'default') {
        var validator = jQuery("#api_quote").validate({
            ignore: ":hidden:not(select)",
            rules: {
                collivery_to: "required",
                contact_to: "required",
                service: "required",
                collection_town: "required",
                collection_suburb: "required",
                collection_location_type: "required",
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
    } else if (which_collection_address != 'default' && which_delivery_address == 'default') {
        var validator = jQuery("#api_quote").validate({
            ignore: ":hidden:not(select)",
            rules: {
                collivery_from: "required",
                contact_from: "required",
                delivery_town: "required",
                delivery_suburb: "required",
                delivery_location_type: "required",
                delivery_street: "required",
                delivery_full_name: "required",
                service: "required",
                delivery_cellphone: {
                    required: true,
                    minlength: 10
                },
                delivery_email: {
                    required: true,
                    email: true
                }
            }
        });
    } else {
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

    if(validator.numberOfInvalids() == 0) {
        return true;
    } else {
        return false;
    }
}

// remove parcel
function remove_parcel(id) {
    jQuery('#item' + id).remove();
}

jQuery(function(){
    var downloadSetting = jQuery("#woocommerce_mds_collivery_downloadLogs");
    if(downloadSetting.length > 0) {
        var url = downloadSetting.attr('placeholder');
        var downloadButton = document.createElement('a');
        downloadButton.setAttribute('href', url);
        downloadButton.setAttribute('class', 'button-primary');
        downloadButton.innerHTML = 'Download Error Logs';
        downloadButton.setAttribute('id', 'woocommerce_mds_collivery_downloadLogs');
        downloadSetting.replaceWith(downloadButton);

        var clearCacheButton = document.createElement('a');
        clearCacheButton.innerHTML = 'Clear Cache';
        clearCacheButton.setAttribute('class', 'button-primary');
        clearCacheButton.setAttribute('id', 'woocommerce_mds_collivery_clearCache');
        clearCacheButton.setAttribute('href', url.replace('mds_download_log_files', 'mds_clear_cache_files'));
        jQuery(clearCacheButton).css('margin-right', '10px').insertBefore('#woocommerce_mds_collivery_downloadLogs');
    }

    (function(jQuery){
        jQuery.fn.hideParent = function(parent, hide){
            if(hide === true)
                this.parents(parent).fadeOut('fast');
            else
                this.parents(parent).fadeIn('fast');

            return this;
        };

    })(jQuery);

    var shippingMode = jQuery('select[name="woocommerce_mds_collivery_method_free"]');
    var percentageDiscount = jQuery('input[name="woocommerce_mds_collivery_shipping_discount_percentage"]');
    var freeDeliveryBlacklist = jQuery('input[name="woocommerce_mds_collivery_free_delivery_blacklist"]');
    var freeDeliveryItems = jQuery('select[data-type="free-delivery-item"],input[data-type="free-delivery-item"]');
    var freeMinTotal = jQuery('input[name="woocommerce_mds_collivery_free_min_total"]');

    shippingMode.change(function () {
        var mode = shippingMode.val();
        switch (mode) {
            case 'no':
                freeDeliveryItems.hideParent('tr', true);
                percentageDiscount.hideParent('tr', true);
                freeDeliveryBlacklist.hideParent('tr', true);
                freeMinTotal.hideParent('tr', true);
                break;
            case 'yes':
                percentageDiscount.hideParent('tr', true);
                freeDeliveryItems.hideParent('tr', false);
                freeDeliveryBlacklist.hideParent('tr', false);
                freeMinTotal.hideParent('tr', false);
                break;
            case 'discount':
                freeDeliveryItems.hideParent('tr', true);
                percentageDiscount.hideParent('tr', false);
                freeDeliveryBlacklist.hideParent('tr', false);
                freeMinTotal.hideParent('tr', false);
                break;
            default:
        }
    });

    shippingMode.change();
});
