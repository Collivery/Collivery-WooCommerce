<?php

define('_MDS_DIR_', __DIR__);
define('MDS_VERSION', "2.1.8");
include('autoload.php');

/**
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.collivery.co.za/
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 2.1.8
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
		global $wpdb;

		// Creates our table to store our accepted deliveries
		$table_name = $wpdb->prefix . 'mds_collivery_processed';
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`waybill` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
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
		global $wpdb;

		// Check if 'WC_Shipping_Method' class is loaded, else exit.
		if (!class_exists('WC_Shipping_Method')) {
			return;
		}

		// If this plugin is sub 2.0.1 then alter the mds table to include the order_id column
		$testForColumn = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "mds_collivery_processed", ARRAY_A);
		if(!empty($testForColumn) && !array_key_exists('order_id', $testForColumn)) {
			$wpdb->query("ALTER TABLE " . $wpdb->prefix . "mds_collivery_processed ADD order_id INT NULL AFTER id");
		}

		require_once('WC_Mds_Shipping_Method.php');

		require_once('mds_admin.php'); // Admin scripts
		require_once('mds_checkout_fields.php'); // Checkout fields.

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
		if(is_admin()) {
			new GitHubPluginUpdater( __FILE__, 'Collivery', "Collivery-WooCommerce" );
		}
	}

	/**
	 * Register Chipping Plugin with WooCommerce
	 *
	 * @param $methods
	 * @return array
	 */
	function add_mds_shipping_method($methods)
	{
		$methods[] = 'WC_Mds_Shipping_Method';
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
		$mds = MdsColliveryService::getInstance();
		$settings = $mds->returnPluginSettings();
		if ($settings['enabled'] == 'no' || !$defaults = $mds->returnDefaultAddress()) {
			return false;
		}

		$collivery = $mds->returnColliveryClass();

		$towns = $collivery->getTowns();
		$location_types = $collivery->getLocationTypes();

		$package = $mds->buildPackageFromCart(WC()->cart->get_cart());

		$cart = $mds->getCartContent($package);

		$use_location_type = WC()->session->get('use_location_type', null);
		$location_type = array(
			'shipping_location_type' => WC()->session->get('shipping_location_type', null),
			'billing_location_type' => WC()->session->get('billing_location_type', null),
		);

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
				$response = $collivery->getPrice($data);
				if(isset($response['delivery_type']) && $response['delivery_type'] == 'local') {
					$package['local'] = 'yes';
					if($mds->validPackage($package)) {
						$packages[0] = $package;
					} else {
						return false;
					}
				} else {
					 $package['local'] = 'no';
				}
			} else {
				if($mds->validPackage($package)) {
					$packages[0] = $package;
				} else {
					return false;
				}
			}
		} else {
			if($mds->validPackage($package)) {
				$packages[0] = $package;
			} else {
				return false;
			}
		}

		return $packages;
	}

	add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');

	/**
	 * Automatically send MDS Collivery the delivery request when payment gateways call $order->payment_complete()
	 *
	 * @param $order_id
	 * @return string
	 */
	function automated_add_collivery_payment_complete($order_id)
	{
		$mds = MdsColliveryService::getInstance();
		$settings = $mds->returnPluginSettings();

		if ($settings['enabled'] == 'yes' && $settings["toggle_automatic_mds_processing"] == 'yes') {
			$mds->automatedAddCollivery($order_id);
		}
	}

	add_action('woocommerce_payment_complete', 'automated_add_collivery_payment_complete');

	/**
	 * Automatically send MDS Collivery the delivery request when status changes to processing for cod, eft's and cheque
	 *
	 * @param $order_id
	 * @return string
	 */
	function automated_add_collivery_status_processing($order_id)
	{
		$mds = MdsColliveryService::getInstance();
		$settings = $mds->returnPluginSettings();

		if ($settings['enabled'] == 'yes' && $settings["toggle_automatic_mds_processing"] == 'yes') {
			$order = new WC_Order($order_id);

			if($order->payment_method === "cod" || $order->payment_method === "cheque" || $order->payment_method === "bacs"){
				$mds->automatedAddCollivery($order_id, true);
			}
		}
	}

	add_action( 'woocommerce_order_status_processing', 'automated_add_collivery_status_processing' );
}
