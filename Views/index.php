<form method="post" action="">
	<div id="header">
		<div id="filterbox">
			<table>
				<tr>
					<td align="left" width="100%">
						<label for="waybill">Filter Waybill:</label>
						<input type="text" name="waybill" size="11"/>
						<label for="status"> Filter Status:</label>
						( Open <input type="radio" name="status" checked="checked" value="1"/> | Closed <input type="radio" name="status" value="0"/> )
						<input type="submit" value="Search"/>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div class="datagrid">
		<table class="adminlist" cellspacing="0" cellpadding="0">
			<thead>
				<tr>
					<th>ID</th>
					<th>Waybill Number</th>
					<th>Shipping Method</th>
					<th>Order Date</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( count( $colliveries ) > 0 ):
				$services = $collivery->getServices();
				$count = 0;
				foreach ( $colliveries as $key => $order ):
					$validation_results = json_decode( $order->validation_results );
					$count++;
				?>
				<tr <?php if ( $count % 2 == 0 ) echo ' class="alt" '; ?>>
					<td><?php echo $order->id; ?></td>
					<td><a href="<?php echo get_site_url() . '/wp-admin/admin.php?page=mds_confirmed&waybill=' . $order->waybill; ?>"><?php echo $order->waybill; ?></a></td>
					<td><?php echo $services[ $validation_results->service ]; ?></td>
					<td><?php echo date( "Y-m-d H:m", $validation_results->collection_time ); ?></td>
				</tr>
			<?php
				endforeach;
				endif;
			?>
			</tbody>
		</table>
	</div>
</form>
