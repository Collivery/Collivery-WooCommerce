<?php

/* * ******************************************************************************
 * This file contains all the functions used for the admin side of the plugin.  *
 * ****************************************************************************** */

// --------------------------------------------------------------------

/**
 * Add a button to order page to register shipping with MDS
 */
add_action('woocommerce_order_actions', 'mds_order_actions');

function mds_order_actions($actions)
{
    $actions['confirm_shipping'] = "Confirm MDS Shipping";
    return $actions;
}

// --------------------------------------------------------------------

/**
 * Redirect Admin to plugin page to register the Collivery
 */
add_action('woocommerce_order_action_confirm_shipping', 'mds_process_order_meta', 20, 2);

function mds_process_order_meta($order)
{
    wp_redirect(home_url() . '/wp-admin/edit.php?page=mds_register&post_id=' . $order->id);
    die();
}

// --------------------------------------------------------------------
// Ajax for getting suburbs in admin section
add_action('wp_ajax_suburbs_admin', 'suburbs_admin_callback');

function suburbs_admin_callback()
{
    if ((isset($_POST['town'])) && ($_POST['town'] != '')) {
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();
	$fields = $collivery->getSuburbs($_POST['town']);
	if (!empty($fields)) {
	    if (count($fields) == 1) {
		foreach ($fields as $value) {
		    echo '<option value="' . $value . '">' . $value . '</option>';
		}
	    } else {
		echo "<option value=\"\" selected=\"selected\">Select Suburb</option>";
		foreach ($fields as $value) {
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

// --------------------------------------------------------------------
// Ajax for getting suburbs in admin section
add_action('wp_ajax_contacts_admin', 'contacts_admin_callback');

function contacts_admin_callback()
{
    if ((isset($_POST['address_id'])) && ($_POST['address_id'] != '')) {
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();
	$fields = $collivery->getContacts($_POST['address_id']);
	if (!empty($fields)) {
	    if (count($fields) == 1) {
		foreach ($fields as $contact_id => $contact) {
		    echo '<option value="' . $contact_id . '">' . $contact['full_name'] . '</option>';
		}
	    } else {
		echo "<option value=\"\" selected=\"selected\">Select Contact</option>";
		foreach ($fields as $contact_id => $contact) {
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

// --------------------------------------------------------------------
// Ajax get a quote
add_action('wp_ajax_quote_admin', 'quote_admin_callback');

function quote_admin_callback()
{
    $mds = new WC_MDS_Collivery;
    $collivery = $mds->getColliveryClass();
    $services = $collivery->getServices();
    $post = $_POST;

    // Now lets get the price for
    $data = array(
	"num_package" => count($post['parcels']),
	"service" => $post['service'],
	"parcels" => $post['parcels'],
	"exclude_weekend" => 1,
	'cover' => $post['cover']
    );

    // Check which collection address we using
    if ($post['which_collection_address'] == 'default') {
	$data['from_town_id'] = $post['collection_city'];
	$data['from_town_type'] = $post['collection_location_type'];
    } else {
	$data['collivery_from'] = $post['collivery_from'];
	$data['contact_from'] = $post['contact_from'];
    }

    // Check which destination address we using
    if ($post['which_destination_address'] == 'default') {
	$data['to_town_id'] = $post['destination_city'];
	$data['to_town_type'] = $post['destination_location_type'];
    } else {
	$data['collivery_to'] = $post['collivery_to'];
	$data['contact_to'] = $post['contact_to'];
    }

    $response = $collivery->getPrice($data);
    if (!isset($response['service'])) {
	echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
	die();
    } else {
	$form = "";
	$form .= '<p class="mds_response"><b>Service: </b>' . $services[$response['service']] . ' - Price incl: R' . $response['price']['inc_vat'] . '</p>';
	echo $form;
	die();
    }
}

// --------------------------------------------------------------------
// Ajax get a quote
add_action('wp_ajax_accept_admin', 'accept_admin_callback');

function accept_admin_callback()
{
    global $wpdb;
    $mds = new WC_MDS_Collivery;
    $collivery = $mds->getColliveryClass();
    $post = $_POST;

    // Check which collection address we using and if we need to add the address to collivery api
    if ($post['which_collection_address'] == 'default') {
	$collection_address = array(
	    'company_name' => ( $post['collection_company_name'] != "" ) ? $post['collection_company_name'] : 'Private',
	    'building' => $post['collection_building_details'],
	    'street' => $post['collection_street'],
	    'location_type' => $post['collection_location_type'],
	    'suburb_id' => $post['collection_state'],
	    'building' => $post['collection_building_details'],
	    'town_id' => $post['collection_city'],
	    'full_name' => $post['collection_full_name'],
	    'phone' => $post['collection_phone'],
	    'cellphone' => $post['collection_cellphone'],
	    'email' => $post['collection_email']
	);

	// Check for any problems
	if (!$collection_address_response = $collivery->addAddress($collection_address)) {
	    echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
	    die();
	} else {
	    // set the collection address and contact from the returned array
	    $collivery_from = $collection_address_response['address_id'];
	    $contact_from = $collection_address_response['contact_id'];
	}
    } else {
	$collivery_from = $post['collivery_from'];
	$contact_from = $post['contact_from'];
    }

    // Check which destination address we using and if we need to add the address to collivery api
    if ($post['which_destination_address'] == 'default') {
	$destination_address = array(
	    'company_name' => ( $post['destination_company_name'] != "" ) ? $post['destination_company_name'] : 'Private',
	    'building' => $post['destination_building_details'],
	    'street' => $post['destination_street'],
	    'location_type' => $post['destination_location_type'],
	    'suburb_id' => $post['destination_state'],
	    'building' => $post['destination_building_details'],
	    'town_id' => $post['destination_city'],
	    'full_name' => $post['destination_full_name'],
	    'phone' => $post['destination_phone'],
	    'cellphone' => $post['destination_cellphone'],
	    'email' => $post['destination_email']
	);

	// Check for any problems
	if (!$destination_address_response = $collivery->addAddress($destination_address)) {
	    echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
	    die();
	} else {
	    $collivery_to = $destination_address_response['address_id'];
	    $contact_to = $destination_address_response['contact_id'];
	}
    } else {
	$collivery_to = $post['collivery_to'];
	$contact_to = $post['contact_to'];
    }

    $data_collivery = array(
	'collivery_from' => $collivery_from,
	'contact_from' => $contact_from,
	'collivery_to' => $collivery_to,
	'contact_to' => $contact_to,
	'collivery_type' => 2, // Package
	'service' => $post['service'],
	'cover' => $post['cover'],
	'collection_time' => $post['collection_time'],
	'parcel_count' => count($post['parcels']),
	'parcels' => $post['parcels']
    );

    // Check for any problems validating
    if (!$validated = $collivery->validate($data_collivery)) {
	echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
	die();
    } else {
	// Check for any problems adding
	if (!$collivery_id = $collivery->addCollivery($validated)) {
	    echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
	    die();
	} else {
	    // Check for any problems accepting
	    if (!$collivery->acceptCollivery($collivery_id)) {
		echo '<p class="mds_response">' . implode(", ", $collivery->getErrors()) . '</p>';
		die();
	    } else {
		// Save the results from validation into our table
		$validated = json_encode($validated);
		$table_name = $wpdb->prefix . 'mds_collivery_processed';
		$data = array(
		    'status' => 1,
		    'validation_results' => $validated,
		    'waybill' => $collivery_id
		);
		$rows_affected = $wpdb->insert($table_name, $data);
	    }
	}
    }

    // Update the order status
    $order = new WC_Order($post['order_id']);
    $order->update_status('processing', $note = 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery_id);

    echo 'redirect|' . $post['order_id'];
    die();
}

// --------------------------------------------------------------------

/**
 * WordPress Backend to Register Collivery
 */
add_action('admin_menu', 'mds_add_options');

function mds_add_options()
{
    add_submenu_page(null, 'Register Collivery', null, 8, 'mds_register', 'mds_register_collivery');
}

// --------------------------------------------------------------------

function mds_register_collivery()
{
    global $woocommerce, $woocommerce_errors;

    $order = new WC_Order($_GET['post_id']);
    $order_id = $_GET['post_id'];
    $custom_fields = $order->order_custom_fields;
    $my_order_meta = get_post_custom($_GET['post_id']);

    $mds = new WC_MDS_Collivery;
    $collivery = $mds->getColliveryClass();
    $settings = $mds->getColliverySettings();
    $parcels = $mds->get_order_content($order->get_items());
    $defaults = $mds->getDefaulsAddress();
    $addresses = $collivery->getAddresses();

    wp_register_script('jquery.datetimepicker_js', plugin_dir_url(__FILE__) . '/views/js/jquery.datetimepicker.js');
    wp_enqueue_script('jquery.datetimepicker_js');
    wp_register_script('mds_collivery_js', plugin_dir_url(__FILE__) . '/views/js/mds_collivery.js');
    wp_enqueue_script('mds_collivery_js');
    wp_register_script('jquery.validate.min_js', plugin_dir_url(__FILE__) . '/views/js/jquery.validate.min.js');
    wp_enqueue_script('jquery.validate.min_js');

    wp_register_style('mds_collivery_css', plugin_dir_url(__FILE__) . '/views/css/mds_collivery.css');
    wp_enqueue_style('mds_collivery_css');
    wp_register_style('jquery.datetimepicker_css', plugin_dir_url(__FILE__) . '/views/css/jquery.datetimepicker.css');
    wp_enqueue_style('jquery.datetimepicker_css');
    include 'views/order.php'; // Include our admin page
}
