<?php

/*******************************************************************************
 * This file contains all the functions used for the admin side of the plugin. *
 *******************************************************************************/

/**
 * Add our Admin menu items
 */
add_action( 'admin_menu', 'mds_admin_menu' );

/**
 * Add plugin admin menu items
 */
function mds_admin_menu()
{
	$first_page = add_submenu_page( 'woocommerce', 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds-already-confirmed', 'mds_confirmed_orders' );
	add_submenu_page( $first_page, 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds_confirmed', 'mds_confirmed_order' );
}

/**
 * Function used to display index of all our deliveries already accepted and sent to MDS Collivery
 */
function mds_confirmed_orders()
{
	global $wpdb;
	wp_register_style( 'mds_collivery_css', plugin_dir_url( __FILE__ ) . '/Views/css/mds_collivery.css' );
	wp_enqueue_style( 'mds_collivery_css' );

	$post = $_POST;
	$status = ( isset( $post['status'] ) && $post['status'] != "" ) ? $post['status'] : 1;
	$waybill = ( isset( $post['waybill'] ) && $post['waybill'] != "" ) ? $post['waybill'] : false;

	$table_name = $wpdb->prefix . 'mds_collivery_processed';
	if ( isset( $post['waybill'] ) && $post['waybill'] != "" ) {
		$colliveries = $wpdb->get_results( "SELECT * FROM `" . $table_name . "` WHERE status=" . $status . " and waybill=" . $waybill . " ORDER BY id DESC;", OBJECT );
	} else {
		$colliveries = $wpdb->get_results( "SELECT * FROM `" . $table_name . "` WHERE status=" . $status . " ORDER BY id DESC;", OBJECT );
	}

	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	include 'Views/index.php';
}

/**
 * View our Collivery once it has been accepted
 */
function mds_confirmed_order()
{
	global $wpdb;
	wp_register_script( 'mds_collivery_js', plugin_dir_url( __FILE__ ) . '/Views/js/mds_collivery.js' );
	wp_enqueue_script( 'mds_collivery_js' );

	$table_name = $wpdb->prefix . 'mds_collivery_processed';
	$data_ = $wpdb->get_results( "SELECT * FROM `" . $table_name . "` WHERE waybill=" . $_GET['waybill'] . ";", OBJECT );
	$data = $data_[0];
	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$directory = getcwd() . '/cache/mds_collivery/waybills/' . $data->waybill;

	// Do we have images of the parcels
	if ( $pod = $collivery->getPod( $data->waybill ) ) {
		if ( !is_dir( $directory ) ) {
			mkdir( $directory, 0755, true );
		}

		file_put_contents( $directory . '/' . $pod['filename'], base64_decode( $pod['file'] ) );
	}

	// Do we have proof of delivery
	if ( $parcels = $collivery->getParcelImageList( $data->waybill ) ) {
		if ( !is_dir( $directory ) ) {
			mkdir( $directory, 0755, true );
		}

		foreach ( $parcels as $parcel ) {
			$size = $parcel['size'];
			$mime = $parcel['mime'];
			$filename = $parcel['filename'];
			$parcel_id = $parcel['parcel_id'];

			if ( $image = $collivery->getParcelImage( $parcel_id ) ) {
				file_put_contents( $directory . '/' . $filename, base64_decode( $image['file'] ) );
			}
		}
	}

	// Get our tracking information
	$tracking = $collivery->getStatus( $data->waybill );
	$validation_results = json_decode( $data->validation_results );

	$collection_address = $collivery->getAddress( $validation_results->collivery_from );
	$destination_address = $collivery->getAddress( $validation_results->collivery_to );
	$collection_contacts = $collivery->getContacts( $validation_results->collivery_from );
	$destination_contacts = $collivery->getContacts( $validation_results->collivery_to );

	// Set our status if the delivery is invoiced (closed)
	if ( $tracking['status_id'] == 6 ) {
		$wpdb->query( "UPDATE `" . $table_name . "` SET `status` = 0 WHERE `waybill` = " . $data->waybill . ";" );
	}

	$pod = glob( $directory . "/*.{pdf,PDF}", GLOB_BRACE );
	$image_list = glob( $directory . "/*.{jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG}", GLOB_BRACE );
	$view_waybill = 'https://quote.collivery.co.za/waybillpdf.php?wb=' . base64_encode( $data->waybill ) . '&output=I';
	include 'Views/view.php';
}

/**
 * Add a button to order page to register shipping with MDS
 */
add_action( 'woocommerce_order_actions', 'mds_order_actions' );

/**
 * Add Order actions process MDS Shipping
 *
 * @param $actions
 * @return mixed
 */
function mds_order_actions( $actions )
{
	$actions['confirm_shipping'] = "Confirm MDS Shipping";
	return $actions;
}

/**
 * Redirect Admin to plugin page to register the Collivery
 */
add_action( 'woocommerce_order_action_confirm_shipping', 'mds_process_order_meta', 20, 2 );

/**
 * @param $order
 */
function mds_process_order_meta( $order )
{
	wp_redirect( home_url() . '/wp-admin/edit.php?page=mds_register&post_id=' . $order->id );
	die();
}

/**
 * Ajax for getting suburbs in admin section
 */
add_action( 'wp_ajax_suburbs_admin', 'suburbs_admin_callback' );

/**
 * Ajax get suburbs for town
 */
function suburbs_admin_callback()
{
	if ( ( isset( $_POST['town'] ) ) && ( $_POST['town'] != '' ) ) {
		$mds = MdsColliveryService::getInstance();
		$collivery = $mds->returnColliveryClass();
		$fields = $collivery->getSuburbs( $_POST['town'] );
		if ( !empty( $fields ) ) {
			if ( count( $fields ) == 1 ) {
				foreach ( $fields as $value ) {
					echo '<option value="' . $value . '">' . $value . '</option>';
				}
			} else {
				echo "<option value=\"\" selected=\"selected\">Select Suburb</option>";
				foreach ( $fields as $value ) {
					echo '<option value="' . $value . '">' . $value . '</option>';
				}
			}
		} else {
			echo '<option value="">Error retrieving data from server. Please try again later...</option>';
		}
	} else {
		echo '<option value="">First Select Town...</option>';
	}

	die();
}

// Ajax for getting suburbs in admin section
add_action( 'wp_ajax_contacts_admin', 'contacts_admin_callback' );

/**
 * Ajax get contacts for address
 */
function contacts_admin_callback()
{
	if ( ( isset( $_POST['address_id'] ) ) && ( $_POST['address_id'] != '' ) ) {
		$mds = MdsColliveryService::getInstance();
		$collivery = $mds->returnColliveryClass();
		$fields = $collivery->getContacts( $_POST['address_id'] );
		if ( !empty( $fields ) ) {
			if ( count( $fields ) == 1 ) {
				foreach ( $fields as $contact_id => $contact ) {
					echo '<option value="' . $contact_id . '">' . $contact['full_name'] . '</option>';
				}
			} else {
				echo "<option value=\"\" selected=\"selected\">Select Contact</option>";
				foreach ( $fields as $contact_id => $contact ) {
					echo '<option value="' . $contact_id . '">' . $contact['full_name'] . '</option>';
				}
			}
		} else {
			echo '<option value="">Error retrieving data from server. Please try again later...</option>';
		}
	} else {
		echo '<option value="">First Select Address...</option>';
	}

	die();
}

/**
 * Ajax get a quote
 */
add_action( 'wp_ajax_quote_admin', 'quote_admin_callback' );

/**
 * Ajax quote
 */
function quote_admin_callback()
{
	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$services = $collivery->getServices();
	$post = $_POST;

	// Now lets get the price for
	$data = array(
		"num_package" => count( $post['parcels'] ),
		"service" => $post['service'],
		"parcels" => $post['parcels'],
		"exclude_weekend" => 1,
		'cover' => $post['cover']
	);

	// Check which collection address we using
	if ( $post['which_collection_address'] == 'default' ) {
		$data['from_town_id'] = $post['collection_town'];
		$data['from_location_type'] = $post['collection_location_type'];
	} else {
		$data['collivery_from'] = $post['collivery_from'];
		$data['contact_from'] = $post['contact_from'];
	}

	// Check which destination address we using
	if ( $post['which_destination_address'] == 'default' ) {
		$data['to_town_id'] = $post['destination_town'];
		$data['to_location_type'] = $post['destination_location_type'];
	} else {
		$data['collivery_to'] = $post['collivery_to'];
		$data['contact_to'] = $post['contact_to'];
	}

	$response = $collivery->getPrice( $data );
	if ( !isset( $response['service'] ) ) {
		echo '<p class="mds_response">' . implode( ", ", $collivery->getErrors() ) . '</p>';
		die();
	} else {
		$form = "";
		$form .= '<p class="mds_response"><b>Service: </b>' . $services[$response['service']] . ' - Price incl: R' . $response['price']['inc_vat'] . '</p>';
		echo $form;
		die();
	}
}

/**
 * Ajax accept quote
 */
add_action( 'wp_ajax_accept_admin', 'accept_admin_callback' );

/**
 * Ajax accept quote and add delivery request to MDS
 */
function accept_admin_callback()
{
	global $wpdb;
	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$post = $_POST;

	try {
		$order = new WC_Order( $post['order_id'] );

		$colliveries = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "mds_collivery_processed WHERE order_id=" . $order->id . ";");

		if($colliveries == 0) {
			// Check which collection address we using and if we need to add the address to collivery api
			if ( $post['which_collection_address'] == 'default' ) {
				$collection_address = $mds->addColliveryAddress(array(
					'company_name' => ( $post['collection_company_name'] != "" ) ? $post['collection_company_name'] : 'Private',
					'building' => $post['collection_building_details'],
					'street' => $post['collection_street'],
					'location_type' => $post['collection_location_type'],
					'suburb' => $post['collection_suburb'],
					'building' => $post['collection_building_details'],
					'town' => $post['collection_town'],
					'full_name' => $post['collection_full_name'],
					'phone' => $post['collection_phone'],
					'cellphone' => $post['collection_cellphone'],
					'email' => $post['collection_email']
				));

				// Check for any problems
				if (!$collection_address) {
					echo '<p class="mds_response">' . implode( ", ", $collivery->getErrors() ) . '</p>';
					die();
				} else {
					// set the collection address and contact from the returned array
					$collivery_from = $collection_address['address_id'];
					$contact_from = $collection_address['contact_id'];
				}
			} else {
				$collivery_from = $post['collivery_from'];
				$contact_from = $post['contact_from'];
			}

			// Check which destination address we using and if we need to add the address to collivery api
			if ( $post['which_destination_address'] == 'default' ) {
				$destination_address = $mds->addColliveryAddress(array(
					'company_name' => ( $post['destination_company_name'] != "" ) ? $post['destination_company_name'] : 'Private',
					'building' => $post['destination_building_details'],
					'street' => $post['destination_street'],
					'location_type' => $post['destination_location_type'],
					'suburb' => $post['destination_suburb'],
					'building' => $post['destination_building_details'],
					'town' => $post['destination_town'],
					'full_name' => $post['destination_full_name'],
					'phone' => $post['destination_phone'],
					'cellphone' => $post['destination_cellphone'],
					'email' => $post['destination_email'],
					'custom_id' => $order->user_id
				));

				// Check for any problems
				if (!$destination_address) {
					echo '<p class="mds_response">' . implode( ", ", $collivery->getErrors() ) . '</p>';
					die();
				} else {
					$collivery_to = $destination_address['address_id'];
					$contact_to = $destination_address['contact_id'];
				}
			} else {
				$collivery_to = $post['collivery_to'];
				$contact_to = $post['contact_to'];
			}

			try {
				$collivery_id = $mds->addCollivery(array(
					'collivery_from' => $collivery_from,
					'contact_from' => $contact_from,
					'collivery_to' => $collivery_to,
					'contact_to' => $contact_to,
					'collivery_type' => 2, // Package
					'service' => $post['service'],
					'cover' => $post['cover'],
					'collection_time' => strtotime($post['collection_time']),
					'parcel_count' => count( $post['parcels'] ),
					'parcels' => $post['parcels']
				));
			} catch(RejectedColliveryException $e) {
				echo '<p class="mds_response">' . $e->getMessage() . '</p>';
				die();
			}

			// Check for any problems validating
			if (!$collivery_id) {
				echo '<p class="mds_response">' . implode( ", ", $collivery->getErrors() ) . '</p>';
				die();
			} else {
				// Save the results from validation into our table
				$mds->addColliveryToProcessedTable($collivery_id, $order->id);
			}

			// Update the order status
			$order->update_status( 'processing', $note = 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery_id );

			echo 'redirect|' . $post['order_id'];
			die();
		} else{
			throw new Exception('<p class="mds_response">Sorry, this order has already been processed.</p>');
		}
	} catch(Exception $e) {
		echo '<p class="mds_response">' . $e->getMessage() . '</p>';
		die();
	}
}

/**
 * WordPress Backend to Register Collivery
 */
add_action( 'admin_menu', 'mds_add_options' );

function mds_add_options()
{
	$submenu = add_submenu_page( null, 'Register Collivery', null, 'manage_options', 'mds_register', 'mds_register_collivery' );

	// load JS conditionally
	add_action( 'load-' . $submenu, 'mds_load_admin_js' );
}

/**
 * this action is only called on the register page load
 */
function mds_load_admin_js() {
	// Unfortunately we can't just enqueue our scripts here - it's too early. So
	// register against the proper action hook to do it
	add_action( 'admin_enqueue_scripts', 'mds_enqueue_admin_js' );
}

/**
 * Enqueue all admin scripts
 */
function mds_enqueue_admin_js() {

	wp_register_script( 'jquery.datetimepicker_js', plugin_dir_url( __FILE__ ) . '/Views/js/jquery.datetimepicker.js' );
	wp_enqueue_script( 'jquery.datetimepicker_js' );
	wp_register_script( 'mds_collivery_js', plugin_dir_url( __FILE__ ) . '/Views/js/mds_collivery.js' );
	wp_enqueue_script( 'mds_collivery_js' );
	wp_register_script( 'jquery.validate.min_js', plugin_dir_url( __FILE__ ) . '/Views/js/jquery.validate.min.js' );
	wp_enqueue_script( 'jquery.validate.min_js' );

	wp_register_style( 'mds_collivery_css', plugin_dir_url( __FILE__ ) . '/Views/css/mds_collivery.css' );
	wp_enqueue_style( 'mds_collivery_css' );
	wp_register_style( 'jquery.datetimepicker_css', plugin_dir_url( __FILE__ ) . '/Views/css/jquery.datetimepicker.css' );
	wp_enqueue_style( 'jquery.datetimepicker_css' );
}


/**
 * Order actions process MDS Shipping
 */
function mds_register_collivery()
{
	$order = new WC_Order( $_GET['post_id'] );
	$order_id = $_GET['post_id'];
	$custom_fields = $order->order_custom_fields;

	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$settings = $mds->returnPluginSettings();
	$parcels = $mds->getOrderContent($order->get_items());
	$defaults = $mds->returnDefaultAddress();
	$addresses = $collivery->getAddresses();

	include 'Views/order.php'; // Include our admin page
}
