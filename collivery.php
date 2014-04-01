<?php

/**
 * @package MDS Collivery
 * @version 1.0
 * Plugin Name: MDS Collivery
 * Description: Plugin to add support for MDS Collivery to WooCommerce
 */

// Our versions
global $wp_version;
global $mds_db_version;
$mds_db_version = "1.0";

add_action('plugins_loaded', 'init_mds_collivery', 0);

function init_mds_collivery()
{
	// Check if 'WC_Shipping_Method' class is loaded, else exit.
	if (!class_exists('WC_Shipping_Method')) {
		return;
	}

	include_once( 'checkout_fields.php' ); //Seperate file with large arrays.
	require_once( 'mds-admin.php' ); //Admin Scripts

	//Load JS file
	add_action('wp_enqueue_scripts', 'load_js');
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

		public function __construct()
		{
			$this->id = 'mds_collivery';
			$this->method_title = __('MDS Collivery', 'woocommerce');

			$this->admin_page_heading = __('MDS Collivery', 'woocommerce');
			$this->admin_page_description = __('Seamlessly integrate your website with MDS Collivery', 'woocommerce');

			add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));

			$config = array(
				'app_name' => 'Shipping by MDS Collivery for WooCommerce', // Application Name
				'app_version' => "2.0", // Application Version
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
			
			$this->init();
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

		public function getColliverySettings()
		{
			return $this->settings;
		}
		
		public function getDefaulsAddress()
		{
			return array('address' => $this->collivery->getAddress($this->default_address_id), 'contacts' => $this->collivery->getContacts($this->default_address_id));
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
		public function init_form_fields()
		{
			global $woocommerce;
			$fields = array(
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
				'risk_cover' => array(
					'title' => "MDS " . __('Insurance', 'woocommerce'),
					'type' => 'checkbox',
					'description' => __('Insurance up to a maximum of R5000.', 'woocommerce'),
					'default' => '1',
					'checked' => 'checked',
				),				
			);
			
			foreach ($this->mds_services as $id => $title) {
				$fields['method_'.$id] = array(
					'title' => __($title.': Enabled', 'woocommerce'),
					'type' => 'checkbox',
					'default' => '1',
					'checked' => 'checked',
				);
				$fields['markup_'.$id] = array(
					'title' => __($title.': Markup', 'woocommerce'),
					'type' => 'text',
					'default' => '10',
				);
			}
			
			$this->form_fields = $fields;
		}

		function calculate_shipping($package = array())
		{
			// Get our default address
			$default_address = $this->collivery->getAddress($this->default_address_id);
			$default_contacts = $this->collivery->getContacts($this->default_address_id);

			// Capture the correct Town and location type
			if (isset($_POST['post_data'])) {
				parse_str($_POST['post_data'], $post_data);
				if (!isset($post_data['ship_to_different_address']) || $post_data['ship_to_different_address'] != TRUE) {
					$to_town_id = $post_data['billing_town'];
					$to_town_type = $post_data['billing_location_type'];
				} else {
					$to_town_id = $post_data['shipping_town'];
					$to_town_type = $post_data['shipping_location_type'];
				}
			} else if (isset($_POST['ship_to_different_address'])) {
				if (!isset($_POST['ship_to_different_address']) || $_POST['ship_to_different_address'] != TRUE) {
					$to_town_id = $_POST['billing_town'];
					$to_town_type = $_POST['billing_location_type'];
				} else {
					$to_town_id = $_POST['shipping_town'];
					$to_town_type = $_POST['shipping_location_type'];
				}
			}

			// get an array with all our parcels
			$cart = $this->get_cart_content($package);

			// Get pricing for each service
			foreach ($this->mds_services as $id => $title) {
				if($this->settings["method_$id"] == 'yes')
				{
					if($this->settings["markup_$id"] > 0)
					{
						$percent = $this->settings['markup_'.$id];
						$markup = "1.$percent";
					}
					
					// Now lets get the price for
					$data = array(
						"from_town_id" => $this->default_address_id,
						"from_town_type" => $default_address['location_type'],
						"to_town_id" => array_search($to_town_id, $this->towns),
						"to_town_type" => array_search($to_town_type, $this->location_types),
						"num_package" => count($cart['products']),
						"service" => $id,
						"parcels" => $cart['products'],
						"exclude_weekend" => 1,
						"cover" => 1
					);

					// query the API for our prices
					$response = $this->collivery->getPrice($data);
					$price = ($response['price']['inc_vat'] * $markup);

					$rate = array(
						'id' => 'mds_' . $id,
						'label' => $title,
						'cost' => number_format($price, 2, '.', ''),
					);
					if ($rate['cost'] > 0) {
						$this->add_rate($rate); //Only add shipping if it has a value
					}
				}
			}
		}

		function get_cart_content($package)
		{
			if (sizeof($package['contents']) > 0) {
				//Reset array to defaults
				$this->cart = array(
					'count' => 0,
					'weight' => 0,
					'max_weight' => 0,
					'products' => Array()
				);

				foreach ($package['contents'] as $item_id => $values) {
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

		// Work through our order items and return an array of parcels
		function get_order_content($items)
		{
			$parcels = array();
			foreach ($items as $item_id => $item) {
				$product = new WC_Product( $item['product_id'] );
				$qty = $item['item_meta']['_qty'][0];

				// Work out Volumetric Weight based on MDS's calculations
				$vol_weight = (($product->length * $product->width * $product->height) / 4000);

				for ($i = 0; $i < $qty; $i++) {
					// Length coversion, mds collivery only acceps CM
					if (strtolower(get_option('woocommerce_dimension_unit')) != 'çm') {
						$length = $this->converter->convert($product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
						$width = $this->converter->convert($product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
						$height = $this->converter->convert($product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					} else {
						$length = $product->length;
						$width = $product->width;
						$height = $product->height;
					}

					// Weight coversion, mds collivery only acceps KG'S
					if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
						$weight = $this->converter->convert($product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
					} else {
						$weight = $product->get_weight();
					}

					$parcels[] = array(
						'length' => $length,
						'width' => $width,
						'height' => $height,
						'weight' => $weight
					);
				}
			}
			return $parcels;
		}
		
		/*
		 * Get Town and Location Types for Checkout Dropdown's from MDS
		 */
		public function get_field_defaults()
		{
			return Array('towns' => array_combine($this->towns, $this->towns), 'location_types' => array_combine($this->location_types, $this->location_types));
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
 * This adds location_type to the hash to update pricing cache when changed.
 */

function mds_collivery_cart_shipping_packages($packages)
{
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();

	if (isset($_POST['post_data']))
	{
		parse_str($_POST['post_data'], $post_data);
		$packages[0]['destination']['location_type'] = $post_data['billing_location_type'] . $post_data['shipping_location_type'];
	}
	else if (isset($_POST['billing_location_type']) || isset($_POST['shipping_location_type']))
	{
		$packages[0]['destination']['location_type'] = $_POST['billing_location_type'] . $_POST['shipping_location_type'];
	}
	else
	{
		//Bad Practice... But incase location_type isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$packages[0]['destination']['location_type'] = rand(0, 999999999999999999) . '-' . rand(0, 999999999999999999) . rand(0, 999999999999999999) . rand(0, 999999999999999999);
	}

	return $packages;
}

add_filter('woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages');
