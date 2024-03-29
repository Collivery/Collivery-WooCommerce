var colliveryFieldsValues = {};
var saProvinces = ['EC','FS','GP','KZN','LP','MP','NC','NW','WC']
var overrideChange = false;
var billingInternational = getIsInternational('billing');
var shippingInternational = getIsInternational('shipping');
var colliveryClass = 'colliveryfield';
var isProvinceChange = false;
var isBuildingPage = true;
jQuery(document)
    .ready(function () {
        // Allow for some narrowing of scope in our css
      jQuery('.woocommerce-checkout').addClass(colliveryClass)
      jQuery('.woocommerce-edit-address').addClass(colliveryClass)

        var select2fields;
        if (jQuery(':hidden#billing_city').length > 0) {
            select2fields = {
                location_type: 'Select your location type',
            };
            var el = jQuery('#billing_town_city_search,#shipping_town_city_search');
            el.select2({
                minimumInputLength: 3,
                ajax: {
                    url: woocommerce_params.ajax_url,
                    type: "POST",
                    data: function (params) {
                        var query = {
                            action: 'mds_collivery_generate_' + 'town_city_search',
                            security: woocommerce_params.update_order_review_nonce,
                            prefix: '_',
                            search_text: params.term
                        }
                        return query;
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    }
                }
            });


        } else {
            select2fields = {
                city: 'Select your city/town',
                suburb: 'Select your city/town',
                location_type: 'Select your location type',
            };
        }


        jQuery.each(select2fields, function (field, placeholder) {
            var el = jQuery('#billing_' + field + ', #shipping_' + field);
            try {
                el.select2({
                    placeholder: placeholder,
                    width: '100%'
                });
            } catch (err) {
                console.log(err)
            }
        });

        if (!jQuery(':hidden#billing_city').length > 0) {
            var ajaxUpdates = [
                {fromField: 'billing_state', field: 'billing_city', prefix: 'towns', db_prefix: 'billing'},
                {fromField: 'billing_city', field: 'billing_suburb', prefix: 'suburbs', db_prefix: 'billing'},
                {fromField: 'billing_city', field: 'billing_city_int', prefix: 'suburbs', db_prefix: 'billing'},
                {fromField: 'shipping_state', field: 'shipping_city', prefix: 'towns', db_prefix: 'shipping'},
                {fromField: 'shipping_city', field: 'shipping_suburb', prefix: 'suburbs', db_prefix: 'shipping'}
            ];

            jQuery.each(ajaxUpdates, function (index, row) {
                var parentEl = jQuery('#' + row.fromField);
                parentEl.on('keydown', function (e) {
                    var keyCode = e.keyCode || e.which;
                    if (keyCode !== 9) {
                        updateSelect(row.fromField, row.field, row.prefix, row.db_prefix);
                    }
                });

                parentEl.on('change', function () {
                    updateSelect(row.fromField, row.field, row.prefix, row.db_prefix);
                });

                cacheValue(row.fromField, parentEl.val());
            });

        }

        var internationalUpdates = [{type: "billing"}, {type: "shipping"}];

        var styling = document.createElement('style');
        styling.innerHTML = '.' + colliveryClass + ' .active { display: block !important; }' +
            '.' + colliveryClass + ' .inactive { display: none !important; }';
        document.body.appendChild(styling);


        jQuery.each(internationalUpdates, function (index, row) {

            updateInternational(row.type);
            var countryEl = jQuery('#' + row.type + "_country");
            countryEl.on('keydown', function (e) {
                var keyCode = e.keyCode || e.which;
                if (keyCode !== 9) {
                    updateInternational(row.type);
                }
            });

            countryEl.on('change', function () {
                updateInternational(row.type);
            });

            var cityEl = jQuery('#' + row.type + "_city_int");
            cityEl.on('keydown', function (e) {
                var keyCode = e.keyCode || e.which;
                if (keyCode !== 9) {
                    updateFields(row.type);
                }
            });

            cityEl.on('change', function () {
                updateFields(row.type);
            });
        });


        //Function to append values for international shipments BEFORE submit
        jQuery('form[name="checkout"]').submit(function (event) {
            if (jQuery('#billing_country').val() != 'ZA' || (jQuery('#shipping_country').val() != 'ZA' && jQuery('#ship-to-different-address input:checked').length > 0)) {
                event.preventDefault();
                var enteredCityBilling = jQuery('#billing_city_int').val();
                var enteredCityShipping = jQuery('#shipping_city_int').val();

                jQuery('#billing_city').append('<option selected >' + enteredCityBilling + '</option>');
                jQuery('#billing_suburb').append('<option selected >' + enteredCityBilling + '</option>');

                if (enteredCityBilling !== enteredCityShipping) {
                    jQuery('#shipping_city').append('<option selected >' + enteredCityShipping + '</option>');
                    jQuery('#shipping_suburb').append('<option selected >' + enteredCityShipping + '</option>');
                }
            }
        })

        var citySearchComboBilling = jQuery('#billing_town_city_search');
        var citySearchComboShipping = jQuery('#shipping_town_city_search');


        if (citySearchComboBilling.length > 0) {
            citySearchComboBilling.change(function (e) {
                var suburb_id = jQuery(e.target).val();
                getProvince('billing_state', '_', suburb_id);
                getTown('billing_city', '_', suburb_id);
                getTown('billing_city_int', '_', suburb_id);
                getSuburb('billing_suburb', '_', suburb_id);

            });
        }
        if (citySearchComboShipping.length > 0) {
            citySearchComboShipping.change(function (e) {
                var suburb_id = jQuery(e.target).val();
                getProvince('shipping_state', '_', suburb_id);
                getTown('shipping_city', '_', suburb_id);
                getTown('shipping_city_int', '_', suburb_id);
                getSuburb('shipping_suburb', '_', suburb_id);

            });
        }

    });

jQuery(window).on('load', function(){
  isBuildingPage = false;
});

function updateInternational(type) {
    var fromEl = jQuery('#' + type + "_country");
    var fromSelect2 = fromEl.data('select2');

    var isChange = fromEl.val() !== '' && fromEl.val() != colliveryFieldsValues[type + "_country"];

    if (isChange) {
        cacheValue(type + "_country", fromEl.val());

        if (fromSelect2)
            fromSelect2.close();

        removeInlineStyling(type);


        if (fromEl.val() == "ZA") {
            // Enable MDS Settings
            setInternational(type, true);

            jQuery('#' + type + '_city .removal').remove();
            jQuery('#' + type + '_suburb .removal').remove();


          activate(jQuery('#' + type + '_suburb_field')[0]);
          activate(jQuery('#' + type + '_city_field')[0]);
          deActivate(jQuery('#' + type + '_city_int_field')[0]);

        } else {
            // Disable MDS Settings
          setInternational(type, false);
          deActivate(jQuery('#' + type + '_city_field')[0]);
          deActivate(jQuery('#' + type + '_suburb_field')[0]);
          deActivate(jQuery('#' + type + '_city_field')[0]);
          activate(jQuery('#' + type + '_city_int_field')[0]);

        }
    }
}
function activate(el){
  if(el){
    el.classList.add('active');
    el.classList.remove('inactive');
  }
}

function deActivate(el){
  if(el){
    el.classList.remove('active');
    el.classList.add('inactive');
  }
}

function removeInlineStyling(type) {
  removeStyle(jQuery('#' + type + '_city_field')[0]);
  removeStyle(jQuery('#' + type + '_suburb_field')[0]);
  removeStyle(jQuery('#' + type + '_city_int_field')[0]);
}
function removeStyle(el){
  if(el){
    el.style.display = "";
  }
}

function updateFields(db_prefix) {
    if (!getIsInternational(db_prefix)) {
        // If TRUE, then Not International
      jQuery('#' + db_prefix + '_city_int')[0].value = jQuery('#' + db_prefix + "_city").val();
    } else {
        // If False, Is International
      jQuery('#' + db_prefix + '_city')[0].value = jQuery('#' + db_prefix + "_city_int").val();
      jQuery('#' + db_prefix + '_suburb')[0].value = jQuery('#' + db_prefix + "_city_int").val();
    }
}

function updateSelect(fromField, field, prefix, db_prefix) {
    if(isBuildingPage){
      return ;
    }
    var fromEl = jQuery('#' + fromField),
        el = jQuery('#' + field),
        fromSelect2 = fromEl.data('select2'),
        isChange = (fromEl.val() !== '' && fromEl.val() != colliveryFieldsValues[fromField])|| saProvinces.includes(fromEl.val()),
        fromText = jQuery('#' + fromField + ' option:selected' ).text();

    if (overrideChange) {
        overrideChange = false;
        isChange = true;
    }
    if (isProvinceChange) {
        isProvinceChange = false;
        return;
    }
    // Ensure we clear the town from cache in case we are changing province
    // Else if we come back to this province and this town - the suburbs won't update
    if (fromField.indexOf('state') != -1 && isChange) {
        cacheValue(field, '');

        // Clear the previously selected suburbs if the province changes
        resetSelect(jQuery('#' + db_prefix + '_suburb'), '<option selected="selected" value="">First Select Town...</option>');
    }

    // The width of the `el` is collapsed if a parent is overlapping it.
    // See https://github.com/select2/select2/pull/5502
    if (fromSelect2) { // May be null if element is hidden
        fromSelect2.close();
    }

    // Check that the value is not empty and has changed from the previous value
    // Only if that is true is there any point in querying for new results
    if (isChange) {
        if (prefix == "suburbs") {
            // Update INT City for the sake of Requirement.
            updateFields(db_prefix);
        }
        cacheValue(fromField, fromEl.val());
        return ajax = jQuery.ajax({
            type: 'POST',
            async: true,
            timeout: 0,
            url: woocommerce_params.ajax_url,
            data: {
                action: 'mds_collivery_generate_' + prefix,
                security: woocommerce_params.update_order_review_nonce,
                parentValue: fromEl.val(),
                db_prefix: db_prefix + '_',
                parentItem:fromText
            },
            success: function (response) {
                resetSelect(el, response);
                if (prefix === 'towns') {
                    updateSelect(field, db_prefix + '_suburb', 'suburbs', db_prefix);
                }
            },
            error: function () {
                resetSelect(el, '<option selected="selected" value="">Error retrieving data from server. Please refresh the page and try again</option>');
            },
            beforeSend: function () {
                resetSelect(el, '<option selected="selected" value="">Loading...</option>');
            }
        });
    }
}

function resetSelect(el, html) {
    el.select2('destroy');
    el.html(html);
    try {
        // use `width:'100%'` so that the width of `el` matches the wrapper element
        el.select2({
            width: '100%',
        });
    } catch (err) {
        console.log(err)
    }
}

function setSelected(el, val) {
    try {
        el.val(val).trigger('change');
    } catch (err) {
        console.log(err)
    }
}

function setValue(el, val) {
    try {
        el.val(val);
    } catch (err) {
        console.log(err)
    }
}

function cacheValue(key, val) {
    colliveryFieldsValues[key] = val;
}

function getSuburb(field, db_prefix, suburb_id) {
    var el = jQuery('input[name="'+field+'"]');
    return ajax = jQuery.ajax({
        type: 'POST',
        url: woocommerce_params.ajax_url,
        data: {
            action: 'mds_collivery_generate_suburb',
            security: woocommerce_params.update_order_review_nonce,
            suburb_id: suburb_id,
            db_prefix: db_prefix + '_',
        },
        success: function (response) {
            setValue(el, response);
        },
        error: function () {
            setValue(el, '');
        },
        beforeSend: function () {
        }
    });
}

function getTown(field, db_prefix, suburb_id) {
    var el = jQuery('input[name="'+field+'"]');
    return ajax = jQuery.ajax({
        type: 'POST',
        url: woocommerce_params.ajax_url,
        data: {
            action: 'mds_collivery_generate_town',
            security: woocommerce_params.update_order_review_nonce,
            suburb_id: suburb_id,
            db_prefix: db_prefix + '_',
        },
        success: function (response) {
            setValue(el, response);
        },
        error: function () {
            setValue(el, '');
        },
        beforeSend: function () {
        }
    });
}

function getProvince(field, db_prefix, suburb_id) {
    var el = jQuery('input[name="'+field+'"]');
    return ajax = jQuery.ajax({
        type: 'POST',
        url: woocommerce_params.ajax_url,
        data: {
            action: 'mds_collivery_generate_province',
            security: woocommerce_params.update_order_review_nonce,
            suburb_id: suburb_id,
            db_prefix: db_prefix + '_',
        },
        success: function (response) {
            isProvinceChange = true;
            setValue(el, response)
            setSelected(el, response);
        },
        error: function () {
            setValue(el, '');
        },
        beforeSend: function () {

        }
    });
}

function getIsInternational(prefix) {
  return jQuery(`#${prefix}_country`).val() != "ZA";
}

function setInternational(prefix, value) {
  if(prefix === 'billing'){
    billingInternational = value;
  }else {
    shippingInternational = value;
  }
}
