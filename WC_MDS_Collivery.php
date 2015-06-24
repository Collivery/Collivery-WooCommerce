<?php

/**
 * WC_MDS_Collivery class extending from WC_Shipping_Method class
 */
class WC_MDS_Collivery extends WC_Shipping_Method
{
	/**
	 * @type
	 */
	var $collivery;

	/**
	 * @type UnitConverter
	 */
	var $converter;

	/**
	 * @type
	 */
	var $validated_data;

	function __construct()
	{
		// Use the MDS API Files
		require_once( 'Mds/Cache.php' );
		require_once( 'Mds/Collivery.php' );

		// Class for converting lengths and weights
		require_once( 'SupportingClasses/UnitConverter.php' );
		$this->converter = new UnitConverter();

		$this->id = 'mds_collivery';
		$this->method_title = __('MDS Collivery', 'woocommerce-mds-shipping');
		$this->admin_page_heading = __('MDS Collivery', 'woocommerce-mds-shipping');
		$this->admin_page_description = __('Seamlessly integrate your website with MDS Collivery', 'woocommerce-mds-shipping');

		add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));

		$this->init();
	}

	/**
	 * Instantiates the plugin
	 */
	function init()
	{
		global $wp_version;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->enabled = $this->settings['enabled'];
		$this->title = $this->settings['title'];

		$this->collivery = new Mds\Collivery(array(
			'app_name' => 'WooCommerce MDS Shipping', // Application Name
			'app_version' => "2.0", // Application Version
			'app_host' => 'Wordpress: ' . $wp_version . ' - WooCommerce: ' . $this->wpbo_get_woo_version_number(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
			'app_url' => get_site_url(), // URL your site is hosted on
			'user_email' => $this->settings['mds_user'], // Your Mds account
			'user_password' => $this->settings['mds_pass'] // Your Mds account password
		));

		// Load the form fields that depend on the WS.
		$this->init_ws_form_fields();
	}

	/**
	 * Returns the MDS Collivery class
	 *
	 * @return \Mds\Collivery
	 */
	function get_collivery_class()
	{
		return $this->collivery;
	}

	/**
	 * Returns plugin settings
	 *
	 * @return array
	 */
	function get_collivery_settings()
	{
		return $this->settings;
	}

	/**
	 * Gets default address of the MDS Account
	 *
	 * @return array
	 */
	function get_default_address()
	{
		$default_address_id = $this->collivery->getDefaultAddressId();
		$data = array(
			'address' => $this->collivery->getAddress($default_address_id),
			'default_address_id' => $default_address_id,
			'contacts' => $this->collivery->getContacts($default_address_id)
		);
		return $data;
	}

	/**
	 * This function is here so we can get WooCommerce version number to pass on to the API for logs
	 */
	function wpbo_get_woo_version_number()
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

	/**
	 * Initial Plugin Settings
	 */
	function init_form_fields()
	{
		$fields = array(
			'enabled' => array(
				'title' => __('Enabled?', 'woocommerce-mds-shipping'),
				'type' => 'checkbox',
				'label' => __('Enable this shipping method', 'woocommerce-mds-shipping'),
				'default' => 'yes',
			),
			'title' => array(
				'title' => __('Method Title', 'woocommerce-mds-shipping'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-mds-shipping'),
				'default' => __('MDS Collivery', 'woocommerce-mds-shipping'),
			),
			'mds_user' => array(
				'title' => "MDS " . __('Username', 'woocommerce-mds-shipping'),
				'type' => 'text',
				'description' => __('Email address associated with your MDS account.', 'woocommerce-mds-shipping'),
				'default' => "api@collivery.co.za",
			),
			'mds_pass' => array(
				'title' => "MDS " . __('Password', 'woocommerce-mds-shipping'),
				'type' => 'text',
				'description' => __('The password used when logging in to MDS.', 'woocommerce-mds-shipping'),
				'default' => "api123",
			),
			'risk_cover' => array(
				'title' => "MDS " . __('Risk Cover', 'woocommerce-mds-shipping'),
				'type' => 'checkbox',
				'description' => __('Risk cover, up to a maximum of R5000.', 'woocommerce-mds-shipping'),
				'default' => 'yes',
			),
			'round' => array(
				'title' => "MDS " . __('Round Price', 'woocommerce-mds-shipping'),
				'type' => 'checkbox',
				'description' => __('Rounds price up.', 'woocommerce-mds-shipping'),
				'default' => 'yes',
			),
		);

		$this->form_fields = $fields;
	}

	/**
	 * Next Plugin Settings after class is instantiated
	 */
	function init_ws_form_fields()
	{
		$fields = $this->form_fields;
		$services = $this->collivery->getServices();

		foreach ($services as $id => $title) {
			$fields['method_' . $id] = array(
				'title' => __($title, 'woocommerce-mds-shipping'),
				'label' => __($title . ': Enabled', 'woocommerce-mds-shipping'),
				'type' => 'checkbox',
				'default' => 'yes',
			);
			$fields['markup_' . $id] = array(
				'title' => __($title . ' Markup', 'woocommerce-mds-shipping'),
				'type' => 'number',
				'default' => '10',
				'custom_attributes' => array(
					'step' 	=> 'any',
					'min'	=> '0'
				)
			);
			$fields['wording_' . $id] = array(
				'title' => __($title . ' Wording', 'woocommerce-mds-shipping'),
				'type' => 'text',
				'default' => $title,
				'class' => 'sectionEnd'
			);
		}

		$fields['method_free'] = array(
			'title' => __('Free Delivery', 'woocommerce-mds-shipping'),
			'label' => __('Free Delivery: Enabled', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'default' => 'yes',
		);

		$fields['wording_free'] = array(
			'title' => __('Free Delivery Wording', 'woocommerce-mds-shipping'),
			'type' => 'text',
			'default' => 'Free Delivery',
		);

		$fields['free_min_total'] = array(
			'title' => __('Free Delivery Min Total', 'woocommerce-mds-shipping'),
			'type' => 'number',
			'description' => __('Min order total before free delivery is included, amount is including vat.', 'woocommerce-mds-shipping'),
			'default' => '1000.00',
			'custom_attributes' => array(
				'step' 	=> 'any',
				'min'	=> '0'
			)
		);

		$fields['free_local_only'] = array(
			'title' => __('Free Delivery Local Only', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'description' => __('Only allow free delivery for local deliveries only. ', 'woocommerce-mds-shipping'),
			'default' => 'no',
		);

		$this->form_fields = $fields;
	}

	/**
	 * Function used by Woocommerce to fetch shipping price
	 *
	 * @param array $package
	 * @return bool
	 */
	function calculate_shipping($package = array())
	{
		if($this->valid_package($package)) {
			if(isset($package['service']) && $package['service'] == 'free') {
				$rate = array(
					'id' => 'mds_free',
					'label' => (!empty($this->settings["wording_free"])) ? $this->settings["wording_free"] : "Free Delivery",
					'cost' => 0.0,
				);

				$this->add_rate($rate);
			} elseif(!isset($package['service']) || (isset($package['service']) && $package['service'] != 'free')) {
				$services = $this->collivery->getServices();

				// Get pricing for each service
				foreach ($services as $id => $title) {
					if ($this->settings["method_$id"] == 'yes') {
						// Now lets get the price for
						$data = array(
							"to_town_id" => $package['destination']['to_town_id'],
							"from_town_id" => $package['destination']['from_town_id'],
							"to_location_type" => $package['destination']['to_location_type'],
							"from_location_type" => $package['destination']['from_location_type'],
							"cover" => ($this->settings['risk_cover'] == 'yes') ? (1) : (0),
							"weight" => $package['cart']['max_weight'],
							"num_package" => $package['cart']['count'],
							"parcels" => $package['cart']['products'],
							"exclude_weekend" => 1,
							"service" => $id,
						);

						// query the API for our prices
						$response = $this->collivery->getPrice($data);
						if (isset($response['price']['inc_vat'])) {
							if((empty($this->settings["wording_$id"]) || $this->settings["wording_$id"] != $title) && ($id == 1 || $id == 2)) {
								$title = $title . ', additional 24 hours on outlying areas';
							}

							$rate = array(
								'id' => 'mds_' . $id,
								'label' => (!empty($this->settings["wording_$id"])) ? $this->settings["wording_$id"] : $title,
								'cost' => $this->add_markup($response['price']['inc_vat'], $this->settings['markup_' . $id]),
							);

							$this->add_rate($rate);
						}
					}
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Validate the package before using the package to get prices
	 *
	 * @param $package
	 * @return bool
	 */
	function valid_package($package)
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
	 * Work through our shopping cart
	 * Convert lengths and weights to desired unit
	 *
	 * @param $package
	 * @return array
	 */
	function get_cart_content($package)
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

				for ($i = 0; $i < $qty; $i++) {
					// Length conversion, mds collivery only accepts cm
					if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
						$length = $this->converter->convert($_product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
						$width = $this->converter->convert($_product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
						$height = $this->converter->convert($_product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					} else {
						$length = $_product->length;
						$width = $_product->width;
						$height = $_product->height;
					}

					// Weight conversion, mds collivery only accepts kg
					if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
						$weight = $this->converter->convert($_product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
					} else {
						$weight = $_product->get_weight();
					}

					$cart['products'][] = array(
						'length' => $length,
						'width' => $width,
						'height' => $height,
						'weight' => $weight
					);
				}
			}
		}

		return $cart;
	}

	/**
	 * Used to build the package for use out of the shipping class
	 *
	 * @return array
	 */
	function build_package_from_cart()
	{
		$package = array();
		$cart = WC()->cart->get_cart();

		if(!empty($cart)) {
			foreach($cart as $item) {
				$product = $item['data'];

				$package['contents'][$item['product_id']] = [
					'data' => $item['data'],
					'quantity' => $item['quantity'],
					'line_subtotal' => $item['ine_subtotal'],
					'weight' => $product->get_weight() * $item['quantity']
				];
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
	function get_order_content($items)
	{
		$parcels = array();
		foreach ($items as $item_id => $item) {
			$product = new WC_Product($item['product_id']);
			$qty = $item['item_meta']['_qty'][0];

			for ($i = 0; $i < $qty; $i++) {
				// Length conversion, mds collivery only accepts cm
				if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
					$length = $this->converter->convert($product->length, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					$width = $this->converter->convert($product->width, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
					$height = $this->converter->convert($product->height, strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
				} else {
					$length = $product->length;
					$width = $product->width;
					$height = $product->height;
				}

				// Weight conversion, mds collivery only accepts kg
				if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
					$weight = $this->converter->convert($product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
				} else {
					$weight = $product->get_weight();
				}

				$parcels[] = array(
					'length' => (empty($length)) ? (0) : ($length),
					'width' => (empty($width)) ? (0) : ($width),
					'height' => (empty($height)) ? (0) : ($height),
					'weight' => (empty($weight)) ? (0) : ($weight)
				);
			}
		}
		return $parcels;
	}

	/**
	 * Adds the delivery request to MDS Collivery
	 *
	 * @param array $array
	 * @param bool $accept
	 * @throws Exception
	 * @return bool
	 */
	public function add_collivery(array $array, $accept=false)
	{
		$this->validated_data = $this->validate_collivery($array);
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
	public function validate_collivery(array $array)
	{
		$addresses = array();
		foreach($this->collivery->getAddresses() as $row) {
			$addresses[$row['address_id']] = $row;
		}

		if(empty($array['collivery_from']) || !is_numeric($array['collivery_from']) || !isset($addresses[$array['collivery_from']])) {
			throw new Exception("Invalid collection address");
		}

		if(empty($array['collivery_to']) || !is_numeric($array['collivery_to']) || !isset($addresses[$array['collivery_to']])) {
			throw new Exception("Invalid destination address");
		}

		$from_contacts = array();
		foreach($this->collivery->getContacts($array['collivery_from']) as $row) {
			$from_contacts[$row['contact_id']] = $row;
		}

		$to_contacts = array();
		foreach($this->collivery->getContacts($array['collivery_to']) as $row) {
			$to_contacts[$row['contact_id']] = $row;
		}

		if(empty($array['contact_from']) || !is_numeric($array['contact_from']) || !isset($to_contacts[$array['contact_from']])) {
			throw new Exception("Invalid collection contact");
		}

		if(empty($array['contact_to']) || !is_numeric($array['contact_to']) || !isset($addresses[$array['contact_to']])) {
			throw new Exception("Invalid destination contact");
		}

		if(empty($array['cellphone']) || !is_numeric($array['cellphone'])) {
			throw new Exception("Invalid cellphone number");
		}

		if(empty($array['collivery_type']) || !is_numeric($array['collivery_type'])) {
			throw new Exception("Invalid parcel type");
		}

		if(empty($array['service']) || !is_numeric($array['service'])) {
			throw new Exception("Invalid service");
		}

		if(empty($array['cover']) || !is_bool($array['cover'])) {
			throw new Exception("Invalid risk cover option");
		}

		if(empty($array['parcels']) || !is_array($array['parcels'])) {
			throw new Exception("Invalid parcels");
		}

		return $this->collivery->validateCollivery($array);
	}

	/**
	 * Adds an address to MDS Collivery
	 *
	 * @param array $array
	 * @return array
	 * @throws Exception
	 */
	public function add_collivery_address(array $array)
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

		if(empty($array['location_type']) || !is_numeric($location_type_id) || !isset($location_types[$location_type_id])) {
			throw new Exception("Invalid location type");
		}

		if(empty($array['town']) || !is_numeric($town_id) || !isset($towns[$town_id])) {
			throw new Exception("Invalid town");
		}

		if(empty($array['suburb']) || !is_numeric($suburb_id) || !isset($suburbs[$suburb_id])) {
			throw new Exception("Invalid suburb");
		}

		if(empty($array['cellphone']) || !is_numeric($array['cellphone'])) {
			throw new Exception("Invalid cellphone number");
		}

		if(empty($array['email']) || !filter_var($array['email'], FILTER_VALIDATE_EMAIL)) {
			throw new Exception("Invalid email address");
		}

		$result = $this->collivery->addAddress(array(
			'company_name' => $array['company_name'],
			'building' => $array['building'],
			'street' => $array['street'],
			'location_type' => $location_type_id,
			'suburb_id' => $suburb_id,
			'town_id' => $town_id,
			'full_name' => $array['full_name'],
			'cellphone' => $array['cellphone'],
			'email' => $array['email'],
		));

		return isset($result['address_id']) && is_numeric($result['address_id']) && isset($result['contact_id']) && is_numeric($result['contact_id']) ? $result : null;
	}

	/**
	 * Get Town and Location Types for Checkout selects from MDS
	 */
	function get_field_defaults()
	{
		$towns = $this->collivery->getTowns();
		$location_types = $this->collivery->getLocationTypes();
		return array('towns' => array_combine($towns, $towns), 'location_types' => array_combine($location_types, $location_types));
	}

	/**
	 * Adds markup to price
	 *
	 * @param $price
	 * @param $markup
	 * @return float|string
	 */
	function add_markup($price, $markup)
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
	function format($price)
	{
		return number_format($price, 2, '.', '');
	}

	/**
	 * Rounds number up to the next highest integer
	 *
	 * @param $price
	 * @return float
	 */
	function round($price)
	{
		return ceil($this->format($price));
	}

	/**
	 * @return null|array
	 */
	function return_validated_data()
	{
		return $this->validated_data;
	}
}
