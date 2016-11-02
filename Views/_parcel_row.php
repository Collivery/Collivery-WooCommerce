<?php if( ! empty($count)):?>
	<tr class="parcel_rows" id="item<?php echo $count;?>" rel="parcels[<?php echo $count;?>]">
		<?php if(isset($parcel) and isset($parcel['id'])):?>
			<input type="hidden" rel="id" style="display: none;" value="<?php echo $parcel['id'];?>" name="parcels[<?php echo $count;?>][id]">
		<?php endif;?>
<?php else:?>
	<tr class="parcel_rows">
<?php endif;?>
<?php foreach(['length', 'width', 'height'] as $name):?>
	<td>
		<input type="number" name="<?php echo isset($count) ? "parcels[$count][$name]" : '';?>" value="<?php echo isset($parcel[$name]) ? $parcel[$name] : '' ?>" placeholder="cm" class="form-control" rel="<?php echo $name;?>" min="0" style="width: 60px;">
	</td>
<?php endforeach;?>
	<td>
		<input type="number" name="<?php echo isset($count) ? "parcels[$count][weight]" : '';?>" value="<?php echo isset($parcel['weight']) ? $parcel['weight'] : '' ?>" placeholder="kg" class="form-control" rel="weight" min="0" style="width: 60px;">
	</td>
	<td>
		<input type="number" name="<?php echo isset($count) ? "parcels[$count][qty]" : '';?>" value="<?php echo isset($parcel['qty']) ? $parcel['qty'] : isset($parcel['quantity']) ? $parcel['quantity'] : '' ?>" placeholder="pcs" class="form-control<?php echo isset($count) ? ' parcel_qty' : '';?>" rel="qty" min="1" style="width: 53px;">
	</td>
	<?php if(isset($include_product_titles) && $include_product_titles):?>
		<td>
			<input type="text" name="<?php echo isset($count) ? "parcels[$count][description]" : '';?>" value="<?php echo isset($parcel['description']) ? $parcel['description'] : '' ?>" placeholder="Description" class="form-control" rel="description" style="width: 200px;">
		</td>
	<?php endif;?>
	<td>
		<a class="btn-link" style="font-size: 20px; cursor: pointer; display: block; color: red;">&#10007;</a>
	</td>
</tr>
