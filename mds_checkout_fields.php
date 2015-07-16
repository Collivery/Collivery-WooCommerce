<?php

add_filter( 'woocommerce_default_address_fields', 'custom_override_default_address_fields' );

// Override the Billing and Shipping fields
function custom_override_default_address_fields( $address_fields )
{
	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$settings = $mds->returnPluginSettings();
	if ($settings['enabled'] == 'no') {
		return $address_fields;
	}

	$field = $mds->returnFieldDefaults();
    $provinces = array( '' => 'Select Province' ) + $field['provinces'];
	$towns = array( '' => 'Select Town' ) + $field['towns'];
	$location_types = array( '' => 'Select Premises Type' ) + $field['location_types'];

	$address_fields = array(
        'province'  => array(
            'type'  => 'select',
            'label' => 'Province',
            'required'  => 1,
            'class'     => array( 'update_totals_on_change' ),
            'options'   => $provinces
        ),
		'state' => array(
			'type' => 'select',
			'label' => 'Town',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $towns,
			'selected' => ''
		),
		'city' => array(
			'type' => 'select',
			'label' => 'Suburb',
			'required' => 1,
			'class' => array( 'form-row-wide' ),
			'options' => array( 'Select town first...' )
		),
		'location_type' => array(
			'type' => 'select',
			'label' => 'Location Type',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $location_types,
			'selected' => ''
		),
		'company' => array(
			'label' => 'Company Name',
			'placeholder' => 'Company (optional)',
			'class' => array( 'form-row-wide' )
		),
		'building_details' => array(
			'label' => 'Building Details',
			'placeholder' => 'Building Details',
			'class' => array( 'form-row-wide' )
		),
		'address_1' => array(
			'name' => 'billing-streetno',
			'label' => 'Street No.',
			'placeholder' => 'Street No.',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'postcode' => array(
			'name' => 'postcode',
			'label' => 'Postal Code',
			'placeholder' => 'Postal Code',
			'required' => 0,
			'class' => array( 'form-row-last' )
		),
		'address_2' => array(
			'name' => 'billing-street',
			'label' => 'Street Name',
			'placeholder' => 'Street',
			'required' => 1,
			'class' => array( 'form-row-wide' )
		),
		'first_name' => array(
			'label' => 'First Name',
			'placeholder' => 'First Name',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'last_name' => array(
			'label' => 'Last Name',
			'placeholder' => 'Last Name',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
		'phone' => array(
			'validate' => array( 'phone' ),
			'label' => 'Cell Phone',
			'placeholder' => 'Phone number',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'email' => array(
			'validate' => array( 'email' ),
			'label' => 'Email Address',
			'placeholder' => 'you@yourdomain.co.za',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
	);

	return apply_filters('mds_default_address_fields', $address_fields);
}

// Hook in
add_filter( 'woocommerce_checkout_fields', 'custom_override_checkout_fields' );
add_action( 'wp_ajax_mds_collivery_generate_towns', 'generate_towns' );
add_action( 'wp_ajax_nopriv_mds_collivery_generate_towns', 'generate_towns' );

// Override the Billing and Shipping fields in Checkout
function custom_override_checkout_fields( $fields )
{
	$mds = MdsColliveryService::getInstance();
	$collivery = $mds->returnColliveryClass();
	$settings = $mds->returnPluginSettings();
	if ($settings['enabled'] == 'no') {
		return $fields;
	}

	$field = $mds->returnFieldDefaults();
    $provinces = array( '' => 'Select Province' ) + $field['provinces'];
	$towns = array( '' => 'Select Town' ) + $field['towns'];
	$location_types = array( '' => 'Select Premises Type' ) + $field['location_types'];

	$billing_data = array(
        'billing_province'   => array(
            'type'      => 'select',
            'label'     => 'Province',
            'required'  => true,
            'class'     => array( 'update_totals_on_change' ),
            'options'   => $provinces,
            'selected'  => '',
        ),
		'billing_state' => array(
			'type' => 'select',
			'label' => 'Town',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $towns,
			'selected' => ''
		),
		'billing_city' => array(
			'type' => 'select',
			'label' => 'Suburb',
			'required' => 1,
			'class' => array( 'form-row-wide' ),
			'options' => array( 'Select town first...' )
		),
		'billing_location_type' => array(
			'type' => 'select',
			'label' => 'Location Type',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $location_types
		),
		'billing_company' => array(
			'label' => 'Company Name',
			'placeholder' => 'Company (optional)',
			'class' => array( 'form-row-wide' )
		),
		'billing_building_details' => array(
			'label' => 'Building Details',
			'placeholder' => 'Building Details',
			'class' => array( 'form-row-wide' )
		),
		'billing_address_1' => array(
			'name' => 'billing-streetno',
			'label' => 'Street No.',
			'placeholder' => 'Street No.',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'billing_postcode' => array(
			'name' => 'postcode',
			'label' => 'Postal Code',
			'placeholder' => 'Postal Code',
			'required' => 0,
			'class' => array( 'form-row-last' )
		),
		'billing_address_2' => array(
			'name' => 'billing-street',
			'label' => 'Street Name',
			'placeholder' => 'Street',
			'required' => 1,
			'class' => array( 'form-row-wide' )
		),
		'billing_first_name' => array(
			'label' => 'First Name',
			'placeholder' => 'First Name',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'billing_last_name' => array(
			'label' => 'Last Name',
			'placeholder' => 'Last Name',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
		'billing_phone' => array(
			'validate' => array( 'phone' ),
			'label' => 'Cell Phone',
			'placeholder' => 'Phone number',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'billing_email' => array(
			'validate' => array( 'email' ),
			'label' => 'Email Address',
			'placeholder' => 'you@yourdomain.co.za',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
	);

	$shipping_data = array(
        'shipping_province'   => array(
            'type'      => 'select',
            'label'     => 'Province',
            'required'  => true,
            'class'     => array( 'update_totals_on_change' ),
            'options'   => $provinces,
            'selected'  => '',
        ),
		'shipping_state' => array(
			'type' => 'select',
			'label' => 'Town',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $towns,
			'selected' => ''
		),
		'shipping_city' => array(
			'type' => 'select',
			'label' => 'Suburb',
			'required' => 1,
			'class' => array( 'form-row-wide' ),
			'options' => array( 'Select town first...' )
		),
		'shipping_location_type' => array(
			'type' => 'select',
			'label' => 'Location Type',
			'required' => 1,
			'class' => array( 'form-row-wide', 'update_totals_on_change' ),
			'options' => $location_types
		),
		'shipping_company' => array(
			'label' => 'Company Name',
			'placeholder' => 'Company (optional)',
			'class' => array( 'form-row-wide' )
		),
		'shipping_building_details' => array(
			'label' => 'Building Details',
			'placeholder' => 'Building Details',
			'class' => array( 'form-row-wide' )
		),
		'shipping_address_1' => array(
			'name' => 'billing-streetno',
			'label' => 'Street No.',
			'placeholder' => 'Street No.',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'shipping_postcode' => array(
			'name' => 'postcode',
			'label' => 'Postal Code',
			'placeholder' => 'Postal Code',
			'required' => 0,
			'class' => array( 'form-row-last' )
		),
		'shipping_address_2' => array(
			'name' => 'billing-street',
			'label' => 'Street Name',
			'placeholder' => 'Street',
			'required' => 1,
			'class' => array( 'form-row-wide' )
		),
		'shipping_first_name' => array(
			'label' => 'First Name',
			'placeholder' => 'First Name',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'shipping_last_name' => array(
			'label' => 'Last Name',
			'placeholder' => 'Last Name',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
		'shipping_phone' => array(
			'validate' => array( 'phone' ),
			'label' => 'Cell Phone',
			'placeholder' => 'Phone number',
			'required' => 1,
			'class' => array( 'form-row-first' )
		),
		'shipping_email' => array(
			'validate' => array( 'email' ),
			'label' => 'Email Address',
			'placeholder' => 'you@yourdomain.co.za',
			'required' => 1,
			'class' => array( 'form-row-last' )
		),
	);

	$fields['billing'] = $billing_data;

	$fields['shipping'] = $shipping_data;

	return apply_filters( 'mds_override_checkout_fields', $fields );
}

/**
 * Rename Province to Town
 */
add_filter( 'woocommerce_get_country_locale', 'custom_override_state_label' );

function custom_override_state_label( $locale )
{
	$mds = MdsColliveryService::getInstance();
	$settings = $mds->returnPluginSettings();
	if ($settings['enabled'] == 'no') {
		return $locale;
	}

	$locale['ZA']['state']['label'] = __( 'Town', 'woocommerce' );
	return $locale;
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta' );

function my_custom_checkout_field_update_order_meta( $order_id ) {
	$mds = MdsColliveryService::getInstance();
	$settings = $mds->returnPluginSettings();
	if ($settings['enabled'] == 'yes') {
		if(!empty($_POST['shipping_location_type'])) {
			update_post_meta( $order_id, 'shipping_location_type', sanitize_text_field( $_POST['shipping_location_type'] ) );
		}

		if(!empty($_POST['billing_location_type'])) {
			update_post_meta( $order_id, 'billing_location_type', sanitize_text_field( $_POST['billing_location_type'] ) );
		}
	}
}

/**
 * Add location_type to session
 */
add_action('wp_ajax_add_location_type_to_session', 'add_location_type_to_session');
add_action('wp_ajax_nopriv_add_location_type_to_session', 'add_location_type_to_session');
function add_location_type_to_session() {
	$mds = MdsColliveryService::getInstance();
	$settings = $mds->returnPluginSettings();
	if ($settings['enabled'] == 'yes') {
		WC()->session->set('use_location_type', esc_attr($_POST['use_location_type']));
		WC()->session->set('shipping_location_type', esc_attr($_POST['shipping_location_type']));
		WC()->session->set('billing_location_type', esc_attr($_POST['billing_location_type']));
	}

	echo 'done';
	die();
}

add_action( 'wp_ajax_mds_collivery_generate_suburbs', 'generate_suburbs' );
add_action( 'wp_ajax_nopriv_mds_collivery_generate_suburbs', 'generate_suburbs' );

// Get the Suburbs on Town Change...
function generate_suburbs()
{
	// Here so we can auto select the clients state
	if ( WC()->session->get_customer_id() > 0 && $_POST['type'] ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'usermeta';
		$config = $wpdb->get_results( "SELECT * FROM `" . $table_name . "` WHERE user_id=" . WC()->session->get_customer_id() . " and meta_key='" . $_POST['type'] . "city';", OBJECT );
		$selected_suburb = !empty($config) ? $config[0]->meta_value : '';
	}
    
    // add filter to override selected suburb
    $selected_suburb = apply_filters( 'collivery_selected_suburb', $selected_suburb );

	if ( ( isset( $_POST['town'] ) ) && ( $_POST['town'] != '' ) ) {
		$mds = MdsColliveryService::getInstance();
		$collivery = $mds->returnColliveryClass();
		$town_id = array_search( $_POST['town'], $collivery->getTowns() );
		$fields = $collivery->getSuburbs( $town_id );
		if ( !empty( $fields ) ) {
			if ( count( $fields ) == 1 ) {
				foreach ( $fields as $value ) {
                    if ( $value != $selected_suburb ) {
                        echo '<option value="' . $value . '">' . $value . '</option>';
                    }
                    else {
                        echo '<option value="' . $value . '" selected="selected">' . $value . '</option>';
                    }
				}
			} else {
				if ( isset( $selected_suburb ) ) {
					foreach ( $fields as $value ) {
						if ( $value != $selected_suburb ) {
							echo '<option value="' . $value . '">' . $value . '</option>';
						} else {
							echo '<option value="' . $value . '" selected="selected">' . $value . '</option>';
						}
					}
				} else {
					echo "<option value=\"\" selected=\"selected\">Select Suburb</option>";
					foreach ( $fields as $value ) {
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

// Get towns on province change...
function generate_towns() {
    $customer_id = WC()->session->get_customer_id();
    // Here so we can auto select the clients province
    if ( $customer_id > 0 && $_POST['type'] ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'usermeta';
        $config = $wpdb->get_results( "SELECT * FROM `{$table_name}` WHERE `user_id` = '{$customer_id}' AND `meta_key` = '{$_POST['type']}state';", OBJECT );
        $selected_town = !empty($config) ? $config[0]->meta_value : '';
    }
    
    // apply filter to $selected_town
    $selected_town = apply_filters( 'collivery_selected_town', $selected_town );
    
    if ( isset( $_POST['province'] ) && $_POST['province'] != '' ) {
        $mds = MdsColliveryService::getInstance();
        $collivery = $mds->returnColliveryClass();
        $province_code = array_search( $_POST['province'], $collivery->getProvinces() );
        $fields = $collivery->getTowns( $country = "ZAF", $province_code );
        if ( !empty( $fields ) ) {
            if ( count( $fields ) == 1 ) {
                foreach ( $fields as $value ) {
                    if ( $value != $selected_town ) {
                        echo '<option value="' . $value . '">' . $value . '</option>';
                    }
                    else {
                        echo '<option value="' . $value . '" selected="selected">' . $value . '</option>';
                    }
                }
            }
            else {
                if ( !empty( $selected_town ) ) {
                    foreach ( $fields as $value ) {
                        if ( $value != $selected_town ) {
                            echo '<option value="' . $value . '">' . $value . '</option>';
                        }
                        else {
                            echo '<option value="' . $value . '" selected="selected">' . $value . '</option>';
                        }
                    }
                }
                else {
                    echo '<option value="" selected="selected">Select Town</option>';
                    foreach ( $fields as $value ) {
                        echo '<option value="' . $value . '">' . $value . '</option>';
                    }
                }
            }
        }
        else {
            echo '<option value="">Error retrieving data from server. Please try again later...</option>';
        }
    }
    else {
        echo '<option value="">First Select Province...</option>';
    }
    
    die();
}

// store province in woocommerce customer
add_action( 'woocommerce_checkout_update_order_review', 'collivery_add_checkout_province' );
function collivery_add_checkout_province( $post_data ) {
    
    parse_str($post_data, $post_data_arr);
    WC()->customer->shipping_province = $post_data_arr['shipping_province'];
    
}
