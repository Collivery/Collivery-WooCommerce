<?php

namespace MdsSupportingClasses;

use MdsExceptions\SoapConnectionException;
use WC_Order;
use WC_Product;
use WC_Admin_Settings;

use MdsExceptions\ProductOutOfException;
use MdsExceptions\InvalidServiceException;
use MdsExceptions\InvalidCartPackageException;
use MdsExceptions\InvalidAddressDataException;
use MdsExceptions\InvalidColliveryDataException;
use MdsExceptions\OrderAlreadyProcessedException;

/**
 * MdsColliveryService
 */
class MdsColliveryService
{
	const TEST_USER = 'api@collivery.co.za';
	const TEST_PASS = 'api123';

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
	 * @type MdsLogger
	 */
	var $logger;

	/**
	 * @type array
	 */
	var $validated_data;

	/**
	 * @type array
	 */
	var $settings;

	/**
	 * @return MdsColliveryService
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $settings
	 * @return MdsColliveryService
	 */
	public function newInstance($settings)
	{
		self::$instance = new self();
		$this->initSettings($settings);

		return self::$instance;
	}

	/**
	 * MdsColliveryService constructor.
	 * @param null $settings
	 */
	private function __construct($settings = null)
	{
		$this->converter = new UnitConverter();
		$this->cache = new MdsCache(ABSPATH . 'cache/mds_collivery/');
		$this->logger = new MdsLogger();
		$this->enviroment = new EnvironmentInformationBag();

		$this->initSettings($settings);
		$this->initMdsCollivery();
	}

	/**
	 *  Sets up the settings array by fetching all of the options out of the database
	 * @param null $settings
	 */
	public function initSettings($settings = null)
	{
		if($settings) {
			$this->settings = is_array($settings) ? $settings : array();
		} else {
			$this->settings = get_option('woocommerce_mds_collivery_settings', array());
		}

		$this->enviroment->setSettings($this->settings);
	}

	/**
	 * Instantiates the MDS Collivery class
	 */
	public function initMdsCollivery()
	{
		if (!is_array($this->settings)) {
			$this->logger->error('initMdsCollivery', 'default settings used', $this->settings);
			$defaultSettings = array(
				'mds_user' => self::TEST_USER,
				'mds_pass' => self::TEST_PASS,
				'enabled' => 'yes',
				'include_product_titles' => 'no',
				'risk_cover' => 'no',
				'risk_cover_threshold' => 0.00,
				'include_vat' => 'yes',
				'round' => 'yes',
				'method_free' => 'no',
				'shipping_discount_percentage' => 10,
				'wording_free' => 'Free Delivery',
				'free_min_total' => 1000.00,
				'free_local_only' => 'no',
				'free_default_service' => 5,
				'free_local_default_service' => 2,
				'toggle_automatic_mds_processing' => 'no',
			);

			$this->settings = $defaultSettings;
			$this->enviroment->setSettings($defaultSettings);
		}

		$colliveryInitData = array(
			'app_name' => $this->enviroment->appName,
			'app_version' => $this->enviroment->appVersion,
			'app_host' => $this->enviroment->appHost,
			'app_url' => $this->enviroment->appUrl,
			'user_email' => $this->settings['mds_user'],
			'user_password' => $this->settings['mds_pass']
		);

		$this->collivery = new Collivery($colliveryInitData, $this->cache);

		if(isset($defaultSettings)) {
			try {
				$services = $this->collivery->getServices();
				if(!empty($services)) {
					foreach($services as $id => $title) {
						$this->settings['method_' . $id] = 'yes';
						$this->settings['markup_' . $id] = '10';
						$this->settings['wording_' . $id] = $title;
					}

					$this->enviroment->setSettings($this->settings);
				} else {
					throw new InvalidColliveryDataException('Unable to get services through the API', 'MdsColliveryService::initMdsCollivery()', $this->loggerSettingsArray(), $colliveryInitData);
				}
			} catch (InvalidColliveryDataException $e) {
				// Just for logging
			}
		}
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

		try {
			$this->validatePackageField($package, 'destination', 'array');
			$this->validatePackageField($package['destination'], 'to_town_id');
			$this->validatePackageField($package['destination'], 'from_town_id');
			$this->validatePackageField($package['destination'], 'to_location_type');
			$this->validatePackageField($package['destination'], 'from_location_type');

			$this->validatePackageField($package, 'cart', 'array');
			$this->validatePackageField($package['cart'], 'max_weight', 'numeric');
			$this->validatePackageField($package['cart'], 'count', 'numeric');
			$this->validatePackageField($package['cart'], 'products', 'array');

			return true;
		} catch(InvalidCartPackageException $e) {
			return false;
		}
	}

	/**
	 * @param $array
	 * @param $field
	 * @param string $type
	 * @throws InvalidCartPackageException
	 */
	private function validatePackageField($array, $field, $type = 'int')
	{
		if(!is_array($array)) {
			throw new InvalidCartPackageException('Unable to validate field"' . $field . '" as its parent is not an array, possible due to when the cart page loads', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), array());
		}

		if(!isset($array[$field])) {
			throw new InvalidCartPackageException($field . ' does not exist in array', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
		}

		if($type == 'int') {
			if(!is_integer($array[$field])) {
				throw new InvalidCartPackageException($field . ' is not an integer', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
			}
		}

		if($type == 'numeric') {
			if(!is_numeric($array[$field])) {
				throw new InvalidCartPackageException($field . ' is not numeric', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
			}
		}

		if($type == 'array') {
			if(!is_array($array[$field])) {
				throw new InvalidCartPackageException($field . ' is not an array', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
			}
		}
	}

	/**
	 * Used to build the package for use out of the shipping class
	 *
	 * @param $cart
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
					'price' => $product->get_price(),
					'line_subtotal' => $product->get_price() * $item['quantity'],
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
		try {
			foreach ($items as $item_id => $item) {
				$qty = $item['item_meta']['_qty'][0];
				$product = new WC_Product($item['product_id']);
				$stock = $product->get_total_stock();

				if($stock != '') {
					if($stock == 0 || $stock < $qty) {
						throw new ProductOutOfException($product->get_formatted_name() . ' is out of stock', 'isOrderInStock', $this->loggerSettingsArray(), $items);
					}
				}
			}

			return true;
		} catch (ProductOutOfException $e) {
			return false;
		}
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
	 * @param array $array
	 * @param $serviceId
	 * @param $cartSubTotal
	 * @return float
	 * @throws InvalidColliveryDataException
	 */
	public function getPrice(array $array, $serviceId, $cartSubTotal)
	{
		if(!$result = $this->collivery->getPrice($array)) {
			throw new InvalidColliveryDataException('Unable to get price from MDS', 'MdsColliveryService::getPrice', $this->loggerSettingsArray(), array('errors' => $this->collivery->getErrors(), 'data' => $array));
		}

		if($this->settings['method_free'] === 'discount' && $cartSubTotal >= $this->settings['free_min_total']) {
			$discount = $this->settings['shipping_discount_percentage'];
		} else {
			$discount = 0;
		}

		if(isset($this->settings['include_vat']) && $this->settings['include_vat'] === 'yes') {
			$returnedAmount = $result['price']['inc_vat'];
		} else {
			$returnedAmount = $result['price']['ex_vat'];
		}

		return Money::make($returnedAmount, $this->settings['markup_' . $serviceId], $discount, $this->settings['round'] == 'yes')->amount;
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
	 * @throws InvalidColliveryDataException
	 * @return bool|array
	 */
	public function validateCollivery(array $array)
	{
		if(empty($array['collivery_from'])) {
			throw new InvalidColliveryDataException('Invalid collection address', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['collivery_to'])) {
			throw new InvalidColliveryDataException('Invalid destination address', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['contact_from'])) {
			throw new InvalidColliveryDataException('Invalid collection contact', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['contact_to'])) {
			throw new InvalidColliveryDataException('Invalid destination contact', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['collivery_type'])) {
			throw new InvalidColliveryDataException('Invalid parcel type', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['service'])) {
			throw new InvalidColliveryDataException('Invalid service', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if($array['cover'] != 1 && $array['cover'] != 0) {
			throw new InvalidColliveryDataException('Invalid risk cover option', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['parcels']) || !is_array($array['parcels'])) {
			throw new InvalidColliveryDataException('Invalid parcels', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
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
		$order = new WC_Order($order_id);

		try {
			if(!$this->hasOrderBeenProcessed($order->id)) {
				foreach($order->get_shipping_methods() as $shipping) {
					if(preg_match("/mds_/", $shipping['method_id'])) {
						$service_id = str_replace("mds_", "", $shipping['method_id']);
					}
				}

				try {
					if(!isset($service_id)) {
						throw new InvalidServiceException('No MDS shipping method used', 'automatedAddCollivery', $this->loggerSettingsArray(), $order->get_shipping_methods());
					}

					$this->updateStatusOrAddNote($order, 'MDS auto processing has begun.', $processing, 'processing');

					if(!$this->isOrderInStock($order->get_items())) {
						$this->updateStatusOrAddNote($order, 'There are products in the order that are not in stock, auto processing aborted.', $processing, 'processing');
						return false;
					}

					$parcels = $this->getOrderContent($order->get_items());
					$defaults = $this->returnDefaultAddress();

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

					$orderTotal = $order->get_subtotal() + $order->get_cart_tax();
					$riskCover = ($this->settings['risk_cover']  == 'yes') & ($orderTotal > $this->settings['risk_cover_threshold']);
					$colliveryOptions = array(
						'collivery_from' => (int)$collivery_from,
						'contact_from' => (int)$contact_from,
						'collivery_to' => (int)$collivery_to,
						'contact_to' => (int)$contact_to,
						'cust_ref' => "Order number: " . $order_id,
						'instructions' => $instructions,
						'collivery_type' => 2,
						'service' => (int)$service_id,
						'cover' => $riskCover ? 1 : 0,
						'parcel_count' => count($parcels),
						'parcels' => $parcels
					);
					$collivery_id = $this->addCollivery($colliveryOptions);

					$collection_time = (isset($this->validated_data['collection_time'])) ? ' anytime from: ' . date('Y-m-d H:i', $this->validated_data['collection_time'])  : '';

					if($collivery_id) {
						// Save the results from validation into our table
						$this->addColliveryToProcessedTable($collivery_id, $order->id);
						$this->updateStatusOrAddNote($order, 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery_id . ', please have order ready for collection' . $collection_time . '.', $processing, 'completed');
					} else {
						throw new InvalidColliveryDataException('Collivery did not return a waybill id', 'automatedAddCollivery', $this->loggerSettingsArray(), array('data' => $colliveryOptions, 'errors' => $this->collivery->getErrors()));
					}
				} catch(InvalidColliveryDataException $e) {
					$this->updateStatusOrAddNote($order, 'There was a problem sending this the delivery request to MDS Collivery, you will need to manually process. Error: ' . $e->getMessage(), $processing, 'processing');
				} catch(InvalidAddressDataException $e) {
					$this->updateStatusOrAddNote($order, 'There was a problem sending this the delivery request to MDS Collivery, you will need to manually process. Error: ' . $e->getMessage(), $processing, 'processing');
				} catch (InvalidServiceException $e) {}
			} else {
				$order->add_order_note("MDS Collivery automated system did not fire, order already sent to MDS.");
			}
		} catch (OrderAlreadyProcessedException $e) {
			$order->add_order_note("MDS Collivery automated system could not fire, the database table is missing which stores the processed orders.");
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
	 * @throws InvalidAddressDataException
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
			throw new InvalidAddressDataException('Invalid location type', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['town']) || !isset($towns[$town_id])) {
			throw new InvalidAddressDataException('Invalid town', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['suburb']) || !isset($suburbs[$suburb_id])) {
			throw new InvalidAddressDataException('Invalid suburb', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['cellphone']) || !is_numeric($array['cellphone'])) {
			throw new InvalidAddressDataException('Invalid cellphone number', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
		}

		if(empty($array['email']) || !filter_var($array['email'], FILTER_VALIDATE_EMAIL)) {
			throw new InvalidAddressDataException('Invalid email address', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
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
	 * If the exception is thrown the base exception class will log this along with a check to see if the processed table exists
	 *
	 * @param int $orderId
	 *
	 * @return int
	 * @throws OrderAlreadyProcessedException
	 */
	public function hasOrderBeenProcessed($orderId)
	{
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$wpdb->prefix}mds_collivery_processed
			WHERE order_id=%d
		", $orderId ) );

		$tableName = $wpdb->prefix.'mds_collivery_processed';
		if($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
			throw new OrderAlreadyProcessedException("Database table $tableName does not exists so we cannot confirm if the order has been processed, unable to continue", $this->loggerSettingsArray(), [
				'order_id' => $orderId,
				'table' => $tableName
			]);
		}

		return $result;
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
	 * Returns true or false depending on if the plugin is enabled or not
	 *
	 * @return array
	 */
	public function isEnabled()
	{
		return $this->settings['enabled'] == 'yes';
	}

	/**
	 * Gets default address of the MDS Account
	 *
	 * @return array|bool
	 */
	public function returnDefaultAddress()
	{
		try {
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
		} catch (SoapConnectionException $e) {
			return array();
		}
	}

	/**
	 * @return null|array
	 */
	public function returnColliveryValidatedData()
	{
		return $this->validated_data;
	}

	/**
	 * @return array
	 */
	public function loggerSettingsArray()
	{
		return $this->enviroment->loggerFormat();
	}

	/**
	 * @return bool|string
	 */
	public function downloadLogFiles()
	{
		if($file = $this->logger->downloadErrorFile()) {
			return $file;
		}

		return false;
	}
}
