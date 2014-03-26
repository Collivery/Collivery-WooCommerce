<?php
/********************************************************************************
 * This file contains all the functions used for the admin side of the plugin.  *
 ********************************************************************************/


/**
 * Add a button to order page to register shipping with MDS
 */
function mds_order_actions( $actions ) {
	$actions['confirm_shipping'] = "Confirm MDS Shipping";
	return $actions;
}
add_action('woocommerce_order_actions', 'mds_order_actions');

/**
 * Redirect Admin to plugin page to register the Collivery
 */
function mds_process_order_meta( $order) {
	wp_redirect(home_url() . '/wp-admin/edit.php?page=mds_register&post_id='. $order->id); die();
}
add_action('woocommerce_order_action_confirm_shipping', 'mds_process_order_meta', 20, 2);

/**
 * WordPress Backend to Register Collivery
 */
function mds_add_options() {
	add_submenu_page( null, 'Register Collivery', null, 8, 'mds_register', 'mds_register_collivery' );
}
add_action('admin_menu', 'mds_add_options');

function mds_register_collivery()
{
	global $woocommerce, $woocommerce_errors;
	
	$order = new WC_Order( $_GET['post_id'] );
	$custom_fields= $order->order_custom_fields;

	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();
	
	if (isset($custom_fields['_mds_status'])&&$custom_fields['_mds_status'][0]==1)
	{
		echo "<h1 class=\"red\">Error. Shipping has already been registered with MDS.</h1>
		<p>The shipping has already been placed with MDS Collivery. You can track the shipping with the following waybill: ". $custom_fields['mds_waybill'][0];
	}
	else if ($order->status!='processing')
	{
		switch ($order->status)
		{
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
	}
	else if ( isset( $_POST['mds_confirm_shipping_info'] ) && $_POST['mds_confirm_shipping_info'] )
	{
		$validation = stripslashes($_POST['validation']);
		$data=json_decode($validation, TRUE);
		$new_col = $mds->register_shipping($data);
		$order->update_status('completed', 'MDS Shipping registered successfully.');
		update_post_meta( $order->id, '_mds_status', 1 );
		update_post_meta( $order->id, 'mds_waybill', $new_col['results']['collivery_id'] );
		echo "<a href=\"". home_url() ."/wp-admin/post.php?post=$_GET[post_id]&action=edit\">Shipping registered successfully. Return to Order.</a>";
	}
	else {
		wp_register_script('mds_collivery_js', plugins_url('Collivery-WooCommerce/views/js/mds_collivery.js'));
		wp_enqueue_script( 'mds_collivery_js' );
		wp_register_style( 'mds_collivery_css', plugins_url( 'Collivery-WooCommerce/views/css/mds_collivery.css' ) );
		wp_enqueue_style( 'mds_collivery_css' );
		include 'views/order.php'; // Include our admin page
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