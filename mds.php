<?php

/**
 * Plugin Name: MDS Collivery
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Our versions
global $wp_version;
global $mds_db_version;
$mds_db_version = "1.0";

// Standard calls so we only make them once
global $towns;
global $services;
global $location_types;
global $collivery;
init();

// Init our API so we can use it throughout our plugin
function init()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mds_collivery_config';

	$config = $wpdb->get_results("SELECT * FROM `" . $table_name . "` WHERE id=1;", OBJECT);

	$config = array(
		'app_name' => 'Shipping by MDS Collivery for WooCommerce', // Application Name
		'app_version' => $mds_db_version, // Application Version
		'app_host' => 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . wpbo_get_woo_version_number(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
		'app_url' => get_site_url(), // URL your site is hosted on
		'user_email' => $config[0]->username,
		'user_password' => $config[0]->password
	);

	// Use the MDS API Files
	require_once 'Mds/Cache.php';
	require_once 'Mds/Collivery.php';
	$collivery = new Mds\Collivery($config);

	// Get some information from the API
	$towns = $collivery->getTowns();
	$services = $collivery->getServices();
	$location_types = $collivery->getLocationTypes();
}

add_action('admin_menu', 'adminMenu'); // Add our Admin menu items
register_activation_hook(__FILE__, 'mdsInstall'); // Install Hook
register_deactivation_hook(__FILE__, 'mdsUninstall'); // Uninstall Hook

// Function to register our functions as pages from our admin menu
function adminMenu()
{
	add_submenu_page('woocommerce', 'MDS Settings', 'MDS Settings', 'manage_options', 'mds-settings', 'mdsSettings');
	$firt_page = add_submenu_page('woocommerce', 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds-already-confirmed', 'mdsConfirmedIndex');
	add_submenu_page($firt_page, 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds_confirmed', 'mdsConfirmed');
}

// Function used to display index of all our deliveries already accepted and sent to MDS Collivery
// Still need to add a filter to this page
function mdsConfirmedIndex()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mds_collivery_processed';

	$colliveries = $wpdb->get_results("SELECT * FROM `" . $table_name . "` WHERE status=1;", OBJECT);

	include 'views/index.php';
}

// View our Collivery once it has been accepted
function mdsConfirmed()
{
	echo 'test';
	// still have to build this function but lets first complete all the rest then we can add colliveries in order to test
}

// Our settings function
// Still need to work on some basic styles for this page
function mdsSettings()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mds_collivery_config';

	// If we posted settings then lets save them
	$post = $_POST;
	if (!empty($post)) {
		// change our password
		$wpdb->query("UPDATE `" . $table_name . "` SET `password` = '" . $post['password'] . "', `username` = '" . $post['username'] . "', `risk_cover` = '" . $post['risk_cover'] . "' WHERE `id` = 1;");
	}

	$config = $wpdb->get_results("SELECT * FROM `" . $table_name . "` WHERE id=1;", OBJECT);

	include 'views/config.php';
}

// Install
// This function is complete
function mdsInstall()
{
	global $wpdb;
	global $mds_db_version;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	mkdir(getcwd().'/cache'); // Make this directory for our cache class

	foreach (array('mds_collivery_processed', 'mds_collivery_config') as $table) {
		$table_name = $wpdb->prefix . $table;
		if ($table == 'mds_collivery_config') {
			$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`password` varchar(55) NOT NULL,
				`username` varchar(55) NOT NULL,
				`risk_cover` int(1) NOT NULL,
				PRIMARY KEY (`id`)
			);";
			dbDelta($sql);

			$data = array(
				'password' => 'demo',
				'username' => 'demo@collivery.co.za',
				'risk_cover' => '1'
			);
			$rows_affected = $wpdb->insert($table_name, $data);
		} else {
			$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`waybill` int(11) NOT NULL,
				`validation_results` TEXT NOT NULL,
				`status` int(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`id`)
			);";
			dbDelta($sql);
		}
	}

	add_option("mds_db_version", $mds_db_version);
}

// Uninstall
// This function is complete
function mdsUninstall()
{
	global $wpdb;
	delete_files(getcwd().'/cache', TRUE);
	@rmdir(getcwd().'/cache'); // Remove our cache directory

	foreach (array('mds_collivery_processed', 'mds_collivery_config') as $table) {
		$table_name = $wpdb->prefix . $table;
		$wpdb->query("DROP TABLE IF EXISTS `$table_name`");
	}

	delete_option('mds_db_version');
}

// This function is here so we can get WooCommerce version number to pass on to the API for logs
function wpbo_get_woo_version_number()
{
	// If get_plugins() isn't available, require it
	if (!function_exists('get_plugins')) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	// Create the plugins folder and file variables
	$plugin_folder = get_plugins('/' . 'woocommerce');
	$plugin_file = 'woocommerce.php';

	// If the plugin version number is set, return it 
	if (isset($plugin_folder[$plugin_file]['Version'])) {
		return $plugin_folder[$plugin_file]['Version'];
	} else {
		// Otherwise return null
		return NULL;
	}
}

// This function is here to delete our cache folder which we created during install
function delete_files($path, $del_dir = false, $level = 0, $dst = false)
{
	// Trim the trailing slash
	$path = rtrim($path, DIRECTORY_SEPARATOR);
	if ($dst) {
		$dst = rtrim($dst, DIRECTORY_SEPARATOR);
	}

	if (!$current_dir = @opendir($path)) {
		return false;
	}

	while (false !== ( $filename = @readdir($current_dir) )) {
		if ($filename != "." and $filename != "..") {
			if (is_dir($path . DIRECTORY_SEPARATOR . $filename)) {
				// Ignore empty folders
				if (substr($filename, 0, 1) != '.') {
					if ($dst) {
						delete_files($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $level + 1, $dst . DIRECTORY_SEPARATOR . $filename);
					} else {
						delete_files($path . DIRECTORY_SEPARATOR . $filename, $del_dir, $level + 1);
					}
				}
			} else {
				if ($dst) {
					unlink($dst . DIRECTORY_SEPARATOR . $filename);
				} else {
					unlink($path . DIRECTORY_SEPARATOR . $filename);
				}
			}
		}
	}
	@closedir($current_dir);

	if ($del_dir == true and $level > 0) {
		if ($dst) {
			return @rmdir($dst);
		} else {
			return @rmdir($path);
		}
	}

	return true;
}
