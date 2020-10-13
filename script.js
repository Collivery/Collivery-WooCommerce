var colliveryFieldsValues = {};
var overrideChange = false;

jQuery(document).ready(function () {
    var select2fields = {
        city: 'Select your city/town',
        suburb: 'Select your city/town',
        location_type: 'Select your location type'
    };

    jQuery.each(select2fields, function (field, placeholder) {
        var el = jQuery('#billing_' + field + ', #shipping_' + field);
        try {
            el.select2({
                placeholder: placeholder
            });
        } catch(err) {
            console.log(err)
        }
    });

    var ajaxUpdates = [
        {fromField: 'billing_state', field: 'billing_city', prefix: 'towns', db_prefix: 'billing'},
        {fromField: 'billing_city', field: 'billing_suburb', prefix: 'suburbs', db_prefix: 'billing'},
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

    function updateSelect(fromField, field, prefix, db_prefix) {
        var fromEl = jQuery('#' + fromField),
            el = jQuery('#' + field),
            fromSelect2 = fromEl.data('select2'),
            isChange = fromEl.val() !== '' && fromEl.val() != colliveryFieldsValues[fromField];

        if (overrideChange) {
            overrideChange = false;
            isChange = true;
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
            cacheValue(fromField, fromEl.val());
            return ajax = jQuery.ajax({
                type: 'POST',
                async: true,
                timeout: 10000,
                url: woocommerce_params.ajax_url,
                data: {
                    action: 'mds_collivery_generate_' + prefix,
                    security: woocommerce_params.update_order_review_nonce,
                    parentValue: fromEl.val(),
                    db_prefix: db_prefix + '_',
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
        } else if (prefix === 'towns'){
            if (jQuery('#billing_suburb').length > 0) {
                if (jQuery('#billing_suburb')[0].options.length > 0) {
                    if (jQuery('#billing_suburb')[0].options[0].innerText == "First select town/city") {
                        overrideChange = true;
                        updateSelect(field, db_prefix + '_suburb', 'suburbs', db_prefix);
                    }
                }   
            }
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
        } catch(err) {
          console.log(err)
        }
    }

  function cacheValue (key, val) {
    colliveryFieldsValues[key] = val;
  }
});
