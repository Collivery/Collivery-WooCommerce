<?php

use MdsSupportingClasses\MdsColliveryService;

define('_MDS_DIR_', __DIR__);
define('MDS_VERSION', '3.5.1');
include 'autoload.php';

/*
 * Plugin Name: MDS Collivery
 * Plugin URI: https://collivery.net/integration/woocommerce
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 3.5.1
 * Author: MDS Technologies
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 * WC requires at least: 3.5
 * WC tested up to: 3.7.1
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    register_activation_hook(__FILE__, 'activate_mds');
	$mds = MdsColliveryService::getInstance();
	$settings = $mds->returnPluginSettings();


	if (!function_exists('activate_mds')) {
        /**
         * When the plugin is installed we check if the mds collivery table exists and if not creates it.
         */
        function activate_mds()
        {
            if (!class_exists('SoapClient')) {
                deactivate_plugins(basename(__FILE__));
                wp_die('Sorry, but you cannot run this plugin, it requires the <a href="http://php.net/manual/en/class.soapclient.php">SOAP</a> support on your server/hosting to function.');
            }

            if (!version_compare(phpversion(), '5.4.0', '>=')) {
                deactivate_plugins(basename(__FILE__));
                wp_die('Sorry, but you cannot run this plugin, it requires PHP version 5.4 or higher');
            }

            global $wpdb;

            // Creates our table to store our accepted deliveries
            $table_name = $wpdb->prefix.'mds_collivery_processed';
            $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `waybill` int(11) NOT NULL,
                `order_id` int(11) NOT NULL,
                `validation_results` TEXT NOT NULL,
                `status` int(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            );";

            $wpdb->query($sql);

            add_option('mds_db_version', MDS_VERSION);
        }
    }

    add_action('plugins_loaded', 'init_mds_collivery', 0);

    /**
     * Instantiate the plugin.
     */
    function init_mds_collivery()
    {
        // Check if 'WC_Shipping_Method' class is loaded, else exit.
        if (!class_exists('WC_Shipping_Method')) {
            return;
        }

         require_once('WC_Mds_Shipping_Method.php');
         require_once('mds_admin.php'); // Admin scripts
         require_once('mds_checkout_fields.php'); // Checkout fields.

        /**
         * Load JS file throughout.
         */
        function load_js()
        {
            wp_register_script('mds_js', plugins_url('script.js', __FILE__), array('jquery'), MDS_VERSION);
            wp_enqueue_script('mds_js');
        }

	    $mds = MdsColliveryService::getInstance();
	    if ($mds->isEnabled()) {
		    add_action( 'wp_enqueue_scripts', 'load_js' );
	    }
        /*
         * Check for updates
         */
        if (is_admin()) {
            new GitHubPluginUpdater(__FILE__, 'Collivery', 'Collivery-WooCommerce');
        }
    }

    if (!function_exists('add_mds_shipping_method')) {
        /**
         * Register Shipping Plugin with WooCommerce.
         *
         * @param $methods
         *
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
         *
         * @return mixed
         */
        function mds_collivery_cart_shipping_packages($packages)
        {
            $shippingPackage = new \MdsSupportingClasses\ShippingPackageData();

            return $shippingPackage->build($packages, $_POST);
        }

        add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
    }

    if (!function_exists('automated_add_collivery_payment_complete')) {
        /**
         * Automatically send MDS Collivery the delivery request when payment gateways call $order->payment_complete().
         *
         * @param $order_id
         *
         * @return string
         */
        function automated_add_collivery_payment_complete($order_id)
        {
	        return MdsColliveryService::getInstance()->automatedAddCollivery($order_id);
        }

            if ($mds->isEnabled() && $settings->getValue('toggle_automatic_mds_processing') == 'yes') {
	            add_action( 'woocommerce_payment_complete', 'automated_add_collivery_payment_complete' );
            }
    }

    if (!function_exists('automated_add_collivery_status_processing')) {
        /**
         * Automatically send MDS Collivery the delivery request when status changes to processing for cod, eft's and cheque.
         *
         * @param $order_id
         *
         * @return bool|null
         */
        function automated_add_collivery_status_processing($order_id)
        {
	        return MdsColliveryService::getInstance()->automatedAddCollivery($order_id, true);
        }

        if ($mds->isEnabled() && $settings->getValue('toggle_automatic_mds_processing') == 'yes') {
	        add_action('woocommerce_order_status_processing', 'automated_add_collivery_status_processing');
        }
    }

    if (!function_exists('mds_change_default_checkout_country')) {
        /**
         * Sets South Africa as the default country during checkout.
         *
         * @return string
         */
        function mds_change_default_checkout_country()
        {
            return 'ZA';
        }

	    if ($mds->isEnabled()) {
		    add_filter( 'default_checkout_billing_country', 'mds_change_default_checkout_country' );
	    }
    }

    if (!function_exists('mds_show_my_account_address_suburb')) {
        function mds_show_my_account_address_suburb($address, $id, $type)
        {
            $suburb = get_user_meta($id, "{$type}_suburb", true);
            $address['city'] = "$suburb, $address[city]";

            return $address;
        }

	    add_filter('woocommerce_my_account_my_address_formatted_address', 'mds_show_my_account_address_suburb', 10, 3);
    }
}
