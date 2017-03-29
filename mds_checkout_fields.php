<?php

if (!function_exists('custom_override_default_address_fields')) {
	/**
	 * Override the Billing and Shipping fields
	 *
	 * @param $address_fields
	 * @return array
	 */
	function custom_override_default_address_fields($address_fields)
	{
		$mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);
		return $mdsCheckoutFields->getCheckoutFields();
	}

	add_filter('woocommerce_default_address_fields', 'custom_override_default_address_fields');
}


if (!function_exists('custom_override_checkout_fields')) {
	/**
	 * Override the Billing and Shipping fields in Checkout
	 *
	 * @param $address_fields
	 * @return array
	 */
	function custom_override_checkout_fields($address_fields)
	{
		$mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);

		$address_fields['billing'] = $mdsCheckoutFields->getCheckoutFields('billing');
		$address_fields['shipping'] = $mdsCheckoutFields->getCheckoutFields('shipping');

		return $address_fields;
	}

	add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
}

if (!function_exists('custom_override_state_label')) {
	/**
	 * Rename Province to Town/City
	 *
	 * @param $locale
	 * @return mixed
	 */
	function custom_override_state_label($locale)
	{
		/** @var \MdsSupportingClasses\MdsColliveryService $mds */
		$mds = MdsColliveryService::getInstance();
		if (!$mds->isEnabled()) {
			return $locale;
		}

		$locale['ZA']['state']['label'] = __('Town/City');
		return $locale;
	}

	add_filter('woocommerce_get_country_locale', 'custom_override_state_label');
}

if (!function_exists('my_custom_checkout_field_update_order_meta')) {
	/**
	 * Save our location type field
	 *
	 * @param $order_id
	 */
	function my_custom_checkout_field_update_order_meta($order_id)
	{
		/** @var \MdsSupportingClasses\MdsColliveryService $mds */
		$mds = MdsColliveryService::getInstance();

		if ($mds->isEnabled()) {
			if (!empty($_POST['shipping_location_type'])) {
				update_post_meta($order_id, 'shipping_location_type', sanitize_text_field($_POST['shipping_location_type']));
			}

			if (!empty($_POST['billing_location_type'])) {
				update_post_meta($order_id, 'billing_location_type', sanitize_text_field($_POST['billing_location_type']));
			}
		}
	}

	add_action('woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta');
}

if (!function_exists('generate_suburbs')) {
	/**
	 * Get the Suburbs on Town Change
	 *
	 * @return string
	 */
	function generate_suburbs()
	{
		// Here so we can auto select the clients state
		if (WC()->session->get_customer_id() > 0 && $_POST['type']) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'usermeta';
			$config = $wpdb->get_results("SELECT * FROM `" . $table_name . "` WHERE user_id='" . WC()->session->get_customer_id() . "' and meta_key='" . $_POST['type'] . "city'", OBJECT);
			$selected_suburb = $config[0]->meta_value;
		}

		if ((isset($_POST['town'])) && ($_POST['town'] != '')) {
			/** @var \MdsSupportingClasses\MdsColliveryService $mds */
			$mds = MdsColliveryService::getInstance();
			$collivery = $mds->returnColliveryClass();
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
	}

	add_action('wp_ajax_mds_collivery_generate_suburbs', 'generate_suburbs');
	add_action('wp_ajax_nopriv_mds_collivery_generate_suburbs', 'generate_suburbs');
}
