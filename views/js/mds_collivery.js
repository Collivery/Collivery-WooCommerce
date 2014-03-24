jQuery.noConflict();
jQuery(document).ready(function()
{

    // Used for setting elements heights the same as the biggest of the bunch
    jQuery('select').each(function()
    {
        jQuery(this).chosen();
    });


    // --------------------------------------------------------------------

    jQuery('#collection_town').change(function()
    {
        var text = jQuery("#collection_town option:selected").text();
        jQuery.ajax(
                {
                    url: base_url + "index.php?option=com_virtuemart&view=mds&task=get_suburbs&town_name=" + text, success: function(result)
                    {
                        jQuery("#collection_suburb_chzn").remove();
                        jQuery("#collection_suburb").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                        jQuery('#collection_suburb').html(result);
                        jQuery('#collection_suburb').chosen();
                    }
                });
    });

    // --------------------------------------------------------------------

    jQuery('#destination_town').change(function()
    {
        var text = jQuery("#destination_town option:selected").text();
        jQuery.ajax(
                {
                    url: base_url + "index.php?option=com_virtuemart&view=mds&task=get_suburbs&town_name=" + text, success: function(result)
                    {
                        jQuery("#destination_suburb_chzn").remove();
                        jQuery("#destination_suburb").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                        jQuery('#destination_suburb').html(result);
                        jQuery('#destination_suburb').chosen();
                    }
                });
    });

    // --------------------------------------------------------------------

    // Used to show the collection company input if the address is not private
    jQuery('.collection_which_company').click(function()
    {
        if (jQuery(this).val() != 'private')
        {
            jQuery("#collection_hide_company").css('display', 'block');
        }
        else
        {
            jQuery("#collection_hide_company").css('display', 'none');
        }
        set_parallel();
    });

    // --------------------------------------------------------------------

    // Used to show the destination company input if the address is not private
    jQuery('.destination_which_company').click(function()
    {
        if (jQuery(this).val() != 'private')
        {
            jQuery("#destination_hide_company").css('display', 'block');
        }
        else
        {
            jQuery("#destination_hide_company").css('display', 'none');
        }
        set_parallel();
    });

    // --------------------------------------------------------------------

    // Used to show the collection company address form
    jQuery('.which_collection_address').click(function()
    {
        if (jQuery('#api_quote').data('validator') != undefined)
        {
            jQuery('#api_quote').data('validator').resetForm();
            jQuery('#api_quote').data('validator', null);
        }
        
        if (jQuery(this).val() == 'default')
        {
            jQuery("#which_collection_hide_default").css('display', 'block');
            jQuery("#which_collection_hide_saved").css('display', 'none');
        }
        else
        {
            jQuery("#which_collection_hide_default").css('display', 'none');
            jQuery("#which_collection_hide_saved").css('display', 'block');
        }
        reset_parallel();
        set_parallel();
    });

    // --------------------------------------------------------------------

    // Used to show the collection company address form
    jQuery('.which_destination_address').click(function()
    {
        if (jQuery('#api_quote').data('validator') != undefined)
        {
            jQuery('#api_quote').data('validator').resetForm();
            jQuery('#api_quote').data('validator', null);
        }
        
        if (jQuery(this).val() == 'default')
        {
            jQuery("#which_destination_hide_default").css('display', 'block');
            jQuery("#which_destination_hide_saved").css('display', 'none');
        }
        else
        {
            jQuery("#which_destination_hide_default").css('display', 'none');
            jQuery("#which_destination_hide_saved").css('display', 'block');
        }
        reset_parallel();
        set_parallel();
    });

    // --------------------------------------------------------------------

    jQuery('#collivery_to').change(function()
    {
        var address_id = jQuery("#collivery_to option:selected").val();
        jQuery.ajax(
                {
                    url: base_url + "index.php?option=com_virtuemart&view=mds&task=get_contacts&address_id=" + address_id, success: function(result)
                    {
                        jQuery("#contact_to_chzn").remove();
                        jQuery("#contact_to").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                        jQuery('#contact_to').html(result);
                        jQuery('#contact_to').chosen();
                    }
                });
    });

    // --------------------------------------------------------------------

    jQuery('#collivery_from').change(function()
    {
        var address_id = jQuery("#collivery_from option:selected").val();
        jQuery.ajax(
                {
                    url: base_url + "index.php?option=com_virtuemart&view=mds&task=get_contacts&address_id=" + address_id, success: function(result)
                    {
                        jQuery("#contact_from_chzn").remove();
                        jQuery("#contact_from").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                        jQuery('#contact_from').html(result);
                        jQuery('#contact_from').chosen();
                    }
                });
    });

    // --------------------------------------------------------------------

    // Used to show the collection company input if the address is not private
    jQuery('#get_quote').click(function(event)
    {
        event.preventDefault();
        if(form_validate())
        {
            var datastring = jQuery("#api_quote").serialize();
            jQuery.ajax({
                type: "POST",
                url: base_url + "index.php?option=com_virtuemart&view=mds&task=get_quote",
                data: datastring,
                success: function(data) {
                    jQuery("#api_results").html(data);
                },
                error: function(data) {
                    jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
                },
                beforeSend: function() {
                    jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
                },
            });            
        }
    });

    // --------------------------------------------------------------------

    // Used to process the delivery and then change order status
    jQuery('#accept_quote').click(function(event)
    {
        event.preventDefault();
        if(form_validate())
        {
            var datastring = jQuery("#api_quote").serialize();
            jQuery.ajax({
                type: "POST",
                url: base_url + "index.php?option=com_virtuemart&view=mds&task=accept_quote",
                data: datastring,
                success: function(data)
                {
                    if (data.match(/\bredirect/))
                    {
                        var redirect = data.split("|");
                        var virtuemart_order_id = redirect[1];
                        jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Delivery has been processed and sent through to MDS Collivery. You will be redirect to your order in 5 seconds.</div>');
                        setTimeout(function() {
                            window.location.href = base_url + "index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=" + virtuemart_order_id;
                        }, 5000);
                    }
                    else
                    {
                        jQuery("#api_results").html(data);
                    }
                },
                error: function(data) {
                    jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
                },
                beforeSend: function() {
                    jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
                },
            });
        }        
    });

    // --------------------------------------------------------------------

    // Used to update all the towns, location types and services
    jQuery('#update').click(function(event)
    {
        event.preventDefault();
        jQuery.ajax({
            type: "GET",
            url: base_url + "index.php?option=com_virtuemart&view=mds&task=update",
            success: function(data) {
                jQuery("#api_results").html(data);
            },
            error: function(data) {
                jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Error: Check if your session has expired and if so log back in.</div>');
            },
            beforeSend: function() {
                jQuery("#api_results").html('<div style="font-size: 15px;margin:15px 0 0 39px;color:black;">Loading.....</div>');
            },
        });
    });

    // --------------------------------------------------------------------

    // Used for setting elements heights the same as the biggest of the bunch
    jQuery('.parallel').each(function()
    {
        var tallest_elem = 0;
        jQuery(this).find('.parallel_target').each(function(i)
        {
            tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
        });

        jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
    });

    // --------------------------------------------------------------------

    // Used for styling labels so they all line up correctly
    jQuery("form").each(function()
    {
        var w = 0;
        jQuery("label", this).each(function()
        {
            if (jQuery(this).width() > w)
            {
                w = jQuery(this).width();
            }
        });

        if (w > 0)
        {
            var percent_width = (w + 5) + "px";
            jQuery("label", this).each(function()
            {
                jQuery(this).css('width', percent_width);
                jQuery(this).css('display', 'inline-block');
            });
        }
    });

    // --------------------------------------------------------------------

});

jQuery(window).load(function()
{
//    jQuery('select').trigger('chosen:updated');
    // Create item fields
    jQuery('#create_fields').click(function()
    {
        var item = jQuery(".itemized_package_node tr:last");
        var num = jQuery('.package_items tr:last').index() + 2;

        // change all the input attributes with next number up
        item.clone().find("input").each(function() {
            jQuery(this).attr({
                'id': function(_, id) {
                    return 'packages[' + num + '][' + id + ']'
                },
                'name': function(_, name) {
                    return  'parcels[' + num + '][' + name + ']'
                }
            });
        }).end().appendTo('.package_items');

        // Give the tr an id for removing later
        jQuery('.package_items tr:last').each(function() {
            jQuery(this).attr('id', 'item' + num);
        });

        // Increase parcel count
        jQuery('#parcel_count').val(num);

        // remove the tr
        jQuery('.package_items tr:last').find("a").click(function(event) {
            event.preventDefault();
            jQuery('#item' + num).remove();
            jQuery('#parcel_count').val(jQuery('#parcel_count').val() - 1); // Minus a parcel
            reset_parallel();
            set_parallel();
        });

        set_parallel();
    });

    // --------------------------------------------------------------------

    // Used for setting elements heights the same as the biggest of the bunch
    jQuery('.parallel').each(function()
    {
        var tallest_elem = 0;
        jQuery(this).find('.parallel_target').each(function(i)
        {
            tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
        });

        jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
    });

    // --------------------------------------------------------------------

    jQuery('#datetimepicker4').datetimepicker({
        onGenerate: function(ct) {
            jQuery(this).find('.xdsoft_date.xdsoft_weekend').addClass('xdsoft_disabled');
        },
        weekends: ['01.01.2014', '02.01.2014', '03.01.2014', '04.01.2014', '05.01.2014', '06.01.2014'],
        dateFormat: "yy-mm-dd",
        minDate: 0,
        minTime: 0,
        maxTime: '15:01'
    });

    jQuery('#open').click(function() {
        jQuery('#datetimepicker4').datetimepicker('show');
    });
    jQuery('#close').click(function() {
        jQuery('#datetimepicker4').datetimepicker('hide');
    });
    jQuery('#reset').click(function() {
        jQuery('#datetimepicker4').datetimepicker('reset');
    });
});

// --------------------------------------------------------------------    

// Function here to set class parallel height evenly
function set_parallel()
{
    jQuery('.parallel').each(function()
    {
        var tallest_elem = 0;
        jQuery(this).find('.parallel_target').each(function(i)
        {
            tallest_elem = (jQuery(this).height() > tallest_elem) ? jQuery(this).height() : tallest_elem;
        });

        jQuery(this).find('.parallel_target').css({'min-height': tallest_elem});
    });
}

// --------------------------------------------------------------------    

// Function here to set class parallel height evenly
function reset_parallel()
{
    jQuery('.parallel').each(function()
    {
        jQuery(this).find('.parallel_target').css({'min-height': 0});
    });
}

// --------------------------------------------------------------------    

function form_validate()
{
    var which_collection_address = jQuery("input:radio[name=which_collection_address]:checked").val();
    var which_destination_address = jQuery("input:radio[name=which_destination_address]:checked").val();
        
    // Validate all except chosen from address
    if (which_collection_address == 'default' && which_destination_address == 'default')
    {
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
    else if (which_collection_address == 'default' && which_destination_address != 'default')
    {
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
    else if (which_collection_address != 'default' && which_destination_address == 'default')
    {
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
    else
    {
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
    
    if (validator.numberOfInvalids() == 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}

// --------------------------------------------------------------------    

// remove parcel
function remove_parcel(id)
{
    jQuery('#item'+id).remove();
}

// --------------------------------------------------------------------