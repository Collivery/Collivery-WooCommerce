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
    });

    function updateSelect(fromField, field, prefix, db_prefix) {
        var fromEl = jQuery('#' + fromField), el = jQuery('#' + field);
        if (fromEl.val() !== '') {
            return ajax = jQuery.ajax({
                type: 'POST',
                async: false,
                timeout: 3000,
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
        }
    }

    function resetSelect(el, html) {
        el.select2('destroy');
        el.html(html);
        try {
            el.select2();
        } catch(err) {
          console.log(err)
        }
    }
});
