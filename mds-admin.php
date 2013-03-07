<?php
/********************************************************************************
 * This file contains all the functions used for the admin side of the plugin.  *
 ********************************************************************************/


/**
 * Add a button to order page to register shipping with MDS
 */
function mds_order_actions( $post_id ) {
	?>
	<li><input type="submit" class="button tips" name="mds_confirm_shipping" value="<?php _e('Confirm Shipping', 'woocommerce'); ?>" data-tip="<?php _e('Register Shipping with MDS Collivery.', 'woocommerce'); ?>" /></li>
	<?php
}
add_action('woocommerce_order_actions', 'mds_order_actions');

/**
 * Redirect Admin to plugin page to register the Collivery
 */
function mds_process_order_meta( $post_id, $post) {	
	if ( isset( $_POST['mds_confirm_shipping'] ) && $_POST['mds_confirm_shipping'] ) {
		wp_redirect(home_url() . '/wp-admin/edit.php?page=mds_register&post_id='. $post_id); die;
	}
}
add_action('woocommerce_process_shop_order_meta', 'mds_process_order_meta', 20, 2);

/**
 * WordPress Backend to Register Collivery
 */
function mds_add_options() {
	add_submenu_page( null, 'Register Collivery', null, 8, 'mds_register', 'mds_register_collivery' );
}
add_action('admin_menu', 'mds_add_options');

function mds_register_collivery() {
	global $woocommerce, $woocommerce_errors;
	
	$order = new WC_Order( $_GET['post_id'] );
	$custom_fields= $order->order_custom_fields;

	$mds = new WC_MDS_Collivery;
	
	if ($custom_fields['_mds_status'][0]==1)
		echo "<h1 class=\"red\">Error. Shipping has already been registered with MDS.</h1>";
	
	else if ($order->status!='processing'){
		switch ($order->status) {
			case 'on-hold':
				echo "<h1 class=\"red\">Error. Order Status \"on-hold\". Payment might not have yet been processed!</h1>";
				break;
			
			case 'completed':
				echo "<h1 class=\"red\">Error. Order Status \"completed\". Shipping has already been processed!</h1>";
				break;
			
			default:
				echo "<h1 class=\"red\">Error. Order Status must be set to \"processing\" before shipping can be processed.</h1>";
				break;
		}		
	} else if ( isset( $_POST['mds_confirm_shipping_info'] ) && $_POST['mds_confirm_shipping_info'] ) {
		$validation = stripslashes($_POST['validation']);
		$data=json_decode($validation, TRUE);
		$new_col = $mds->register_shipping($data);
		$order->update_status('completed', 'MDS Shipping registered successfully.');
		update_post_meta( $order->id, '_mds_status', 1 );
		echo "<a href=\"". home_url() ."/wp-admin/post.php?post=$_GET[post_id]&action=edit\">Shipping registered successfully. Return to Order.</a>";
	} else {
		
		$cptypes = $mds->get_cptypes();
		
		$cptype = $mds->get_code($cptypes, $custom_fields['mds_cptypes'][0]);
		$town = $mds->get_code($mds->get_towns(), $order->shipping_state);
		$suburb = $mds->get_code($mds->get_subs($order->shipping_state), $order->shipping_city);
		
		$address=array(
			'companyName'=>$order->shipping_company,
			'CP_Type'=>$cptype,
			'TownBrief'=>$town,
			'mapID'=>$suburb,
			'building'=>$custom_fields['mds_building'][0],
			'streetnum'=>$order->shipping_address_1,
			'street'=>$order->shipping_address_2,
			);
		$contact=array(
			'fname'=>$order->shipping_first_name .' '. $order->shipping_last_name,
			'cellNo'=>$custom_fields['_shipping_phone'][0],
			'emailAddr'=>$custom_fields['_shipping_email'][0],
			);
		$my_address=$mds->my_address();
		$my_contact=$mds->my_contact();
		?>
		<style>
			.green {
				color: green;
			}
			.red {
				color: red;
			}
		</style>
		<h1>Confirm Shipping Information</h1>
		<div style="float:left; width: 50%">
			<h2>Client Information</h2>
			<table>
				<tr>
					<td>Name</td>
					<td><?php echo $contact['fname']; ?></td>
				</tr>
				<tr>
					<td>Number</td>
					<td><?php echo $contact['cellNo']; ?></td>
				</tr>
				<tr>
					<td>Email Address</td>
					<td><?php echo $contact['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Company Name</td>
					<td><?php echo $order->shipping_company; ?></td>
				</tr>
				<tr>
					<td>Location Type</td>
					<td><?php echo $custom_fields['mds_cptypes'][0]; ?></td>
				</tr>
				<tr>
					<td>Town</td>
					<td><?php echo $order->shipping_state; ?></td>
				</tr>
				<tr>
					<td>Suburb</td>
					<td><?php echo $order->shipping_city; ?></td>
				</tr>
				<tr>
					<td>Building Details</td>
					<td><?php echo $custom_fields['mds_building'][0]; ?></td>
				</tr>
				<tr>
					<td>Street Number</td>
					<td><?php echo $order->shipping_address_1; ?></td>
				</tr>
				<tr>
					<td>Street Name</td>
					<td><?php echo $order->shipping_address_2; ?></td>
				</tr>
			</table>
			<?php
				if (!isset( $_POST['mds_confirm_shipping_info'])&&!isset( $_POST['mds_add_client'])){ ?>
					<form method="post">
						<input type="submit" class="button tips" name="mds_add_client" value="<?php _e('Confirm Address', 'woocommerce'); ?>" data-tip="<?php _e('Add client to MDS database.', 'woocommerce'); ?>" />
					</form>
				<?php }
			?>
		</div>
		<div style="float:right;  width: 50%">
			<h2>My Information</h2>
			<table>
				<tr>
					<td>Name</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['fname']; ?></td>
				</tr>
				<tr>
					<td>Number</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['cellNo']; ?></td>
				</tr>
				<tr>
					<td>Email Address</td>
					<td><?php echo $my_contact['results'][$my_contact['contact_id']]['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Company Name</td>
					<td><?php echo $my_address['results']['companyName']; ?></td>
				</tr>
				<tr>
					<td>Location Type</td>
					<td><?php echo $cptypes[$my_address['results']['CP_Type']]; ?></td>
				</tr>
				<tr>
					<td>Town</td>
					<td><?php echo $my_address['results']['TownName']; ?></td>
				</tr>
				<tr>
					<td>Suburb</td>
					<td><?php echo $my_address['results']['Suburb']; ?></td>
				</tr>
				<tr>
					<td>Building Details</td>
					<td><?php //echo $contact['address']['results']['emailAddr']; ?></td>
				</tr>
				<tr>
					<td>Street Number</td>
					<td><?php echo $my_address['results']['Address3']; ?></td>
				</tr>
				<tr>
					<td>Street Name</td>
					<td><?php echo $my_address['results']['Address2']; ?></td>
				</tr>
			</table>
		</div>
		<?php
		if ( isset( $_POST['mds_add_client'] ) && $_POST['mds_add_client'] ) {
			if (isset($custom_fields['_mds_address_id'][0])&&$custom_fields['_mds_address_hash'][0]==md5(implode(',', $address)))
			{
				$cpid['results']=$custom_fields['_mds_address_id'][0];
				echo "<p class=\"green\"><strong>Address already added, using previous ID</strong></p>";
			} else {
				$cpid = $mds->addAddress($address);
			}
			
			$contact['cpid'] = $cpid['results'];
			
			if($cpid['error_message']) {
				print("Error - ".$cpid['error_message']);
			} else {
				update_post_meta( $order->id, '_mds_address_id', $cpid['results'] );
				update_post_meta( $order->id, '_mds_address_hash', md5(implode(',', $address)) );
				if (isset($custom_fields['_mds_contact_id'][0])&&$custom_fields['_mds_contact_hash'][0]==md5(implode(',', $contact)))
				{
					$ctid['results'] = $custom_fields['_mds_contact_id'][0];
					echo "<p class=\"green\"><strong>Contact already added, using previous ID</strong></p>";
				} else {
					$ctid = $mds->addContact($contact);
				}
				if($ctid['error_message'])
					print("Error - ".$ctid['error_message']);
				else{
					update_post_meta( $order->id, '_mds_contact_id', $ctid['results'] );
					update_post_meta( $order->id, '_mds_contact_hash', md5(implode(',', $contact)) );
					print("</strong></p><p class=\"green\"><strong>Address and Contact added succesfully!</strong></p>");
					$items = unserialize($custom_fields['_order_items'][0]);
					
					$collivery_data = mds_get_items($items);
					$collivery_data['collivery_from']=$my_address['address_id'];
					$collivery_data['contact_from']=$my_contact['contact_id'];
					$collivery_data['collivery_to']=$cpid['results'];
					$collivery_data['contact_to']=$ctid['results'];
					$collivery_data['collivery_type']=2;
					$collivery_data['service']=5;
					$collivery_data['mds_cover']=true;
					//print_r($collivery_data);
					$mds_contact = $mds->get_client_contact($cpid['results']);
					$mds_address = $mds->get_client_address($cpid['results']);
					
					$validation = $mds->validate($collivery_data);
					?>
					<h2>Validated Information</h2>
					<table>
						<tr>
							<th>Description</th>
							<th>Data</th>
						</tr>
						<tr>
							<td>From (Address)</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_from']; ?></td>
						</tr>
						<tr>
							<td>From (Contact)</td>
							<td style="text-align: right;"><?php echo $validation['results']['contact_from']; ?></td>
						</tr>
						<tr>
							<td>To (Address)</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_to']; ?></td>
						</tr>
						<tr>
							<td>To (Contact)</td>
							<td style="text-align: right;"><?php echo $validation['results']['contact_to']; ?></td>
						</tr>
						<tr>
							<td>Collivery Type</td>
							<td style="text-align: right;"><?php echo $validation['results']['collivery_type']; ?></td>
						</tr>
						<tr>
							<td>Number of Packages</td>
							<td style="text-align: right;"><?php echo $validation['results']['num_package']; ?></td>
						</tr>
						<tr>
							<td>Weight</td>
							<td style="text-align: right;"><?php echo $validation['results']['weight']; ?> Kg</td>
						</tr>
						<tr>
							<td>Volumetric Weight</td>
							<td style="text-align: right;"><?php echo $validation['results']['vol_weight']; ?> Kg</td>
						</tr>
						<tr>
							<td>Service</td>
							<td style="text-align: right;"><?php echo $validation['results']['service']; ?></td>
						</tr>
						<tr>
							<td>MDS Cover</td>
							<td style="text-align: right;"><?php echo ($validation['results']['mds_cover']==1) ? "Yes" : "No"; ?></td>
						</tr>
						<tr>
							<td>Collection Time</td>
							<td style="text-align: right;"><?php echo date("H\:i \- D\, d F Y",$validation['results']['collection_time']); ?></td>
						</tr>
						<tr>
							<td>Delivery Time</td>
							<td style="text-align: right;"><?php echo date("H\:i \- D\, d F Y",$validation['results']['delivery_time']); ?></td>
						</tr>
					</table>
					<p>Client Charged: <strong>R<?php echo $order->order_shipping; ?></strong><br>Actual Price: <strong>R<?php echo $validation['pricing']['results']['Total']; ?></strong></p>
					<form method="post">
						<input type="hidden" name="validation" value="<?php echo htmlentities(json_encode($validation['results'])); ?>">
						<input type="submit" class="button tips" name="mds_confirm_shipping_info" value="<?php _e('Accept Collivery', 'woocommerce'); ?>" data-tip="<?php _e('Accept the Collivery and send someone to pickup shipping.', 'woocommerce'); ?>" />
					</form>
					<?php
					
					
					
				}
			}
		}
	}
}

/**
 * Creates array with parcel information from Order
 */
function mds_get_items($items)
{
	$qty=0;
	$weight=0;
	foreach ($items as $item) {
		$product = new WC_Product($item['id']);
		$qty+=$item['qty'];
		
		$weight += $product->weight * $item['qty'];
		
		for ($i=0; $i < $item['qty']; $i++) { 
			$parcels[] = array("length" => $product->length, "width" => $product->width, "height" => $product->height, "weight" => $product->weight);
		}
	}
	return array(
		//'num_package'=>0,
		'weight'=>round($weight, 1),
		'parcels'=>$parcels,
		);
}