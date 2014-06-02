<?php

add_filter('woocommerce_default_address_fields', 'custom_override_default_address_fields');

// Override the Billing and Shipping fields
function custom_override_default_address_fields($address_fields)
{
	$mds = new WC_MDS_Collivery();
	$collivery = $mds->getColliveryClass();
	$field = $mds->get_field_defaults();
	$towns = Array('' => 'Select Town') + $field['towns'];
	$location_types = Array('' => 'Select Premesis Type') + $field['location_types'];

	$address_fields = Array(
	'state' => Array(
		'type' => 'select',
		'label' => 'Town',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $towns,
		'selected' => ''
	),
	'city' => Array(
		'type' => 'select',
		'label' => 'Suburb',
		'required' => 1,
		'class' => Array('form-row-last'),
		'options' => Array('Select town first...')
	),
	'location_type' => Array(
		'type' => 'select',
		'label' => 'Location Type',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $location_types,
		'selected' => ''
	),
	'company' => Array(
		'label' => 'Company Name',
		'placeholder' => 'Company (optional)',
		'class' => Array('form-row-last')
	),
	'building_details' => Array(
		'label' => 'Building Details',
		'placeholder' => 'Building Details',
		'class' => Array('form-row-wide')
	),
	'address_1' => Array(
		'name' => 'billing-streetno',
		'label' => 'Street No.',
		'placeholder' => 'Street No.',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'address_2' => Array(
		'name' => 'billing-street',
		'label' => 'Street Name',
		'placeholder' => 'Street',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'first_name' => Array(
		'label' => 'First Name',
		'placeholder' => 'First Name',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'last_name' => Array(
		'label' => 'Last Name',
		'placeholder' => 'Last Name',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'email' => Array(
		'validate' => array('email'),
		'label' => 'Email Address',
		'placeholder' => 'you@yourdomain.co.za',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'phone' => Array(
		'validate' => array('phone'),
		'label' => 'Cell Phone',
		'placeholder' => 'Phone number',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	);

	return $address_fields;
}

// Hook in
add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
add_action('wp_ajax_mds_collivery_generate_suburbs', 'generate_suburbs');
add_action('wp_ajax_nopriv_mds_collivery_generate_suburbs', 'generate_suburbs');

// Override the Billing and Shipping fields in Checkout
function custom_override_checkout_fields($fields)
{
	$mds = new WC_MDS_Collivery();
	$collivery = $mds->getColliveryClass();
	$field = $mds->get_field_defaults();
	$towns = Array('' => 'Select Town') + $field['towns'];
	$location_types = Array('' => 'Select Premesis Type') + $field['location_types'];

	$billing_data = Array(
	'billing_state' => Array(
		'type' => 'select',
		'label' => 'Town',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $towns,
		'selected' => ''
	),
	'billing_city' => Array(
		'type' => 'select',
		'label' => 'Suburb',
		'required' => 1,
		'class' => Array('form-row-last'),
		'options' => Array('Select town first...')
	),
	'billing_location_type' => Array(
		'type' => 'select',
		'label' => 'Location Type',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $location_types
	),
	'billing_company' => Array(
		'label' => 'Company Name',
		'placeholder' => 'Company (optional)',
		'class' => Array('form-row-last')
	),
	'billing_building_details' => Array(
		'label' => 'Building Details',
		'placeholder' => 'Building Details',
		'class' => Array('form-row-wide')
	),
	'billing_address_1' => Array(
		'name' => 'billing-streetno',
		'label' => 'Street No.',
		'placeholder' => 'Street No.',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'billing_address_2' => Array(
		'name' => 'billing-street',
		'label' => 'Street Name',
		'placeholder' => 'Street',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'billing_first_name' => Array(
		'label' => 'First Name',
		'placeholder' => 'First Name',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'billing_last_name' => Array(
		'label' => 'Last Name',
		'placeholder' => 'Last Name',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'billing_phone' => Array(
		'validate' => array('phone'),
		'label' => 'Cell Phone',
		'placeholder' => 'Phone number',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'billing_email' => Array(
		'validate' => array('email'),
		'label' => 'Email Address',
		'placeholder' => 'you@yourdomain.co.za',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	);

	$shipping_data = Array(
	'shipping_state' => Array(
		'type' => 'select',
		'label' => 'Town',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $towns,
		'selected' => ''
	),
	'shipping_city' => Array(
		'type' => 'select',
		'label' => 'Suburb',
		'required' => 1,
		'class' => Array('form-row-last'),
		'options' => Array('Select town first...')
	),
	'shipping_location_type' => Array(
		'type' => 'select',
		'label' => 'Location Type',
		'required' => 1,
		'class' => Array('form-row-first', 'update_totals_on_change'),
		'options' => $location_types
	),
	'shipping_company' => Array(
		'label' => 'Company Name',
		'placeholder' => 'Company (optional)',
		'class' => Array('form-row-last')
	),
	'shipping_building_details' => Array(
		'label' => 'Building Details',
		'placeholder' => 'Building Details',
		'class' => Array('form-row-wide')
	),
	'shipping_address_1' => Array(
		'name' => 'billing-streetno',
		'label' => 'Street No.',
		'placeholder' => 'Street No.',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'shipping_address_2' => Array(
		'name' => 'billing-street',
		'label' => 'Street Name',
		'placeholder' => 'Street',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'shipping_first_name' => Array(
		'label' => 'First Name',
		'placeholder' => 'First Name',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'shipping_last_name' => Array(
		'label' => 'Last Name',
		'placeholder' => 'Last Name',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	'shipping_phone' => Array(
		'validate' => array('phone'),
		'label' => 'Cell Phone',
		'placeholder' => 'Phone number',
		'required' => 1,
		'class' => Array('form-row-first')
	),
	'shipping_email' => Array(
		'validate' => array('email'),
		'label' => 'Email Address',
		'placeholder' => 'you@yourdomain.co.za',
		'required' => 1,
		'class' => Array('form-row-last')
	),
	);

	$fields['billing'] = $billing_data;

	$fields['shipping'] = $shipping_data;

	return $fields;
}

// Rename Province to Town
add_filter('woocommerce_get_country_locale', 'custom_override_state_label');

function custom_override_state_label($locale)
{
	$locale['ZA']['state']['label'] = __('Town', 'woocommerce');
	return $locale;
}

// Get the Suburbs on Town Change...
function generate_suburbs()
{
	// Here so we can auto select the clients state
	if (WC()->session->get_customer_id() > 0 && $_POST['type']) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'usermeta';
	$config = $wpdb->get_results("SELECT * FROM `" . $table_name . "` WHERE user_id=" . WC()->session->get_customer_id() . " and meta_key='" . $_POST['type'] . "city';", OBJECT);
	$selected_suburb = $config[0]->meta_value;
	}

	if ((isset($_POST['town'])) && ($_POST['town'] != '')) {
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();
	$town_id = array_search($_POST['town'], $collivery->getTowns());
	$fields = $collivery->getSuburbs($town_id);
	if (!empty($fields)) {
		if (count($fields) == 1) {
		foreach ($fields as $value) {
			echo '<option value="' . $value . '">' . $value . '</option>';
		}
		} else {
		if (isset($selected_suburb)) {
			foreach ($fields as $value) {
			if ($value != $selected_suburb) {
				echo '<option value="' . $value . '">' . $value . '</option>';
			} else {
				echo '<option value="' . $value . '" selected="selected">' . $value . '</option>';
			}
			}
		} else {
			echo "<option value=\"\" selected=\"selected\">Select Suburb</option>";
			foreach ($fields as $value) {
			echo '<option value="' . $value . '">' . $value . '</option>';
			}
		}
		}
	} else
		echo '<option value="">Error retrieving data from server. Please try again later...</option>';
	} else {
	echo '<option value="">First Select Town...</option>';
	}

	die();
}
