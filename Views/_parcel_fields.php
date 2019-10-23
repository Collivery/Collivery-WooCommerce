<div id="parcel_fields" class="form-group">
    <table>
        <thead id="parcel_table_header">
            <tr>
                <th align="left">Length</th>
                <th align="left">Width</th>
                <th align="left">Height</th>
                <th align="left">Weight</th>
                <th align="left">Quantity</th>
                <?php if (isset($include_product_titles) && $include_product_titles):?>
                    <th align="left">Description</th>
                <?php endif; ?>
                <th align="left">&nbsp;</th>
            </tr>
        </thead>
        <tbody id="package_items">
            <?php
                $count = 1;
                if (isset($parcels) && !empty($parcels)) {
                    foreach ($parcels as $parcel) {
                        echo \MdsSupportingClasses\View::make('_parcel_row', compact('parcel', 'count', 'include_product_titles'));
                        ++$count;
                    }
                } else {
                    echo \MdsSupportingClasses\View::make('_parcel_row', compact('count', 'include_product_titles'));
                }
            ?>
        </tbody>
    </table>

    <table>
        <tr style="font-size: 14px; width:35%; text-align: center;" class="parcelTotals">
            <td colspan="2" style="text-align: left"><button class="addParcel btn btn-primary">Add Parcel</button></td>
            <td style="width:10%">Totals:</td>
            <td style="width:15%; font-weight: bold"><span class="totalWeight">0</span> kg</td>
            <td style="width:20%; font-weight: bold"><span class="totalVolWeight">0</span> vol kg</td>
            <td style="width:15%; font-weight: bold"><span class="totalQty">0</span></td>
            <td style="width:5%">&nbsp;</td>
        </tr>
    </table>
</div>

<script type="text/javascript">
    (function() {
        var inputParcelCount = document.getElementById('parcel_count'), addParcelButton = jQuery('.addParcel');

        addParcelButton.click(function(event) {
            event.preventDefault();
            addParcel();
        });

        if (inputParcelCount) {
            addParcelButton.closest("form").find('input[type=submit]').on('click',function(event) {
                if(Number(inputParcelCount.value) !== parcelQty()) {
                    event.preventDefault();
                }
            });

            inputParcelCount.addEventListener('change', function() { // Number of parcels keyup function
                var parcel_qty = parcelQty();
                if(parcel_qty > 0) {
                    if(Number(jQuery('input[name=parcel_count]').val()) < parcel_qty) { // if the number is less than the actual parcels
                        jQuery(this).effect("highlight", {}, 1000);
                        jQuery(this).val(parcel_qty); // highlight error
                    }
                } else {
                    createFields();
                }
            });
        }

        jQuery('#package_items tr').each(function() { // Go through each existing parcels
            bindDelete(jQuery(this).find("a"));
            bindQty(jQuery(this).find('input[rel="qty"]'));
        });

        function createFields()
        {
            addParcel();
        }

        function bindQty(qty)
        {
            qty.addClass('parcel_qty');
            qty.keydown(function(e) {
                var parcel_qty = parcelQty();

                if ( ! e.shiftKey && e.keyCode === 9) {
                    if( ! inputParcelCount || parcel_qty < Number(inputParcelCount.value)) {  // if the number is greater than the actual parcels
                        e.preventDefault();
                        createFields();
                    }
                }
            });

            qty.change(function(e) {
                var parcel_qty = parcelQty();
                var parcels = jQuery('#parcel_count');

                if (e.keyCode !== 9) {
                    var thisQty = jQuery(this).val();
                    if(thisQty > 0) {
                         if(parcel_qty > Number(parcels.val())) { // if the number is less than the actual parcels
                            parcels.effect("highlight", {}, 1000); // highlight error
                            qty.effect("highlight", {}, 1000); // highlight error
                            maxQty = parcels.val() - (parcel_qty - thisQty);
                            qty.val(maxQty > 0 ? maxQty : 1);
                        }
                    } else if (thisQty !== '') {
                        qty.effect("highlight", {}, 1000); // highlight error
                        jQuery(this).val(1);
                    }
                }
            });
        }

        function bindDelete(del)
        {
            var rel = jQuery(this).attr('rel');
            del.click(function(event) {
                event.preventDefault();

                if(jQuery('#package_items tr:visible').length > 1) {
                    var parcelRow = jQuery(event.target).closest('tr');
                    if (parcelRow.find("input[rel='id']").length === 0) {
                        parcelRow.remove();
                    } else {
                        var parcelIdField = parcelRow.find("input[rel='id']");
                        var parcelId = parcelIdField.val();
                        if (parcelRow.find('input').prop('disabled')) {
                            parcelRow.find('input').prop('disabled', false);
                            parcelRow.find('input[name="parcels['+ parcelId +'][deleted]"]').remove();
                            parcelRow.find('a b').attr('class', 'glyphicon glyphicon-trash text-danger');
                            parcelRow.removeClass('strikeout');
                        } else {
                            parcelRow.find('input').prop('disabled', true);
                            parcelRow.append('<input type="hidden" name="parcels['+ parcelId +'][deleted]" value="true">');
                            parcelRow.find('a b').attr('class', 'glyphicon glyphicon-repeat text-success');
                            parcelRow.addClass('strikeout');
                        }
                    }
                }
                parcelQty();
            });
        }

        function addParcel()
        {
            //Add check to make sure that the number is greater than the parcels
            var rowCount = jQuery('#package_items tr:visible').length;
            var item = jQuery(".itemized_package_node tr:last");
            var num = rowCount + 1;

            // change all the input attributes with next number up
            item.clone().find("input").each(function() {
                var rel = jQuery(this).attr('rel');
                if (rel == 'qty') {
                    bindQty(jQuery(this));
                }

                jQuery(this).attr({
                    name: 'parcels[' + num + '][' + rel + ']'
                });
            }).end().appendTo('#package_items');

            // Give the tr an id for removing later
            var lastPackItem = jQuery('#package_items tr:last');
            lastPackItem.each(function() {
                jQuery(this).attr('id', 'item' + num);
            });

            // remove the tr
            bindDelete(lastPackItem.find("a"));

            lastPackItem.find("input[rel='length']").focus();

            if (inputParcelCount && parcelQty() > inputParcelCount.value) {
                inputParcelCount.value = parcelQty();
            }
        }

        function parcelQty()
        {
            var totalQty       = 0,
                totalWeight    = 0,
                totalVolWeight = 0;

            jQuery('.parcel_qty:not(:disabled)').each(function() {
                var tableRow = jQuery(this).closest('tr'),
                    width    = tableRow.find('input[rel="width"]').val(),
                    length   = tableRow.find('input[rel="length"]').val(),
                    height   = tableRow.find('input[rel="height"]').val(),
                    weight   = tableRow.find('input[rel="weight"]').val(),
                    qty      = this.value;

                if(qty == "") {
                    qty = 0;
                } else {
                    qty = Number(qty);
                }

                totalQty       += qty;
                totalWeight    += weight*qty;
                totalVolWeight += ((width*length*height)/5000)*qty;
            });

            jQuery('.parcelTotals .totalQty').text(round(totalQty, 1));
            jQuery('.parcelTotals .totalWeight').text(round(totalWeight, 1));
            jQuery('.parcelTotals .totalVolWeight').text(round(totalVolWeight, 1));

            return totalQty;
        }

        jQuery('#package_items').on('change', 'input', function(e) {
            parcelQty();
        });

        parcelQty();
    }());

    function round(value, decimals) {
        return Number(Math.round(value+'e'+decimals)+'e-'+decimals);
    }
</script>
