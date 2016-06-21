<h1>Viewing Waybill: <?php echo $data->waybill; ?></h1>
<span>Back to </span><a href="<?php echo get_admin_url() . '/admin.php?page=mds-already-confirmed'; ?>">MDS Confirmed</a><br />
<br />
<div class="parallel">
	<table width="100%">
	<tbody>
		<tr>
		<td width="50%">
			<fieldset class="parallel_target" style="background-color: #E0E0E0;">
			<legend style="font-size:large; font-weight:bold;">Status Information:</legend>
			<table>
				<?php
				echo '<tr><td>Waybill <a href="' . get_admin_url() . '/admin.php?page=mds-confirmed-order-view-pdf&waybill=' . $data->waybill . '&type=waybill" rel="wrapped_waybill" class="show_waybill">' . $data->waybill . '--<i>View pdf</i>' . '</a></td></tr>' . '<tr><td>Status: ' . $tracking['status_text'] . '</td></tr>';
				echo '<tr><td>Status last updated:' . $tracking['updated_time'] . ' on the ' . date( "d/M/Y", strtotime( $tracking['updated_date'] ) ) . '</td></tr>';
				if ( isset( $tracking['delivered_at'] ) ) {
					echo '<tr><td>Delivered at ' . date( "H:i:s", strtotime( $tracking['delivered_at'] ) ) . ' on the ' . date( "d/M/Y", strtotime( $tracking['delivered_at'] ) );
				} else {
					if ( isset( $tracking['eta'] ) ) {
						echo '<tr><td>Estimated time of delivery: ' . date( "H:i:s", $tracking['eta'] ) . ' on the ' . date( "d/M/Y", $tracking['eta'] ) . '</td></tr>';
					} else {
						echo '<tr><td>Delivery will be before ' . $tracking['delivery_time'] . ' on the ' . date( "d/M/Y", strtotime( $tracking['delivery_date'] ) ) . '</td></tr>';
					}
				}
				?>
			</table>
			</fieldset>
		</td>
		<td width="50%">
			<fieldset class="parallel_target" style="background-color: #E0E0E0;">
			<legend style="font-size:large; font-weight:bold;">General Information:</legend>
			<table>
				<?php echo '<tr><td>Quoted Weight: ' . number_format( $validation_results->weight, 2, '.', '' ) . ' | Actual Weight: ' . number_format( $tracking['weight'], 2, '.', '' ) . '</td></tr>'; ?>
				<?php echo '<tr><td>Quoted Vol Weight: ' . number_format( $validation_results->vol_weight, 2, '.', '' ) . ' | Actual Vol Weight: ' . number_format( $tracking['vol_weight'], 2, '.', '' ) . '</td></tr>'; ?>
				<?php echo '<tr><td>Quoted Price: R' . number_format( $validation_results->price->inc_vat, 2, '.', '' ) . ' | Actual Price: R' . number_format( $tracking['total_price'] * 1.14, 2, '.', '' ) . '</td></tr>'; ?>
					<tr>
					<td>
						Proof of delivery: <?= '<a href="' . get_admin_url() . '/admin.php?page=mds-confirmed-order-view-pdf&waybill=' . $data->waybill . '&type=pod" rel="wrapped_waybill" class="show_waybill">' . 'View POD' . '</a>' ?>
					</td>
					</tr>
				<?php if ( !empty( $image_list ) ): ?>
					<tr>
					<td>
						Images (<?php echo count( $image_list ); ?>):
					<?php
					$count = 1;
					foreach ( $image_list as $image ) {
						echo ' <a href="' . $image . '" rel="image_' . $count . '" class="show_image">Image ' . $count . '</a>';
						$count++;
					}
					?>
					</td>
					</tr>
				<?php endif; ?>
			</table>
			</fieldset>
		</td>
		</tr>
	</tbody>
	</table>
</div>
<div class="parallel">
	<table width="100%">
	<tbody>
		<tr>
		<td width="50%">
			<fieldset class="parallel_target" style="background-color: #E0E0E0;">
			<legend style="font-size:large; font-weight:bold;">Collection Address:</legend>
			<?php if ( isset( $collection_address['nice_address'] ) && $collection_address['nice_address'] != "" ) {
				echo '<p>' . $collection_address['nice_address'] . '</p>';
			} ?>
			<?php
			$collection_count = 1;
			foreach ( $collection_contacts as $contact ) {
				if ( isset( $contact['nice_contact'] ) && $contact['nice_contact'] != "" ) {
					if ( $collection_count == 1 ) {
						echo '<b>Contacts:</b><br />' . $contact['nice_contact'] . '<br />';
					} else if ( $collection_count != count( $collection_contacts ) ) {
							echo $contact['nice_contact'] . '<br />';
						} else {
						echo $contact['nice_contact'];
					}
				}
				$collection_count++;
			}
			?>
			</fieldset>
		</td>
		<td width="50%">
			<fieldset class="parallel_target" style="background-color: #E0E0E0;">
			<legend style="font-size:large; font-weight:bold;">Destination Address:</legend>
			<?php if ( isset( $destination_address['nice_address'] ) && $destination_address['nice_address'] != "" ) {
				echo '<p>' . $destination_address['nice_address'] . '</p>';
			} ?>
			<?php
			$destination_count = 1;
			foreach ( $destination_contacts as $contact ) {
				if ( isset( $contact['nice_contact'] ) && $contact['nice_contact'] != "" ) {
					if ( $destination_count == 1 ) {
						echo '<b>Contacts:</b><br />' . $contact['nice_contact'] . '<br />';
					} else if ( $destination_count != count( $destination_contacts ) ) {
							echo $contact['nice_contact'] . '<br />';
						} else {
						echo $contact['nice_contact'];
					}
				}
				$destination_count++;
			}
			?>
			</fieldset>
		</td>
		</tr>
	</tbody>
	</table>
</div>
