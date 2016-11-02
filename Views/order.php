<?php
use MdsSupportingClasses\View;
?>

<p>
	<b>Please Note:</b>
	<ul>
		<li>
			<b>Allow an addition 24 hours on all services for outlying areas. If both the collection point and delivery point are both outlying allow an addition 48 hours.</b>
		</li>
		<li>
			If you make changes and accept, those changes will be sent to MDS Collivery as a collection and
			delivery request, make sure your information is correct. If you have managed to pass incorrect information then you
			can log onto <a href="https://quote.collivery.co.za/login.php" target="blank">MDS Collivery</a> to cancel or make
			changes.
		</li>
	</ul>
</p>

<form accept-charset="UTF-8" action="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" method="post" id="api_quote">
	<div class="parallel">
		<table width="100%">
			<tr>
				<td style="min-width:30%; max-width:35%; vertical-align: top; padding: 0 10px;">
					<?php echo View::make('_address_fields', [
						'prefix' => 'collection',
						'towns' => array('' => 'Select Town') + $towns,
						'location_types' => array('' => 'Select Location Type') + $location_types,
						'suburbs' => array('' => 'Select Suburb') + $suburbs,
						'contacts' => array('' => 'Select Contact') + $defaults['contacts'],
						'addresses' => array('' => 'Select Address') + $addresses,
						'default_address_id' => $defaults['default_address_id'],
					]);?>
				</td>
				<td style="min-width:20%; max-width:40%; vertical-align: top; padding: 0 10px;">
					<h3>Parcel's / Instructions / Service</h3>
					<?php echo View::make('_parcel_fields', [
						'include_product_titles' => $include_product_titles,
						'parcels' => $parcels
					]);?>
					<hr />
					<?php echo View::make('_service_fields', compact('services', 'shipping_method'));?>
					<hr />
					<label for="cover">Risk Cover</label>
					<label>
						<input id="cover" name="cover" type="radio" value="1"<?= !$defaultCover ? ' checked="checked"' : '' ?>>
						Up to 1000 - default
					</label>
					<label>
						<input id="cover" name="cover" type="radio" value="0"<?= !$defaultCover ? ' checked="checked"' : '' ?>>
						Up to R10,000
					</label>
					<hr />
					<label for="service">Collection Time:</label>
					<input type="text" name="collection_time" id="datetimepicker4" value=""/><hr />
				</td>
				<td style="min-width:30%; max-width:35%; vertical-align: top; padding: 0 10px;">
					<?php echo View::make('_address_fields', [
						'prefix' => 'delivery',
						'towns' => array('' => 'Select Town') + $towns,
						'location_types' => array('' => 'Select Location Type') + $location_types,
						'suburbs' => array('' => 'Select Suburb') + $populatedSuburbs,
						'order' => $order,
						'contacts' => array('' => 'Select Address First'),
						'addresses' => array('' => 'Select Address') + $addresses,
					]);?>
				</td>
			</tr>
		</table>
	</div>

	<ul id="top_menu">
		<li>
			<button type="button" id="get_quote">Get Quote</button>
		</li>
		<li>
			<button type="button" id="accept_quote">Accept/Dispatch</button>
		</li>
	</ul>
</form>

<div id="api_results"></div>

<?php
	echo View::make('_parcel_template', ['include_product_titles' => '$include_product_titles']);
?>
