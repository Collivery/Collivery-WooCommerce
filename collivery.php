<?php

define('_MDS_DIR_', __DIR__);
define('MDS_VERSION', "2.7.0");
include('autoload.php');

/**
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.collivery.co.za/
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 2.7.0
 * Author: Bryce Large
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	register_activation_hook(__FILE__, 'activate_mds');

	if (!function_exists('activate_mds')) {
		/**
		 * When the plugin is installed we check if the mds collivery table exists and if not creates it.
		 */
		function activate_mds()
		{
			if ( ! class_exists( 'SoapClient' ) ) {
				deactivate_plugins( basename( __FILE__ ) );
				wp_die( 'Sorry, but you cannot run this plugin, it requires the <a href="http://php.net/manual/en/class.soapclient.php">SOAP</a> support on your server/hosting to function.' );
			}

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

	if (!function_exists('add_mds_shipping_method')) {
		/**
		 * Register Chipping Plugin with WooCommerce
		 *
		 * @param $methods
		 * @return array
		 */
		function add_mds_shipping_method($methods)
		{
			$methods['mds_collivery'] = 'WC_Mds_Shipping_Method';
			return $methods;
		}

		add_filter('woocommerce_shipping_methods', 'add_mds_shipping_method');
	}

	if (!function_exists('mds_collivery_cart_shipping_packages')) {
		/**
		 * WooCommerce caches pricing information.
		 * This adds our unique fields to the hash to update pricing cache when changed.
		 *
		 * @param $packages
		 * @return mixed
		 */
		function mds_collivery_cart_shipping_packages($packages)
		{
			/** @var \MdsSupportingClasses\MdsColliveryService $mds */
			$mds = MdsColliveryService::getInstance();
			$settings = $mds->returnPluginSettings();
			if ($settings['enabled'] == 'no' || !$defaults = $mds->returnDefaultAddress()) {
				return $packages;
			}

			$collivery = $mds->returnColliveryClass();
			$towns = $collivery->getTowns();
			$location_types = $collivery->getLocationTypes();
			$package = $mds->buildPackageFromCart(WC()->cart->get_cart());
			$cart = $mds->getCartContent($package);

			if(!is_array($cart) || !isset($cart['total'])) {
				return $packages;
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
				if (!isset($_POST['ship_to_different_address']) || $_POST['ship_to_different_address'] != TRUE) {
					if(isset($_POST['billing_location_type'])) {
						$to_town_type = $_POST['billing_location_type'];
					} else {
						return $packages;
					}
				}else {
					if(isset($_POST['shipping_location_type'])) {
						$to_town_type = $_POST['shipping_location_type'];
					} else {
						return $packages;
					}
				}
			}

			/** No need to go any further */
			if(!isset($to_town_type) || $to_town_type == '') {
				return $packages;
			}

			$package['cart'] = $cart;
			$package['method_free'] = $settings["method_free"];
			$package['free_min_total'] = $settings["free_min_total"];
			$package['free_local_only'] = $settings["free_local_only"];

			if (!isset($_POST['ship_to_different_address']) || $_POST['ship_to_different_address'] != TRUE) {
				$package['destination'] = array(
					"from_town_id" => (int) $defaults['address']['town_id'],
					"from_location_type" => (int) $defaults['address']['location_type'],
					"to_town_id" => (int) array_search($to_town_id, $towns),
					"to_location_type" => (int) array_search($to_town_type, $location_types),
					'country' => 'ZA',
					'state' => WC()->customer->get_state(),
					'postcode' => WC()->customer->get_postcode(),
					'city' => $to_town_id,
					'address' => WC()->customer->get_address(),
					'address_2' => WC()->customer->get_address_2()
				);
			} else {
				$package['destination'] = array(
					"from_town_id" => (int) $defaults['address']['town_id'],
					"from_location_type" => (int) $defaults['address']['location_type'],
					"to_town_id" => (int) array_search($to_town_id, $towns),
					"to_location_type" => (int) array_search($_POST['shipping_location_type'], $location_types),
					'country' => 'ZA',
					'state' => WC()->customer->get_shipping_state(),
					'postcode' => WC()->customer->get_shipping_postcode(),
					'city' => $to_town_id,
					'address' => WC()->customer->get_shipping_address(),
					'address_2' => WC()->customer->get_shipping_address_2()
				);
			}

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
					} else {
						$package['service'] = null;
						$package['local'] = 'no';
					}

					if($mds->validPackage($package)) {
						$packages[0] = $package;
					}
				} else {
					if($mds->validPackage($package)) {
						$packages[0] = $package;
					}
				}
			} else {
				if($mds->validPackage($package)) {
					$packages[0] = $package;
				}
			}

			return $packages;
		}

		add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
	}

	if (!function_exists('automated_add_collivery_payment_complete')) {
		/**
		 * Automatically send MDS Collivery the delivery request when payment gateways call $order->payment_complete()
		 *
		 * @param $order_id
		 * @return string
		 */
		function automated_add_collivery_payment_complete($order_id)
		{
			/** @var \MdsSupportingClasses\MdsColliveryService $mds */
			$mds = MdsColliveryService::getInstance();
			$settings = $mds->returnPluginSettings();

			if ($settings['enabled'] == 'yes' && $settings["toggle_automatic_mds_processing"] == 'yes') {
				$mds->automatedAddCollivery($order_id);
			}
		}

		add_action('woocommerce_payment_complete', 'automated_add_collivery_payment_complete');
	}

	if (!function_exists('automated_add_collivery_status_processing')) {
		/**
		 * Automatically send MDS Collivery the delivery request when status changes to processing for cod, eft's and cheque
		 *
		 * @param $order_id
		 * @return string
		 */
		function automated_add_collivery_status_processing($order_id)
		{
			/** @var \MdsSupportingClasses\MdsColliveryService $mds */
			$mds = MdsColliveryService::getInstance();
			$settings = $mds->returnPluginSettings();

			if ($settings['enabled'] == 'yes' && $settings["toggle_automatic_mds_processing"] == 'yes') {
				$mds->automatedAddCollivery($order_id, true);
			}
		}

		add_action( 'woocommerce_order_status_processing', 'automated_add_collivery_status_processing' );
	}

	if (!function_exists('mds_change_default_checkout_country')) {
		/**
		 * Sets South Africa as the default country during checkout
		 *
		 * @return string
		 */
		function mds_change_default_checkout_country()
		{
			return 'ZA';
		}

		add_filter('default_checkout_country', 'mds_change_default_checkout_country');
	}
}
