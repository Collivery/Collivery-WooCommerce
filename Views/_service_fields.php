<label for="service">Service</label>
<select id="service" name="service">
	<option value=""<?php if(empty($shipping_method)) {echo ' selected="selected" ';} ?>></option>
	<?php foreach($services as $service_id => $service): ?>
		<option value="<?php echo $service_id; ?>" <?php if ( $service == $shipping_method ) {echo 'selected="selected" ';} ?>><?php echo $service; ?></option>
	<?php endforeach; ?>
</select>
