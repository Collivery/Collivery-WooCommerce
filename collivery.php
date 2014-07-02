<?php

/**
 * Plugin Name: MDS Collivery
 * Plugin URI: http://www.collivery.co.za/
 * Description: Plugin to add support for MDS Collivery in WooCommerce.
 * Version: 1.4
 * Author: Bryce Large | Bernhard Breytenbach
 * License: GNU/GPL version 3 or later: http://www.gnu.org/licenses/gpl.html
 */
// Our versions
global $wp_version;
global $mds_db_version;
$mds_db_version = "1.7";

register_activation_hook( __FILE__, 'mdsInstall' ); // Install Hook

function mdsInstall()
{

	// We have to check what php version we have before anything is installed.
	if ( version_compare( PHP_VERSION, '5.3.0' ) < 0 ) {
		die( 'Your PHP version is not able to run this plugin, update to the latest version before instaling this plugin.' );
	}

	// Check if there is a cache directory
	if ( !is_dir( __DIR__ . '/Mds/cache' ) ) {

		// Lets make a cache directory
		@mkdir( __DIR__ . '/Mds/cache', 0755, true );

		// Make sure that we actually created the directory
		if ( !is_dir( __DIR__ . '/Mds/cache' ) ) {
			die( 'The plugin was unable to create a cache directory. Please make one manualy first, ' . dir( __DIR__ . '/Mds/cache' ) );
		}
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

	$wpdb->query( $sql );

	add_option( "mds_db_version", "1.7" );
}

add_action( 'plugins_loaded', 'init_mds_collivery', 0 );

function init_mds_collivery()
{

	// Check if 'WC_Shipping_Method' class is loaded, else exit.
	if ( !class_exists( 'WC_Shipping_Method' ) ) {
		return;
	}

	include_once 'mds-admin.php'; //Admin Scripts
	include_once 'checkout_fields.php'; //Seperate file with large arrays.
	//Load JS file
	add_action( 'wp_enqueue_scripts', 'load_js' );

	function load_js()
	{
		wp_register_script( 'mds_js', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'mds_js' );
	}

	class WC_MDS_Collivery extends WC_Shipping_Method {

		var $collivery;
		var $converter;

		public function __construct()
		{

			// Use the MDS API Files
			require_once 'Mds/Cache.php';
			require_once 'Mds/Collivery.php';

			// Class for converting lengths and weights
			require_once 'UnitConvertor.php';
			$this->converter = new UnitConvertor();

			$this->id = 'mds_collivery';
			$this->method_title = __( 'MDS Collivery', 'woocommerce' );
			$this->admin_page_heading = __( 'MDS Collivery', 'woocommerce' );
			$this->admin_page_description = __( 'Seamlessly integrate your website with MDS Collivery', 'woocommerce' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );

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

			$config = array(
				'app_name' => 'Shipping by MDS Collivery for WooCommerce', // Application Name
				'app_version' => "2.0", // Application Version
				'app_host' => 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . $this->wpbo_get_woo_version_number(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
				'app_url' => get_site_url(), // URL your site is hosted on
				'user_email' => $this->mds_user,
				'user_password' => $this->mds_pass
			);

			$this->collivery = new Mds\Collivery( $config );

			// Load the form fields that depend on the WS.
			$this->init_ws_form_fields();
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
			$default_address_id = $this->collivery->getDefaultAddressId();
			$data = array(
				'address' => $this->collivery->getAddress( $default_address_id ),
				'default_address_id' => $default_address_id,
				'contacts' => $this->collivery->getContacts( $default_address_id )
			);
			return $data;
		}

		// This function is here so we can get WooCommerce version number to pass on to the API for logs
		function wpbo_get_woo_version_number()
		{
			// If get_plugins() isn't available, require it
			if ( !function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Create the plugins folder and file variables
			$plugin_folder = get_plugins( '/' . 'woocommerce' );
			$plugin_file = 'woocommerce.php';

			// If the plugin version number is set, return it
			if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
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
					'title' => __( 'Enabled?', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable this shipping method', 'woocommerce' ),
					'default' => 'yes',
				),
				'title' => array(
					'title' => __( 'Method Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'MDS Collivery', 'woocommerce' ),
				),
				'mds_user' => array(
					'title' => "MDS " . __( 'Username', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Email address associated with your MDS account.', 'woocommerce' ),
					'default' => "api@collivery.co.za",
				),
				'mds_pass' => array(
					'title' => "MDS " . __( 'Password', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'The password used when logging in to MDS.', 'woocommerce' ),
					'default' => "api123",
				),
				'risk_cover' => array(
					'title' => "MDS " . __( 'Insurance', 'woocommerce' ),
					'type' => 'checkbox',
					'description' => __( 'Insurance up to a maximum of R5000.', 'woocommerce' ),
					'default' => 'yes',
				),
			);

			$this->form_fields = $fields;
		}

		public function init_ws_form_fields()
		{
			global $woocommerce;
			$fields = $this->form_fields;
			$services = $this->collivery->getServices();

			foreach ( $services as $id => $title ) {
				$fields['method_' . $id] = array(
					'title' => __( $title . ': Enabled', 'woocommerce' ),
					'type' => 'checkbox',
					'default' => 'yes',
				);
				$fields['markup_' . $id] = array(
					'title' => __( $title . ': Markup', 'woocommerce' ),
					'type' => 'text',
					'default' => '10',
				);
			}

			$this->form_fields = $fields;
		}

		function calculate_shipping( $package = array() )
		{
			$towns = $this->collivery->getTowns();
			$location_types = $this->collivery->getLocationTypes();

			// Get our default address
			$defaults = $this->getDefaulsAddress();

			// Capture the correct Town and location type
			if ( isset( $_POST['post_data'] ) ) {
				parse_str( $_POST['post_data'], $post_data );
				if ( !isset( $post_data['ship_to_different_address'] ) || $post_data['ship_to_different_address'] != TRUE ) {
					$to_town_id = $post_data['billing_state'];
					$to_town_type = $post_data['billing_location_type'];
				} else {
					$to_town_id = $post_data['shipping_state'];
					$to_town_type = $post_data['shipping_location_type'];
				}
			} else if ( isset( $_POST['ship_to_different_address'] ) ) {
					if ( !isset( $_POST['ship_to_different_address'] ) || $_POST['ship_to_different_address'] != TRUE ) {
						$to_town_id = $_POST['billing_state'];
						$to_town_type = $_POST['billing_location_type'];
					} else {
						$to_town_id = $_POST['shipping_state'];
						$to_town_type = $_POST['shipping_location_type'];
					}
				} else if ( isset( $package['destination'] ) ) {
					$to_town_id = $package['destination']['state'];
					$to_town_type = $package['destination']['location_type'];
				} else {
				return;
			}

			// get an array with all our parcels
			$cart = $this->get_cart_content( $package );
			$services = $this->collivery->getServices();

			// Get pricing for each service
			foreach ( $services as $id => $title ) {
				if ( $this->settings["method_$id"] == 'yes' ) {
					if ( $this->settings["markup_$id"] > 0 ) {
						$percent = $this->settings['markup_' . $id];
						$markup = "1.$percent";
					}

					// Now lets get the price for
					$data = array(
						"from_town_id" => $defaults['address']['town_id'],
						"from_town_type" => $defaults['address']['location_type'],
						"to_town_id" => array_search( $to_town_id, $towns ),
						"to_town_type" => array_search( $to_town_type, $location_types ),
						"num_package" => count( $cart['products'] ),
						"service" => $id,
						"parcels" => $cart['products'],
						"exclude_weekend" => 1,
						"cover" => ( $this->settings['risk_cover'] == 'yes' ) ? ( 1 ) : ( 0 )
					);

					// query the API for our prices
					$response = $this->collivery->getPrice( $data );
					if ( isset( $response['price']['inc_vat'] ) ) {
						$price = ( $response['price']['inc_vat'] * $markup );
						$rate = array(
							'id' => 'mds_' . $id,
							'label' => $title,
							'cost' => number_format( $price, 2, '.', '' ),
						);
						$this->add_rate( $rate ); //Only add shipping if it has a value
					}
				}
			}
		}

		function get_cart_content( $package )
		{
			if ( sizeof( $package['contents'] ) > 0 ) {

				//Reset array to defaults
				$this->cart = array(
					'count' => 0,
					'weight' => 0,
					'max_weight' => 0,
					'products' => array()
				);

				foreach ( $package['contents'] as $item_id => $values ) {
					$_product = $values['data']; // = WC_Product class
					$qty = $values['quantity'];

					$this->cart['count'] += $qty;
					$this->cart['weight'] += $_product->get_weight() * $qty;

					// Work out Volumetric Weight based on MDS's calculations
					$vol_weight = ( ( $_product->length * $_product->width * $_product->height ) / 4000 );

					if ( $vol_weight > $_product->get_weight() ) {
						$this->cart['max_weight'] += $vol_weight * $qty;
					} else {
						$this->cart['max_weight'] += $_product->get_weight() * $qty;
					}

					for ( $i = 0; $i < $qty; $i++ ) {
						// Length coversion, mds collivery only acceps CM
						if ( strtolower( get_option( 'woocommerce_dimension_unit' ) ) != 'cm' ) {
							$length = $this->converter->convert( $_product->length, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
							$width = $this->converter->convert( $_product->width, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
							$height = $this->converter->convert( $_product->height, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
						} else {
							$length = $_product->length;
							$width = $_product->width;
							$height = $_product->height;
						}

						// Weight coversion, mds collivery only acceps KG'S
						if ( strtolower( get_option( 'woocommerce_weight_unit' ) ) != 'kg' ) {
							$weight = $this->converter->convert( $_product->get_weight(), strtolower( get_option( 'woocommerce_weight_unit' ) ), 'kg', 6 );
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
	 * Work through our order items and return an array of parcels
	 */

		function get_order_content( $items )
		{
			$parcels = array();
			foreach ( $items as $item_id => $item ) {
				$product = new WC_Product( $item['product_id'] );
				$qty = $item['item_meta']['_qty'][0];

				// Work out Volumetric Weight based on MDS's calculations
				$vol_weight = ( ( $product->length * $product->width * $product->height ) / 4000 );

				for ( $i = 0; $i < $qty; $i++ ) {
					// Length coversion, mds collivery only acceps CM
					if ( strtolower( get_option( 'woocommerce_dimension_unit' ) ) != 'cm' ) {
						$length = $this->converter->convert( $product->length, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
						$width = $this->converter->convert( $product->width, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
						$height = $this->converter->convert( $product->height, strtolower( get_option( 'woocommerce_dimension_unit' ) ), 'cm', 6 );
					} else {
						$length = $product->length;
						$width = $product->width;
						$height = $product->height;
					}

					// Weight coversion, mds collivery only acceps KG'S
					if ( strtolower( get_option( 'woocommerce_weight_unit' ) ) != 'kg' ) {
						$weight = $this->converter->convert( $product->get_weight(), strtolower( get_option( 'woocommerce_weight_unit' ) ), 'kg', 6 );
					} else {
						$weight = $product->get_weight();
					}

					$parcels[] = array(
						'length' => ( empty( $length ) ) ? ( 0 ) : ( $length ),
						'width' => ( empty( $width ) ) ? ( 0 ) : ( $width ),
						'height' => ( empty( $height ) ) ? ( 0 ) : ( $height ),
						'weight' => ( empty( $weight ) ) ? ( 0 ) : ( $weight )
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
			$towns = $this->collivery->getTowns();
			$location_types = $this->collivery->getLocationTypes();
			return array( 'towns' => array_combine( $towns, $towns ), 'location_types' => array_combine( $location_types, $location_types ) );
		}

	}

}

/*
 * Register Plugin with WooCommerce
 */
add_filter( 'woocommerce_shipping_methods', 'add_MDS_Collivery_method' );

function add_MDS_Collivery_method( $methods )
{
	$methods[] = 'WC_MDS_Collivery';
	return $methods;
}

/*
 * WooCommerce caches pricing information.
 * This adds location_type to the hash to update pricing cache when changed.
 */
add_filter( 'woocommerce_cart_shipping_packages', 'mds_collivery_cart_shipping_packages' );

function mds_collivery_cart_shipping_packages( $packages )
{
	$mds = new WC_MDS_Collivery;
	$collivery = $mds->getColliveryClass();

	if ( isset( $_POST['post_data'] ) ) {
		parse_str( $_POST['post_data'], $post_data );
		$packages[0]['destination']['location_type'] = $post_data['billing_location_type'] . $post_data['shipping_location_type'];
	} else if ( isset( $_POST['billing_location_type'] ) || isset( $_POST['shipping_location_type'] ) ) {
			$packages[0]['destination']['location_type'] = ( isset( $_POST['billing_location_type'] ) && $_POST['shipping_location_type'] ) ? ( $_POST['shipping_location_type'] ) : ( $_POST['billing_location_type'] );
		} else {
		//Bad Practice... But incase location_type isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$packages[0]['destination']['location_type'] = rand( 0, 999999999999999999 ) . '-' . rand( 0, 999999999999999999 ) . rand( 0, 999999999999999999 ) . rand( 0, 999999999999999999 );
	}

	if ( isset( $_POST['post_data'] ) ) {
		parse_str( $_POST['post_data'], $post_data );
		$packages[0]['destination']['town'] = $post_data['billing_state'] . $post_data['shipping_state'];
	} else if ( isset( $_POST['billing_state'] ) || isset( $_POST['shipping_state'] ) ) {
			$packages[0]['destination']['town'] = ( isset( $_POST['shipping_state'] ) && $_POST['shipping_state'] ) ? ( $_POST['billing_state'] ) : ( $_POST['billing_location_type'] );
		} else {
		//Bad Practice... But incase town isn't set, do not cache the order!
		//@TODO: Find a way to fix this
		$packages[0]['destination']['town'] = rand( 0, 999999999999999999 ) . '-' . rand( 0, 999999999999999999 ) . rand( 0, 999999999999999999 ) . rand( 0, 999999999999999999 );
	}

	return $packages;
}
