<?php namespace MdsSupportingClasses;

use WC;
use WC_Order;
use WC_Product;
use WC_Admin_Settings;

use Exception;

/**
 * MdsColliveryService
 */
class MdsColliveryService
{
	/**
	 * self
	 */
	private static $instance;

	/**
	 * @type Collivery
	 */
	var $collivery;

	/**
	 * @type MdsCache
	 */
	var $cache;

	/**
	 * @type array
	 */
	var $validated_data;

	/**
	 * @type array
	 */
	var $settings;

	/**
	 * @param null $settings
	 * @return MdsColliveryService
	 * @throws Exception
	 */
	public static function getInstance($settings = null)
	{
		if (! self::$instance) {
			if (is_null($settings)) {
				global $wpdb;
				$settings = unserialize($wpdb->get_var("SELECT `option_value` FROM `" . $wpdb->prefix . "options` WHERE `option_name` LIKE 'woocommerce_mds_collivery_settings'"));
			};
			self::$instance = new self($settings);
		}

		return self::$instance;
	}

	private function __construct($settings)
	{
		$this->settings = $settings;

		$this->converter = new UnitConverter();

		$this->cache = new MdsCache();

		$this->initMdsCollivery($this->settings);
	}

	/**
	 * Instantiates the MDS Collivery class
	 *
	 * @param null|array $settings
	 */
	public function initMdsCollivery($settings=null)
	{
		global $wp_version;

		if($settings) {
			$username = $settings['mds_user'];
			$password = $settings['mds_pass'];
		} else {
			$username = $this->settings['mds_user'];
			$password = $this->settings['mds_pass'];
		}

		$this->collivery = new Collivery(array(
			'app_name' => 'WooCommerce MDS Shipping Plugin', // Application Name
			'app_version' => MDS_VERSION, // Plugin Version
			'app_host' => 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . $this->returnWoocommerceVersionNumber(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
			'app_url' => get_site_url(), // URL your site is hosted on
			'user_email' => $username, // Your Mds account
			'user_password' => $password // Your Mds account password
		));
	}

	/**
	 * Work through our shopping cart
	 * Convert lengths and weights to desired unit
	 *
	 * @param $package
	 * @return null|array
	 */
	function getCartContent($package)
	{
		if(isset($package['contents']) && sizeof($package['contents']) > 0) {

			$cart = array(
				'count' => 0,
				'total' => 0,
				'weight' => 0,
				'max_weight' => 0,
				'products' => array()
			);

			foreach ($package['contents'] as $item_id => $values) {
				$_product = $values['data']; // = WC_Product class
				$qty = $values['quantity'];
				$parcel['quantity'] = $qty;

				$cart['count'] += $qty;
				$cart['total'] += $values['line_subtotal'];
				$cart['weight'] += $_product->get_weight() * $qty;

				// Work out Volumetric Weight based on MDS calculations
				$vol_weight = (($_product->length * $_product->width * $_product->height) / 4000);

				if ($vol_weight > $_product->get_weight()) {
					$cart['max_weight'] += $vol_weight * $qty;
				} else {
					$cart['max_weight'] += $_product->get_weight() * $qty;
				}

				// Length conversion, mds collivery only accepts cm
				if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
					$parcel['length'] = $this->converter->convert($_product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					$parcel['width'] = $this->converter->convert($_product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					$parcel['height'] = $this->converter->convert($_product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
				} else {
					$parcel['length'] = $_product->length;
					$parcel['width'] = $_product->width;
					$parcel['height'] = $_product->height;
				}

				// Weight conversion, mds collivery only accepts kg
				if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
					$parcel['weight'] = $this->converter->convert($_product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
				} else {
					$parcel['weight'] = $_product->get_weight();
				}

				$parcel['description'] = $_product->get_title();

				$cart['products'][] = $parcel;
			}

			return $cart;
		}
	}

	/**
	 * Validate the package before using the package to get prices
	 *
	 * @param $package
	 * @return bool
	 */
	function validPackage($package)
	{
		// $package must be an array and not empty
		if(!is_array($package) || empty($package)) {
			return false;
		}

		if(!isset($package['destination'])) {
			return false;
		} else {
			if(!isset($package['destination']['to_town_id']) || !is_integer($package['destination']['to_town_id']) || $package['destination']['to_town_id'] == 0) {
				return false;
			}

			if(!isset($package['destination']['from_town_id']) || !is_integer($package['destination']['from_town_id']) || $package['destination']['from_town_id'] == 0) {
				return false;
			}

			if(!isset($package['destination']['to_location_type']) || !is_integer($package['destination']['to_location_type']) || $package['destination']['to_location_type'] == 0) {
				return false;
			}

			if(!isset($package['destination']['from_location_type']) || !is_integer($package['destination']['from_location_type']) || $package['destination']['from_location_type'] == 0) {
				return false;
			}
		}

		if(!isset($package['cart'])) {
			return false;
		} else {
			if(!isset($package['cart']['max_weight']) || !is_numeric($package['cart']['max_weight'])) {
				return false;
			}

			if(!isset($package['cart']['count']) || !is_numeric($package['cart']['count'])) {
				return false;
			}

			if(!isset($package['cart']['products']) || !is_array($package['cart']['products'])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Used to build the package for use out of the shipping class
	 *
	 * @return array
	 */
	function buildPackageFromCart($cart)
	{
		$package = array();

		if(!empty($cart)) {
			foreach($cart as $item) {
				$product = $item['data'];

				$package['contents'][$item['product_id']] = array(
					'data' => $item['data'],
					'quantity' => $item['quantity'],
					'price' => $this->format($product->get_price()),
					'line_subtotal' => $this->format($product->get_price() * $item['quantity']),
					'weight' => $product->get_weight() * $item['quantity']
				);
			}
		}

		return $package;
	}

	/**
	 * Work through our order items and return an array of parcels
	 *
	 * @param $items
	 * @return array
	 */
	function getOrderContent($items)
	{
		$parcels = array();
		foreach ($items as $item_id => $item) {
			$product = new WC_Product($item['product_id']);
			$parcel['quantity'] = $item['item_meta']['_qty'][0];

			// Length conversion, mds collivery only accepts cm
			if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
				$parcel['length'] = $this->converter->convert($product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
				$parcel['width'] = $this->converter->convert($product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
				$parcel['height'] = $this->converter->convert($product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
			} else {
				$parcel['length'] = $product->length;
				$parcel['width'] = $product->width;
				$parcel['height'] = $product->height;
			}

			// Weight conversion, mds collivery only accepts kg
			if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
				$parcel['weight'] = $this->converter->convert($product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
			} else {
				$parcel['weight'] = $product->get_weight();
			}

			$parcel['description'] = $product->get_title();

			$parcels[] = $parcel;
		}

		return $parcels;
	}

	/**
	 * Goes through and order and checks if all products are in stock
	 *
	 * @param $items
	 * @return bool
	 */
	public function isOrderInStock($items)
	{
		foreach ($items as $item_id => $item) {
			$qty = $item['item_meta']['_qty'][0];
			$product = new WC_Product($item['product_id']);
			$stock = $product->get_total_stock();

			if($stock != '') {
				if($stock == 0 || $stock < $qty) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param WC_Order $order
	 * @param $message
	 * @param $processing
	 * @param null $status
	 */
	public function updateStatusOrAddNote(WC_Order $order, $message, $processing, $status = null)
	{
		if(!$processing) {
			$order->update_status($status, $message);
		} else {
			$order->add_order_note($message, 0);
		}
	}

	/**
	 * Adds the delivery request to MDS Collivery
	 *
	 * @param array $array
	 * @param bool $accept
	 * @return bool
	 */
	public function addCollivery(array $array, $accept=true)
	{
		$this->validated_data = $this->validateCollivery($array);

		if(isset($this->validated_data['time_changed']) && $this->validated_data['time_changed'] == 1) {
			$id = $this->validated_data['service'];
			$services = $this->collivery->getServices();

			if(!empty($this->settings["wording_$id"])) {
				$reason = preg_replace('|' . preg_quote($services[$id]) . '|', $this->settings["wording_$id"], $this->validated_data['time_changed_reason']);
			} else {
				$reason = $this->validated_data['time_changed_reason'];
			}

			$reason = preg_replace('|collivery|i', 'delivery', $reason);
			$reason = preg_replace('|The delivery time has been CHANGED to|i', 'the approximate delivery day is', $reason);

			if(function_exists('wc_add_notice')) {
				wc_add_notice(sprintf(__($reason, "woocommerce-mds-shipping")));
			}
		}

		$collivery_id = $this->collivery->addCollivery($this->validated_data);

		if($accept) {
			return ($this->collivery->acceptCollivery($collivery_id)) ? $collivery_id : false;
		}

		return $collivery_id;
	}

	/**
	 * Validate delivery request before adding the request to MDS Collivery
	 *
	 * @param array $array
	 * @throws Exception
	 * @return bool|array
	 */
	public function validateCollivery(array $array)
	{
		if(empty($array['collivery_from'])) {
			throw new Exception("Invalid collection address");
		}

		if(empty($array['collivery_to'])) {
			throw new Exception("Invalid destination address");
		}

		if(empty($array['contact_from'])) {
			throw new Exception("Invalid collection contact");
		}

		if(empty($array['contact_to'])) {
			throw new Exception("Invalid destination contact");
		}

		if(empty($array['collivery_type'])) {
			throw new Exception("Invalid parcel type");
		}

		if(empty($array['service'])) {
			throw new Exception("Invalid service");
		}

		if($array['cover'] != 1 && $array['cover'] != 0) {
			throw new Exception("Invalid risk cover option");
		}

		if(empty($array['parcels']) || !is_array($array['parcels'])) {
			throw new Exception("Invalid parcels");
		}

		return $this->collivery->validate($array);
	}

	/**
	 * Auto process to send collection requests to MDS
	 *
	 * @param $order_id
	 * @param bool $processing
	 * @return bool|null
	 */
	public function automatedAddCollivery($order_id, $processing = false)
	{
		global $wpdb;

		$order = new WC_Order($order_id);

		$colliveries = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "mds_collivery_processed WHERE order_id=" . $order->id . ";");

		if($colliveries == 0) {
			try {
				$this->updateStatusOrAddNote($order, 'MDS auto processing has begun.', $processing, 'processing');

				if(!$this->isOrderInStock($order->get_items())) {
					$this->updateStatusOrAddNote($order, 'There are products in the order that are not in stock, auto processing aborted.', $processing, 'processing');
					return false;
				}

				$parcels = $this->getOrderContent($order->get_items());
				$defaults = $this->returnDefaultAddress();

				foreach($order->get_shipping_methods() as $shipping) {
					$services[str_replace("mds_", "", $shipping['method_id'])] = $shipping['name'];
				}

				$address = $this->addColliveryAddress(array(
					'company_name' => ( $order->shipping_company != "" ) ? $order->shipping_company : 'Private',
					'building' => $order->shipping_building_details,
					'street' => $order->shipping_address_1 . ' ' . $order->shipping_address_2,
					'location_type' => $order->shipping_location_type,
					'suburb' => $order->shipping_city,
					'town' => $order->shipping_state,
					'full_name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
					'cellphone' => preg_replace("/[^0-9]/", "", $order->shipping_phone),
					'email' => str_replace(' ', '', $order->shipping_email),
					'custom_id' => $order->user_id
				));

				$collivery_from = $defaults['default_address_id'];
				list($contact_from) = array_keys($defaults['contacts']);

				$collivery_to = $address['address_id'];
				$contact_to = $address['contact_id'];

				$service_id = array_search($order->get_shipping_method(), $services);

				$instructions = "Order number: " . $order_id;
				if(isset($this->settings['include_product_titles']) && $this->settings['include_product_titles'] == "yes") {
					$count = 1;
					$instructions .= ': ';
					foreach($parcels as $parcel) {
						if(isset($parcel['description'])) {
							$ending = ($count == count($parcels)) ? '' : ', ';
							$instructions .= $parcel['quantity'] . ' X ' . $parcel['description'] . $ending;
							$count++;
						}
					}
				}

				$collivery_id = $this->addCollivery(array(
					'collivery_from' => (int) $collivery_from,
					'contact_from' => (int) $contact_from,
					'collivery_to' => (int) $collivery_to,
					'contact_to' => (int) $contact_to,
					'cust_ref' => "Order number: " . $order_id,
					'instructions' => $instructions,
					'collivery_type' => 2,
					'service' => (int) $service_id,
					'cover' => ($this->settings['risk_cover'] == 'yes') ? 1 : 0,
					'parcel_count' => count($parcels),
					'parcels' => $parcels
				));

				$collection_time = (isset($this->validated_data['collection_time'])) ? ' anytime from: ' . date('Y-m-d H:i', $this->validated_data['collection_time'])  : '';

				if($collivery_id) {
					// Save the results from validation into our table
					$this->addColliveryToProcessedTable($collivery_id, $order->id);
					$this->updateStatusOrAddNote($order, 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery_id . ', please have order ready for collection' . $collection_time . '.', $processing, 'completed');
				} else {
					$this->updateStatusOrAddNote($order, 'There was a problem sending this the delivery request to MDS Collivery, you will need to manually process.', $processing, 'processing');
				}
			} catch(Exception $e) {
				$this->updateStatusOrAddNote($order, 'There was a problem sending this the delivery request to MDS Collivery, you will need to manually process. Error: ' . $e->getMessage(), $processing, 'processing');
			}
		}
	}

	/**
	 * Adds the new collivery to our mds processed table
	 * @param int $collivery_id
	 * @param int $order_id
	 * @return bool
	 */
	public function addColliveryToProcessedTable($collivery_id, $order_id)
	{
		global $wpdb;

		// Save the results from validation into our table
		$table_name = $wpdb->prefix . 'mds_collivery_processed';
		$data = array(
			'status' => 1,
			'order_id' => $order_id,
			'validation_results' => json_encode($this->returnColliveryValidatedData()),
			'waybill' => $collivery_id
		);

		$wpdb->insert( $table_name, $data );
	}

	/**
	 * Adds an address to MDS Collivery
	 *
	 * @param array $array
	 * @return array
	 * @throws Exception
	 */
	public function addColliveryAddress(array $array)
	{
		$towns = $this->collivery->getTowns();
		$location_types = $this->collivery->getLocationTypes();

		if(!is_numeric($array['town'])) {
			$town_id = (int) array_search($array['town'], $towns);
		} else {
			$town_id = $array['town'];
		}

		$suburbs = $this->collivery->getSuburbs($town_id);

		if(!is_numeric($array['suburb'])) {
			$suburb_id = (int) array_search($array['suburb'], $suburbs);
		} else {
			$suburb_id = $array['suburb'];
		}

		if(!is_numeric($array['location_type'])) {
			$location_type_id = (int) array_search($array['location_type'], $location_types);
		} else {
			$location_type_id = $array['location_type'];
		}

		if(empty($array['location_type']) || !isset($location_types[$location_type_id])) {
			throw new Exception("Invalid location type");
		}

		if(empty($array['town']) || !isset($towns[$town_id])) {
			throw new Exception("Invalid town");
		}

		if(empty($array['suburb']) || !isset($suburbs[$suburb_id])) {
			throw new Exception("Invalid suburb");
		}

		if(empty($array['cellphone']) || !is_numeric($array['cellphone'])) {
			throw new Exception("Invalid cellphone number");
		}

		if(empty($array['email']) || !filter_var($array['email'], FILTER_VALIDATE_EMAIL)) {
			throw new Exception("Invalid email address");
		}

		$newAddress = array(
			'company_name' => $array['company_name'],
			'building' => $array['building'],
			'street' => $array['street'],
			'location_type' => $location_type_id,
			'suburb_id' => $suburb_id,
			'town_id' => $town_id,
			'full_name' => $array['full_name'],
			'phone' => (!empty($array['phone'])) ? preg_replace("/[^0-9]/", "", $array['phone']) : '',
			'cellphone' => preg_replace("/[^0-9]/", "", $array['cellphone']),
			'custom_id' => $array['custom_id'],
			'email' => $array['email'],
		);

		// Before adding an address lets search MDS and see if we have already added this address
		$searchAddresses = $this->searchAndMatchAddress(array(
			'custom_id' => $array['custom_id'],
			'suburb_id' => $suburb_id,
			'town_id' => $town_id,
		), $newAddress);

		if(is_array($searchAddresses)) {
			return $searchAddresses;
		} else {
			$this->cache->clear(array('addresses', 'contacts'));
			return $this->collivery->addAddress($newAddress);
		}
	}

	/**
	 * Searches for an address and matches each important field
	 *
	 * @param array $filters
	 * @param array $newAddress
	 * @return bool
	 */
	public function searchAndMatchAddress(array $filters, array $newAddress)
	{
		$searchAddresses = $this->collivery->getAddresses($filters);
		if(!empty($searchAddresses)) {
			$match = true;

			$matchAddressFields = array(
				'company_name' => 'company_name',
				'building_details' => 'building',
				'street' => 'street',
				'location_type' => 'location_type',
				'suburb_id' => 'suburb_id',
				'town_id' => 'town_id',
				'custom_id' => 'custom_id',
			);

			foreach($searchAddresses as $address) {
				foreach($matchAddressFields as $mdsField => $newField) {
					if($address[$mdsField] != $newAddress[$newField]) {
						$match = false;
					}
				}

				if($match) {
					if(!isset($address['contact_id'])) {
						$contacts = $this->collivery->getContacts($address['address_id']);
						list($contact_id) = array_keys($contacts);
						$address['contact_id'] = $contact_id;
					}

					return $address;
				}
			}
		} else {
			$this->collivery->clearErrors();
		}

		return false;
	}

	/**
	 * Get Town and Location Types for Checkout selects from MDS
	 *
	 * @return array|bool
	 */
	public function returnFieldDefaults()
	{
		$towns = $this->collivery->getTowns();
		$location_types = $this->collivery->getLocationTypes();

		if(!is_array($towns) || !is_array($location_types)) {
			return false;
		}

		return array('towns' => array_combine($towns, $towns), 'location_types' => array_combine($location_types, $location_types));
	}

	/**
	 * Returns the MDS Collivery class
	 *
	 * @return Collivery
	 */
	public function returnColliveryClass()
	{
		return $this->collivery;
	}

	/**
	 * Returns the MDS Cache class
	 *
	 * @return MdsCache
	 */
	public function returnCacheClass()
	{
		return $this->cache;
	}

	/**
	 * Returns the UnitConverter class
	 *
	 * @return UnitConverter
	 */
	public function returnConverterClass()
	{
		return $this->converter;
	}

	/**
	 * Returns the WC_Mds_Shipping_Method plugin settings
	 *
	 * @return array
	 */
	public function returnPluginSettings()
	{
		return $this->settings;
	}

	/**
	 * Gets default address of the MDS Account
	 *
	 * @return array|bool
	 */
	public function returnDefaultAddress()
	{
		$default_address_id = $this->collivery->getDefaultAddressId();
		if(!$default_address = $this->collivery->getAddress($default_address_id)) {
			return false;
		}

		$data = array(
			'address' => $default_address,
			'default_address_id' => $default_address_id,
			'contacts' => $this->collivery->getContacts($default_address_id)
		);

		return $data;
	}

	/**
	 * @return null|array
	 */
	public function returnColliveryValidatedData()
	{
		return $this->validated_data;
	}

	/**
	 * Adds markup to price
	 *
	 * @param $price
	 * @param $markup
	 * @return float|string
	 */
	public function addMarkup($price, $markup)
	{
		$price += $price * ($markup / 100);
		return (isset($this->settings['round']) && $this->settings['round'] == 'yes') ? $this->round($price) : $this->format($price);
	}

	/**
	 * Format a number with grouped thousands
	 *
	 * @param $price
	 * @return string
	 */
	public function format($price)
	{
		return number_format($price, 2, '.', '');
	}

	/**
	 * Rounds number up to the next highest integer
	 *
	 * @param $price
	 * @return float
	 */
	public function round($price)
	{
		return ceil($this->format($price));
	}

	/**
	 * This function is here so we can get WooCommerce version number to pass on to the API for logs
	 */
	private function returnWoocommerceVersionNumber()
	{
		// If get_plugins() isn't available, require it
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
}
