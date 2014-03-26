<?php

/**
 * @package MDS Collivery
 * @version 0.1
 */
/*
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.coffeecode.co.za/
 *
 * Description: Plugin to add support for MDS Collivery to WooCommerce
 * Author: Bernhard Breytenbach
 * Version: 0.1
 * Author URI: http://www.coffeecode.co.za/
 */

// Our versions
global $wp_version;
global $mds_db_version;
$mds_db_version = "1.0";

add_action('plugins_loaded', 'init_mds_collivery', 0);

function init_mds_collivery()
{
	// Check if 'WC_Shipping_Method' class is loaded, else exit.
	if (!class_exists('WC_Shipping_Method'))
		return;

	include_once( 'checkout_fields.php' ); //Seperate file with large arrays.
	require_once( 'mds-admin.php' ); //Admin Scripts

	add_action('wp_enqueue_scripts', 'load_js');

	//Load JS file
	function load_js()
	{
		wp_register_script('mds_js', plugins_url('script.js', __FILE__), array('jquery'));
		wp_enqueue_script('mds_js');
	}

	class WC_MDS_Collivery extends WC_Shipping_Method {

		var $towns;
		var $services;
		var $location_types;
		var $extension_id;
		var $collivery;
		var $adresses;
		var $default_address_id;
		var $default_contacts;
		var $mds_services;
		var $risk_cover;
		var $converter;

		function __construct()
		{
			$this->id = 'mds_collivery';
			$this->method_title = __('MDS Collivery', 'woocommerce');

			$this->admin_page_heading = __('MDS Collivery', 'woocommerce');
			$this->admin_page_description = __('Seamlessly integrate your website with MDS Collivery', 'woocommerce');

			add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));
			$this->init();

			$config = array(
				'app_name' => 'Shipping by MDS Collivery for WooCommerce', // Application Name
				'app_version' => $mds_db_version, // Application Version
				'app_host' => 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . $this->wpbo_get_woo_version_number(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
				'app_url' => get_site_url(), // URL your site is hosted on
				'user_email' => $this->mds_user,
				'user_password' => $this->mds_pass
			);

			// Use the MDS API Files
			require_once 'Mds/Cache.php';
			require_once 'Mds/Collivery.php';
			$this->collivery = new Mds\Collivery($config);

			// Get some information from the API
			$this->towns = $this->collivery->getTowns();
			$this->services = $this->collivery->getServices();
			$this->location_types = $this->collivery->getLocationTypes();
			$this->addresses = $this->collivery->getAddresses();
			$this->default_address_id = $this->collivery->getDefaultAddressId();
			$this->default_contacts = $this->collivery->getContacts($this->default_address_id);
			$this->mds_services = $this->collivery->getServices();

			// Class for converting lengths and weights
			require_once 'UnitConvertor.php';
			$this->converter = new UnitConvertor();
		}

		function init()
		{
			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->enabled = $this->settings['enabled'];
			$this->title = $this->settings['title'];

			// MDS Specific Values
			$this->mds_user = $this->settings['mds_user'];
			$this->mds_pass = $this->settings['mds_pass'];
			$this->markup = $this->settings['markup'];
		}

		public function getColliveryClass()
		{
			return $this->collivery;
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

		/*
		 * Plugin Settings
		 */

		function init_form_fields()
		{
			global $woocommerce;
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enabled?', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enable this shipping method', 'woocommerce'),
					'default' => 'yes',
				),
				'title' => array(
					'title' => __('Method Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => __('MDS Collivery', 'woocommerce'),
				),
				'mds_user' => array(
					'title' => "MDS " . __('Username', 'woocommerce'),
					'type' => 'text',
					'description' => __('Email address associated with your MDS account.', 'woocommerce'),
					'default' => "demo@collivery.co.za",
				),
				'mds_pass' => array(
					'title' => "MDS " . __('Password', 'woocommerce'),
					'type' => 'text',
					'description' => __('The password used when logging in to MDS.', 'woocommerce'),
					'default' => "demo",
				),
				'markup' => array(
					'title' => __('Markup', 'woocommerce'),
					'type' => 'text',
					'description' => __('Charge clients x% more for shipping. Negative (-) numbers will decrease the cost.', 'woocommerce'),
					'default' => 30,
				),
			);
		}

		function calculate_shipping($package = array())
		{
			$cart = $this->get_cart_content($package);
			$default_address = $this->collivery->getAddress($this->default_address_id);
			$default_contacts = $this->collivery->getContacts($this->default_address_id);
			die(print_r(WC()->address));

			// Now lets get the price for
			$data = array(
				"from_town_id" => $this->default_address_id,
				"from_town_type" => $default_address['location_type'],
				"to_town_id" => $to_town_id,
				"to_town_type" => $to_town_type,
				"num_package" => $tot_parcel,
				"service" => $mds_service,
				"parcels" => $parcels,
				"exclude_weekend" => 1,
				"cover" => $this->risk_cover
			);

			$price = $this->collivery->getPrice($data);

			// Get pricing for each service
			foreach ($this->mds_services as $id => $title) {
				$rate = array(
					'id' => $this->id . '_' . $id,
					'label' => $this->title . ' - ' . $title,
					'cost' => ($this->get_shipping_estimate(
						$town_brief, $town_type, $id, $cart, $collection_time, $delivery_time
					) * (1 + ($this->markup / 100))),
				);
				if ($rate['cost'] > 0) {
					$this->add_rate($rate); //Only add shipping if it has a value
				}
			}
		}

		function get_cart_content($package)
		{
			if (sizeof($package['contents']) > 0)
			{
				//Reset array to defaults
				$this->cart = array(
					'count' => 0,
					'weight' => 0,
					'max_weight' => 0,
					'products' => Array()
				);

				foreach ($package['contents'] as $item_id => $values)
				{
					$_product = $values['data']; // = WC_Product class
					$qty = $values['quantity'];

					$this->cart['count'] += $qty;
					$this->cart['weight'] += $_product->get_weight() * $qty;

					// Work out Volumetric Weight based on MDS's calculations
					$vol_weight = (($_product->length * $_product->width * $_product->height) / 4000);

					if ($vol_weight > $_product->get_weight()) {
						$this->cart['max_weight'] += $vol_weight * $qty;
					} else {
						$this->cart['max_weight'] += $_product->get_weight() * $qty;
					}

					for ($i = 0; $i < $qty; $i++) {
						// Length coversion, mds collivery only acceps CM
						if (strtolower(get_option('woocommerce_dimension_unit')) != 'çm') {
							$length = $this->converter->convert($_product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
							$width = $this->converter->convert($_product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
							$height = $this->converter->convert($_product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
						} else {
							$length = $_product->length;
							$width = $_product->width;
							$height = $_product->height;
						}

						// Weight coversion, mds collivery only acceps KG'S
						if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
							$weight = $this->converter->convert($_product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
						} else {
							$weight = $_product->get_weight();
						}

						$this->cart['products'][] = array(
							'length' => $length,
							'width' => $width,
							'height' => $height,
							'weight' => $weight
						);
					}
				}
			}
			return $this->cart;
		}

		/*
		 * Get a shipping estimate from MDS based on current data.
		 */
		function get_shipping_estimate($town_brief, $town_type, $service_type, $cart, $collection_time, $delivery_time)
		{
			$my_address = $this->my_address();
			$data = array(
				'from_town_brief' => $my_address['results']['TownBrief'],
				'from_town_type' => $my_address['results']['CP_Type'],
				'to_town_brief' => $town_brief,
				'service_type' => $service_type,
				'mds_cover' => true,
				'weight' => $cart['max_weight'],
			);

			if ((isset($town_type)) && ($town_type != ""))
				$data['to_town_type'] = $town_type;

			if ((isset($collection_time)) && ($collection_time != 0))
				$data['collection_time'] = $collection_time;
			if ((isset($delivery_time)) && ($delivery_time != 0))
				$data['delivery_time'] = $delivery_time;

			$pricing = $this->collivery->getPrice($data);

			return $pricing['price']['ex_vat'];
		}

		/*
		 * Get list of Suburbs from MDS
		 */

		public function get_subs($town)
		{
			$town_code = $this->get_code($this->get_towns(), $town);

			if (!isset($this->subs[$town_code])) {
				$this->subs[$town_code] = $this->collivery->getSuburbs($town_code);
			}
			return $this->subs[$town_code]['results'];
		}

		/*
		 * Get Town and Location Types for Checkout Dropdown's from MDS
		 */

		public function get_field_defaults()
		{
			return Array('towns' => $this->towns, 'location_types' => $this->location_types);
		}

		/*
		 * Get array key from label
		 */

		function get_code($array, $label)
		{
			foreach ($array as $key => $value) {
				if ($label == $value) {
					return $key;
				}
			}
			return false;
		}

		/*
		 * Bunch of MDS Functions
		 */

		public function addAddress($address)
		{
			return $this->collivery->addAddress($address);
		}

		public function addContact($contact)
		{
			return $ctid = $this->collivery->addContact($contact);
		}

		public function validate($data)
		{
			$validation = $this->collivery->validate($data);
			return $validation;
		}

		public function register_shipping($data)
		{
			$new_collivery = $this->collivery->addCollivery($data);
			if ($new_collivery['results']) {
				$this->collivery_id = $new_collivery['collivery_id'];
				$send_emails = 1;
				$this->collivery->acceptCollivery($this->collivery_id);
			}
			return $new_collivery;
		}

		public function my_address()
		{
			if (!isset($this->my_address)) {
				$default_address_id = $this->authenticate['DefaultAddressID'];
				$this->my_address = $this->get_client_address($default_address_id);
				$this->my_address['address_id'] = $default_address_id;
			}
			return $this->my_address;
		}

		public function my_contact()
		{
			if (!isset($this->my_contact)) {
				$address = $this->my_address();
				$this->my_contact = $this->get_client_contact($address['address_id']);
				$first_contact_id = each($this->my_contact['results']);
				$this->my_contact['contact_id'] = $first_contact_id[0];
			}
			return $this->my_contact;
		}

		public function get_client_address($cpid)
		{
			if (!isset($this->client_address[$cpid])) {
				$this->client_address[$cpid] = $this->collivery->getAddress($cpid);
			}
			return $this->client_address[$cpid];
		}

		public function get_client_contact($ctid)
		{
			if (!isset($this->client_contact[$ctid])) {
				$this->client_contact[$ctid] = $this->collivery->getContacts($ctid);
			}
			return $this->client_contact[$ctid];
		}

	}

	// End MDS_Collivery Class
}

// End init_mds_collivery()

/*
 * Register Plugin with WooCommerce
 */

function add_MDS_Collivery_method($methods)
{
	$methods[] = 'WC_MDS_Collivery';
	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_MDS_Collivery_method');

/*
 * WooCommerce caches pricing information.
 * This adds cptypes to the hash to update pricing cache when changed.
 */

//function mds_collivery_cart_shipping_packages($packages)
//{
//	if (isset($_POST['post_data'])) {
//		parse_str($_POST['post_data'], $post_data);
//		$cptypes = $post_data['billing_cptypes'] . $post_data['shipping_cptypes'];
//	} else if (isset($_POST['billing_cptypes']) || isset($_POST['shipping_cptypes'])) {
//		$cptypes = $_POST['billing_cptypes'] . $_POST['shipping_cptypes'];
//	} else {
//		//Bad Practice... But incase cptypes isn't set, do not cache the order!
//		//@TODO: Find a way to fix this
//		$cptypes = rand(0, 999999999999999999) . rand(0, 999999999999999999) . rand(0, 999999999999999999) . rand(0, 999999999999999999);
//	}
//	$packages[0]['destination']['cptypes'] = $cptypes;
//	return $packages;
//}
//
//add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
//
///*
// * Save custom shipping fields to order
// */
//
//function mds_collivery_checkout_update_order_meta($order_id)
//{
//	if ($_POST['shiptobilling'] == true) {
//		if ($_POST['billing_cptypes'])
//			update_post_meta($order_id, 'mds_cptypes', esc_attr($_POST['billing_cptypes']));
//		if ($_POST['billing_building_details'])
//			update_post_meta($order_id, 'mds_building', esc_attr($_POST['billing_building_details']));
//	} else {
//		if ($_POST['shipping_cptypes'])
//			update_post_meta($order_id, 'mds_cptypes', esc_attr($_POST['shipping_cptypes']));
//		if ($_POST['shipping_building_details'])
//			update_post_meta($order_id, 'mds_building', esc_attr($_POST['shipping_building_details']));
//	}
//}
//
//add_action('woocommerce_checkout_update_order_meta', 'mds_collivery_checkout_update_order_meta');