<h3><?php echo ucwords($prefix);?> Address</h3>

<label for="which_<?php echo $prefix;?>_address">Which <?php echo $prefix;?> Address:</label>
<label>
    <input<?php echo $prefix == 'delivery' ? ' checked="checked"' : '';?> class="which_address" name="which_<?php echo $prefix;?>_address" type="radio" value="default" rel="<?php echo $prefix;?>"> New
</label>
<label>
    <input<?php echo $prefix != 'delivery' ? ' checked="checked"' : '';?> class="which_address" name="which_<?php echo $prefix;?>_address" type="radio" value="saved" rel="<?php echo $prefix;?>"> Saved
</label>

<table id="which_<?php echo $prefix;?>_hide_default" style="<?php echo $prefix == 'delivery' ? 'display: block;' : 'display: none;';?>">
    <tbody>
        <tr>
            <td><label for="<?php echo $prefix;?>_town">Town</label></td>
            <td>
                <select style="width: 100%;" id="<?php echo $prefix;?>_town" name="<?php echo $prefix;?>_town">
                    <option value="" selected="selected"></option>
                    <?php foreach ( $towns as $town_id => $town ): ?>
                        <option<?php echo isset($order) && $town == $order->shipping_state ? ' selected="selected"' : '';?> value="<?php echo $town_id; ?>"><?php echo $town; ?></option>
                    <?php endforeach; ?>`
                </select>
            </td>
        </tr>
        <tr>
            <td id="populate_<?php echo $prefix;?>_suburb">
                <label for="<?php echo $prefix;?>_suburb">Suburb</label>
            </td>
            <td>
                <select style="width: 100%;" id="<?php echo $prefix;?>_suburb" name="<?php echo $prefix;?>_suburb">
                    <?php foreach ( $suburbs as $suburb_id => $suburb ): ?>
                        <option<?php echo isset($order) && $suburb == $order->shipping_city ? ' selected="selected"' : '';?> value="<?php echo $suburb_id; ?>"><?php echo $suburb; ?></option>
                    <?php endforeach; ?>`
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_company_name">Company</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_company_name" name="<?php echo $prefix;?>_company_name" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_company : '';?>">
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_location_type">Location Type</label></td>
            <td>
                <select style="width: 100%;" id="<?php echo $prefix;?>_location_type" name="<?php echo $prefix;?>_location_type">
                    <option value="" selected="selected"></option>
                    <?php foreach ( $location_types as $location_id => $location ): ?>
                        <option<?php echo isset($order) && $location == $order->shipping_location_type ? ' selected="selected"' : '';?> value="<?php echo $location_id; ?>"><?php echo $location; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_building_details">Building Details</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_building_details" name="<?php echo $prefix;?>_building_details" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_address_2 : '';?>"/>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_street">Street</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_street" name="<?php echo $prefix;?>_street" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_address_1 : '';?>"/>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_full_name">Contact Person</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_full_name" name="<?php echo $prefix;?>_full_name" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_first_name . ' ' . $order->shipping_last_name : '';?>"/>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_phone">Landline</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_phone" name="<?php echo $prefix;?>_phone" size="30" type="text"/>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_cellphone">Cell Phone</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_cellphone" name="<?php echo $prefix;?>_cellphone" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_phone : '';?>"/>
            </td>
        </tr>
        <tr>
            <td><label for="<?php echo $prefix;?>_email">Email</label></td>
            <td>
                <input style="width: 100%;" id="<?php echo $prefix;?>_email" name="<?php echo $prefix;?>_email" size="30" type="text" value="<?php echo isset($order) ? $order->shipping_email : '';?>"/>
            </td>
        </tr>
    </tbody>
</table>

<?php $subPrefix = $prefix == 'delivery' ? 'to' : 'from';?>
<table id="which_<?php echo $prefix;?>_hide_saved" style="<?php echo $prefix == 'delivery' ? 'display: none;' : 'display: block;';?>">
    <tbody>
        <tr>
            <td><label for="collivery_<?php echo $subPrefix;?>">Address:</label></td>
            <td>
                <select name="collivery_<?php echo $subPrefix;?>" id="collivery_<?php echo $subPrefix;?>" class="shortenedSelect" style="max-width: 400px;">
                    <?php foreach ( $addresses as $address ): if(!is_array($address)) continue;?>
                        <option<?php echo isset($default_address_id) && $default_address_id == $address['address_id'] ? ' selected="selected"' : ''; ?> value="<?php echo  $address['address_id']; ?>"><?php echo isset($address['nice_address']) ? $address['nice_address'] : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="contact_<?php echo $subPrefix;?>">Contact:</label></td>
            <td>
                <select name="contact_<?php echo $subPrefix;?>" id="contact_<?php echo $subPrefix;?>" class="shortenedSelect" style="max-width: 400px;">
                    <?php foreach ( $contacts as $contact_id => $contact ): ?>
                        <option<?php echo isset($default_contact_id) && $default_contact_id == $contact_id || count($contacts == 1) ? ' selected="selected"' : ''; ?> value="<?php echo $contact_id; ?>"><?php echo isset($contact['nice_contact']) ? $contact['nice_contact'] : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </tbody>
</table>

