<?php

define('MDS_VERSION', "2.0.1");

/**
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.collivery.co.za/
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 2.0.1
 * Author: Bryce Large
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	/**
	 * Register Install function
	 */
	register_activation_hook(__FILE__, 'install');

	/**
	 * When the plugin is installed we check if the mds collivery table exists and if not creates it.
	 */
	function install()
	{
		// We have to check what php version we have before anything is installed.
		if (version_compare(PHP_VERSION, '5.3.0') < 0) {
			die('Your PHP version is not able to run this plugin, update to the latest version before installing this plugin.');
		}

		global $wpdb;

		// Creates our table to store our accepted deliveries
		$table_name = $wpdb->prefix . 'mds_collivery_processed';
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`waybill` int(11) NOT NULL,
			`validation_results` TEXT NOT NULL,
			`status` int(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (`id`)
		);";

		$wpdb->query($sql);

		add_option("mds_db_version", MDS_VERSION);
	}

	add_action('plugins_loaded', 'init_mds_collivery', 0);

	/**
	 * Instantiate the plugin
	 */
	function init_mds_collivery()
	{
		// Check if 'WC_Shipping_Method' class is loaded, else exit.
		if (!class_exists('WC_Shipping_Method')) {
			return;
		}

		require_once( 'mds_admin.php' ); // Admin scripts
		require_once( 'mds_checkout_fields.php' ); // Checkout fields.
		require_once( 'SupportingClasses/GithubPluginUpdater.php' ); // Auto updating class

		/**
		 * Load JS file throughout
		 */
		function load_js()
		{
			wp_register_script('mds_js', plugins_url('script.js', __FILE__), array('jquery'));
			wp_enqueue_script('mds_js');
		}

		add_action('wp_enqueue_scripts', 'load_js');

		/**
		 * Check for updates
		 */
		if ( is_admin() ) {
			new GitHubPluginUpdater( __FILE__, 'Collivery', "Collivery-WooCommerce" );
		}

		require_once( 'WC_MDS_Collivery.php' );
	}

	/**
	 * Register Chipping Plugin with WooCommerce
	 *
	 * @param $methods
	 * @return array
	 */
	function add_mds_shipping_method($methods)
	{
		$methods[] = 'WC_MDS_Collivery';
		return $methods;
	}

	add_filter('woocommerce_shipping_methods', 'add_mds_shipping_method');

	/**
	 * WooCommerce caches pricing information.
	 * This adds our unique fields to the hash to update pricing cache when changed.
	 *
	 * @param $packages
	 * @return mixed
	 */
	function mds_collivery_cart_shipping_packages($packages)
	{
		$mds = new WC_MDS_Collivery;
		$defaults = $mds->get_default_address();
		$collivery = $mds->get_collivery_class();
		$settings = $mds->get_collivery_settings();

		$towns = $collivery->getTowns();
		$location_types = $collivery->getLocationTypes();

		$package = $mds->build_package_from_cart();
		$cart = $mds->get_cart_content($package);

		$use_location_type = WC()->session->get('use_location_type', null);
		$location_type = [
			'shipping_location_type' => WC()->session->get('shipping_location_type', null),
			'billing_location_type' => WC()->session->get('billing_location_type', null),
		];

		if(!is_array($cart) || !isset($cart['total'])) {
			return false;
		}

		if(isset($_POST['post_data'])) {
			parse_str($_POST['post_data'], $post_data);
			if(!isset($post_data['ship_to_different_address']) || $post_data['ship_to_different_address'] != TRUE) {
				$to_town_id = $post_data['billing_state'];
				$to_town_type = $post_data['billing_location_type'];
			} else {
				$to_town_id = $post_data['shipping_state'];
				$to_town_type = $post_data['shipping_location_type'];
			}
		} elseif(isset($_POST['ship_to_different_address'])) {
			if (!isset($_POST['ship_to_different_address']) || $_POST['ship_to_different_address'] != TRUE) {
				$to_town_id = $_POST['billing_state'];
				$to_town_type = $_POST['billing_location_type'];
			} else {
				$to_town_id = $_POST['shipping_state'];
				$to_town_type = $_POST['shipping_location_type'];
			}
		} elseif(isset($packages[0]['destination'])) {
			$to_town_id = $packages[0]['destination']['state'];
			$to_town_type = $location_type[$use_location_type];
		}

		$package['cart'] = $cart;
		$package['method_free'] = $settings["method_free"];
		$package['free_min_total'] = $settings["free_min_total"];
		$package['free_local_only'] = $settings["free_local_only"];

		$package['destination'] = array(
			"from_town_id" => (int) $defaults['address']['town_id'],
			"from_location_type" => (int) $defaults['address']['location_type'],
			"to_town_id" => (int) array_search($to_town_id, $towns),
			"to_location_type" => (int) array_search($to_town_type, $location_types),
			'country' => WC()->customer->get_shipping_country(),
			'state' => WC()->customer->get_shipping_state(),
			'postcode' => WC()->customer->get_shipping_postcode(),
			'city' => WC()->customer->get_shipping_city(),
			'address' => WC()->customer->get_shipping_address(),
			'address_2' => WC()->customer->get_shipping_address_2()
		);

		if ($settings["method_free"] == 'yes' && $cart['total'] >= $settings["free_min_total"]) {
			$package['service'] = 'free';
			if($settings["free_local_only"] == 'yes') {
				$data = array(
					"num_package" => 1,
					"service" => 2,
					"exclude_weekend" => 1,
				) + $package['destination'];

				// Query the API to test if this is a local delivery
				$response = $this->collivery->getPrice($data);
				if(isset($response['delivery_type']) && $response['delivery_type'] == 'local') {
					if($mds->valid_package($package)) {
						$packages[0] = $package;
					} else {
						return false;
					}
				}
			} else {
				if($mds->valid_package($package)) {
					$packages[0] = $package;
				} else {
					return false;
				}
			}
		} else {
			if($mds->valid_package($package)) {
				$packages[0] = $package;
			} else {
				return false;
			}
		}

		return $packages;
	}

	add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');

	/**
	 * Automatically send MDS Collivery the delivery request
	 * The accounts default address and first contact will be used and a new destination address will be added every time
	 *
	 * @param $order_status
	 * @param $order_id
	 * @return string
	 */
	function virtual_order_payment_complete_order_status($order_status, $order_id)
	{
		$order = new WC_Order($order_id);

		if('processing' == $order_status && ('on-hold' == $order->status || 'pending' == $order->status || 'failed' == $order->status)) {
			try {
				$mds = new WC_MDS_Collivery;
				$settings = $mds->get_collivery_settings();
				$parcels = $mds->get_order_content($order->get_items());
				$defaults = $mds->get_default_address();

				$address = $mds->add_collivery_address(array(
					'company_name' => ( $order->shipping_company != "" ) ? $order->shipping_company : 'Private',
					'building' => $order->shipping_building_details,
					'street' => $order->shipping_address_1 . ' ' . $order->shipping_address_2,
					'location_type' => $order->shipping_location_type,
					'suburb' => $order->shipping_city,
					'town' => $order->shipping_state,
					'full_name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
					'cellphone' => $order->shipping_phone,
					'email' => $order->shipping_email
				));

				$collivery_from = $defaults['default_address_id'];
				list($contact_from) = array_keys($defaults['contacts']);
				$collivery_to = $address['address_id'];
				$contact_to = $address['contact_id'];

				$collivery_id = $mds->add_collivery(array(
					'collivery_from' => $collivery_from,
					'contact_from' => $contact_from,
					'collivery_to' => $collivery_to,
					'contact_to' => $contact_to,
					'collivery_type' => 2,
					'service' => $order->get_shipping_method(),
					'cover' => ($settings['risk_cover'] == 'yes') ? 1 : 0,
					'parcel_count' => count($parcels),
					'parcels' => $parcels
				));

				if($collivery_id) {
					$order->update_status( 'processing', 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery_id );
				} else {
					$order->update_status( 'processing', 'Order has not been sent to MDS Collivery successfully, you will need to manually process.');
				}

				// Return new order status
				return 'completed';
			} catch(Exception $e) {
				$order->update_status( 'processing', 'Order has not been sent to MDS Collivery successfully, you will need to manually process. Error: ' . $e->getMessage());
			}
		}

		// return original status
		return $order_status;
	}

	add_filter('woocommerce_payment_complete_order_status', 'virtual_order_payment_complete_order_status');

} elseif (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {

	wc_add_notice(sprintf(__("This plugin requires WooCommerce 2.1 or higher!", "woocommerce-mds-shipping" ), 'error'));

} else {

	/**
	 * Check if WooCommerce is up and running
	 *
	 * @return null
	 */
	function checkWooNotices()
	{
		if (!is_plugin_active('woocommerce/woocommerce.php')) {
			ob_start();
			?><div class="error">
			<p><strong><?php _e('WARNING', 'wooExtraOptions'); ?></strong>: <?php _e('WooCommerce is not active and WooCommerce MDS Collivery Shipping Plugin will not work!', 'woocommerce-mds-shipping'); ?></p>
			</div><?php
			echo ob_get_clean();
		}
	}

	add_action('admin_notices', 'checkWooNotices');
}
