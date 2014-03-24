<form method="post" action="http://localhost/wordpress/wp-admin/admin.php?page=mds-already-confirmed">
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
	<table class="adminlist" cellspacing="0" cellpadding="0">
		<thead>
		<tr>
			<th>Waybill Number</th>
			<th>Shipping Method</th>
			<th>Order Date</th>
		</tr>
		</thead>
		<tbody>
		<?php if (count ($colliveries[0]) > 0):?>
			<?php foreach ($colliveries as $key => $order):
				$validation_results = json_decode($order->validation_results);
			?>
			<tr>
				<td><a href="<?php echo home_url().'/wp-admin/admin.php?page=mds_confirmed&waybill='.$order->waybill;?>"><?php echo $order->waybill;?></a></td>
				<td></td>
				<td><?php echo date("Y-m-d H:m", $validation_results->collection_time); ?></td>
			</tr>
			<?php endforeach;?>
		<?php endif;?>
		</tbody>
	</table>
</form>
