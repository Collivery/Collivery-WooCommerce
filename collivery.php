<?php

use MdsSupportingClasses\MdsColliveryService;
use MdsSupportingClasses\ShippingPackageData;

define('_MDS_DIR_', __DIR__);

define('MDS_VERSION', '4.4.7');

include 'autoload.php';
require_once ABSPATH.'wp-includes/functions.php';
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/*
 * Plugin Name: MDS Collivery
 * Plugin URI: https://collivery.net/integration/woocommerce
 * Description: Plugin to add support for MDS Collivery in WooCommerce.

 * Version: 4.4.7

 * Author: MDS Technologies
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 * Requires PHP: 7.4.0
 * Requires at least: 5.9.8
 * Tested up to: 8.2.13
 * WC requires at least: 6.9.4
 * WC tested up to: 8.5.1
 */
if( is_plugin_active('woocommerce/woocommerce.php')) {
    register_activation_hook(__FILE__, 'activate_mds');
    $mds = MdsColliveryService::getInstance();
    $settings = $mds->returnPluginSettings();

    add_action('before_woocommerce_init', function(){
        if ( class_exists( FeaturesUtil::class ) ) {
            FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    });

    if (!function_exists('activate_mds')) {
        /**
         * When the plugin is installed we check if the mds collivery table exists and if not creates it.
         */
        function activate_mds()
        {
            if (!version_compare(phpversion(), '7.0.0', '>=')) {
                deactivate_plugins(basename(__FILE__));
                wp_die('Sorry, but you cannot run this plugin, it requires PHP version 7.0.0 or higher');
            }

            global $wpdb;

            if(is_multisite()) {

                $blogs = get_sites();

                foreach ($blogs as $blog) {

                    switch_to_blog($blog->blog_id);
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

                    restore_current_blog();
                }

            } else {
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
            }



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
            wp_register_script('mds_js', plugins_url('script.js', __FILE__), ['jquery'], MDS_VERSION);
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
         * @return array
         */
        function mds_collivery_cart_shipping_packages($packages)
        {
            return (new ShippingPackageData())->build($packages, $_POST);
        }

        add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
    }

    if ($mds->isEnabled()) {
        if (!function_exists('mds_collivery_new_order')) {
            /**
             * Add the address fields to the order
             * $order_id is generated by WooCommerce and does not contain the suburb/town names,
             * use id's to get the names
             *
             * @param string $postString
             *
             * @return void
             * @throws WC_Data_Exception
             */
            function mds_collivery_new_order($order_id)
            {
                $mds = MdsColliveryService::getInstance();

                $order = new WC_Order($order_id);

                $billingSuburb = $order->get_meta('_billing_suburb');
                $shippingSuburb = $order->get_meta('_shipping_suburb');

                $billingCity = $order->get_billing_city();
                $shippingCity = $order->get_shipping_city();


                if (is_numeric($billingSuburb)) {
                    $result = $mds->getSuburb($billingSuburb);
                    $billingSuburb = $result['name'];
                    $billingCity = $result['town']['name'];
                }
                if (wc_ship_to_billing_address_only()) {
                    $shippingSuburb = $billingSuburb;
                    $shippingCity = $billingCity;
                }
                if (is_numeric($shippingSuburb)) {
                    $result = $mds->getSuburb($shippingSuburb);
                    $shippingSuburb = $result['name'];
                    $shippingCity = $result['town']['name'];
                }

                $order->update_meta_data('_billing_suburb', $billingSuburb);
                $order->update_meta_data('_shipping_suburb', $shippingSuburb);
                $order->save_meta_data();
                $order->set_shipping_city($shippingCity);
                $order->set_billing_city($billingCity);
                $order->save();

            }

            add_action('woocommerce_new_order', 'mds_collivery_new_order', 1, 1);

        }
    }

    if (!function_exists('mds_collivery_checkout_update_order_review')) {
        /**
         * Add the suburb fields to the customer
         * $_POST is generated by WooCommerce and does not contain the suburb data,
         * use the raw string instead
         *
         * @param string $postString
         *
         * @return void
         */
        function mds_collivery_checkout_update_order_review($postString)
        {

            $customer = WC()->customer;
            $postData = wp_parse_args($postString);
            $mds = MdsColliveryService::getInstance();
            $billingSuburb = isset($postData['billing_suburb']) ?
                wc_clean(wp_unslash($postData['billing_suburb'])) :
                null;

            if(!empty($billingSuburb) && !is_numeric($billingSuburb)) {
                $town          = wc_clean(wp_unslash($postData['billing_city']));
                $billingSuburb = $mds->searchSuburbByName($town, $billingSuburb);
            }

            if (wc_ship_to_billing_address_only()) {
                $shippingSuburb = $billingSuburb;
            } else {
                $shippingSuburb = isset($postData['shipping_suburb']) ?
                    wc_clean(wp_unslash($postData['shipping_suburb'])) :
                    null;

                if(!empty($shippingSuburb) && !is_numeric($shippingSuburb)) {
                    $town           = wc_clean(wp_unslash($postData['shipping_city']));
                    $shippingSuburb = $mds->searchSuburbByName($town, $shippingSuburb);
                }
            }

            if($customer->meta_exists('billing_suburb')) {
                $customer->update_meta_data('billing_suburb', $billingSuburb);
            } else {
                $customer->add_meta_data('billing_suburb', $billingSuburb);
            }

            if($customer->meta_exists('shipping_suburb')) {
                $customer->update_meta_data('shipping_suburb', $shippingSuburb);
            } else {
                $customer->add_meta_data('shipping_suburb', $shippingSuburb);
            }

            $customer->save_meta_data();
        }

        add_action('woocommerce_checkout_update_order_review', 'mds_collivery_checkout_update_order_review');
    }

    if (!function_exists('automated_add_collivery_payment_complete')) {
        /**
         * Automatically send MDS Collivery the delivery request when payment gateways call $order->payment_complete().
         *
         * @param $order_id
         *
         * @return void
         */
        function automated_add_collivery_payment_complete($order_id)
        {
            MdsColliveryService::getInstance()->automatedOrderToCollivery($order_id);
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
         * @return void
         */
        function automated_add_collivery_status_processing($order_id)
        {
            MdsColliveryService::getInstance()->automatedOrderToCollivery($order_id, true);
        }

        if ($mds->isEnabled() && $settings->getValue('toggle_automatic_mds_processing') == 'yes') {
            add_action('woocommerce_order_status_changed', 'automated_add_collivery_status_processing');
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
} else {
    add_action( 'admin_notices', 'need_woocommerce' );
}

function need_woocommerce() {
    $error = sprintf( __( 'The plugin requires WooCommerce. Please install and activate the  %sWooCommerce%s plugin. ' , 'foo' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>' );
    $message = '<div class="error"><p>' . $error . '</p></div>';
    echo $message;
}
