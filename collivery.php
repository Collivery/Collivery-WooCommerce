<?php

/**
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.collivery.co.za/
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 2.0.1
 * Author: Bryce Large
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 */

/**
 * Register Install function
 */
register_activation_hook(__FILE__, 'install');

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

	add_option("mds_db_version", "2.0.1");
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
 * This adds location_type to the hash to update pricing cache when changed.
 *
 * @param $packages
 * @return mixed
 */
function mds_collivery_cart_shipping_packages($packages)
{
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->get_collivery_class();

	if (isset($_POST['post_data'])) {
		parse_str($_POST['post_data'], $post_data);
		$packages[0]['destination']['location_type'] = $post_data['billing_location_type'] . $post_data['shipping_location_type'];
	} else if (isset($_POST['billing_location_type']) || isset($_POST['shipping_location_type'])) {
		$packages[0]['destination']['location_type'] = (isset($_POST['billing_location_type']) && $_POST['shipping_location_type']) ? ($_POST['shipping_location_type']) : ($_POST['billing_location_type']);
	} else {
		//Bad Practice... But incase location_type isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$packages[0]['destination']['location_type'] = rand(0, 999999999999999999) . '-' . rand(0, 999999999999999999) . rand(0, 999999999999999999) . rand(0, 999999999999999999);
	}

	if (isset($_POST['post_data'])) {
		parse_str($_POST['post_data'], $post_data);
		$packages[0]['destination']['town'] = $post_data['billing_state'] . $post_data['shipping_state'];
	} else if (isset($_POST['billing_state']) || isset($_POST['shipping_state'])) {
		$packages[0]['destination']['town'] = (isset($_POST['shipping_state']) && $_POST['shipping_state']) ? ($_POST['billing_state']) : ($_POST['billing_location_type']);
	} else {
		//Bad Practice... But incase town isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$packages[0]['destination']['town'] = rand(0, 999999999999999999) . '-' . rand(0, 999999999999999999) . rand(0, 999999999999999999) . rand(0, 999999999999999999);
	}

	return $packages;
}

add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
