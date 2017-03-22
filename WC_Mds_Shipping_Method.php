<?php

use MdsExceptions\InvalidColliveryDataException;
use MdsSupportingClasses\EnvironmentInformationBag;

/**
 * WC_Mds_Shipping_Method class extending from WC_Shipping_Method class
 */
class WC_Mds_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * @type MdsSupportingClasses\Collivery
	 */
	var $collivery;

	/**
	 * @type MdsSupportingClasses\MdsCache
	 */
	var $cache;

	/**
	 * @type MdsSupportingClasses\UnitConverter
	 */
	var $converter;

	/**
	 * @type MdsSupportingClasses\MdsColliveryService
	 */
	var $collivery_service;

	/**
	 * WC_Mds_Shipping_Method constructor.
	 * @param int $instance_id
	 */
	function __construct($instance_id = 0)
	{
		parent::__construct($instance_id);

		$this->id = 'mds_collivery';
		$this->method_title = __('MDS Collivery shipping');
		$this->method_description  = __('MDS Collivery offers range of different delivery services');
		$this->admin_page_heading = __('MDS Collivery shipping');
		$this->admin_page_description = __('Seamlessly integrate your website with MDS Collivery');

		$this->supports = array(
			'settings',
			'shipping-zones',
			'instance-settings',
		);

		$this->init();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Instantiates the plugin
	 */
	function init()
	{
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title = $this->method_title;
		$this->enabled = $this->settings['enabled'];

		$this->init_mds_collivery();

		if($this->enabled == 'yes') {
			// Load the rest of the form fields
			$this->init_ws_form_fields();
		}

		add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));
	}

	/**
	 * Instantiates the MDS Collivery class
	 */
	function init_mds_collivery()
	{
		$this->collivery_service = MdsColliveryService::getInstance($this->settings);

		$this->collivery = $this->collivery_service->returnColliveryClass();
		$this->converter = $this->collivery_service->returnConverterClass();
		$this->cache = $this->collivery_service->returnCacheClass();
	}

	/**
	 * Initial Plugin Settings
	 */
	function init_form_fields()
	{
		$fields = array(
			'downloadLogs' => array(
				'title' => __('Clear Cache/Download Error Logs?'),
				'type' => 'text',
				'description' => __('If you have any errors with the MDS plugin, you can download log files and email them to integration@collivery.co.za for support, clearing cache can be useful if you have empty list of towns etc'),
				'placeholder' => admin_url() . 'admin.php?page=mds_download_log_files',
			),
			'enabled' => array(
				'title' => __('Enabled?'),
				'type' => 'checkbox',
				'label' => __('Enable this shipping method'),
				'default' => 'yes',
			),
			'mds_user' => array(
				'title' => "MDS " . __('Username'),
				'type' => 'text',
				'description' => __('Email address associated with your MDS account.'),
				'default' => "api@collivery.co.za",
			),
			'mds_pass' => array(
				'title' => "MDS " . __('Password'),
				'type' => 'text',
				'description' => __('The password used when logging in to MDS.'),
				'default' => "api123",
			)
		);

		$this->form_fields = $fields;
		$this->instance_form_fields = $fields;
	}

	/**
	 * Next Plugin Settings after class is instantiated
	 */
	function init_ws_form_fields()
	{
		$settingFields = new \MdsSupportingClasses\MdsSettings($this->form_fields);
		$fields = $settingFields->getFields();

		$this->form_fields = $fields;
		$this->instance_form_fields = $fields;
	}

	/**
	 * Initialise Settings for instances.
	 * Do not default the settings, rather just use the plugins standard settings
	 *
	 * @since 2.6.0
	 */
	public function init_instance_settings()
	{
		$this->instance_settings = $this->settings;
	}

	/**
	 * Admin Panel Options Processing
	 * - Saves the options to the DB
	 * - Used to validate MDS account details
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function process_admin_options()
	{
		$this->validate_settings_fields();

		if ( count( $this->errors ) > 0 ) {
			$this->display_errors();
			return false;
		} else {
			update_option( $this->plugin_id . $this->id . '_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->sanitized_fields ) );
			$this->init_settings();
			$this->collivery_service = $this->collivery_service->newInstance($this->settings);
			$this->collivery = $this->collivery_service->returnColliveryClass();
			$this->converter = $this->collivery_service->returnConverterClass();
			$this->cache = $this->collivery_service->returnCacheClass();
			return true;
		}
	}

	/**
	 * Validate Settings Field Data.
	 * - Used to validate MDS account details
	 *
	 * @since 1.0.0
	 * @uses method_exists()
	 * @param array $form_fields (default: array())
	 * @return bool
	 */
	public function validate_settings_fields($form_fields = array())
	{
		if ( ! $form_fields ) {
			$form_fields = $this->get_form_fields();
		}

		$this->sanitized_fields = array();

		foreach ($form_fields as $fieldKey => $field) {
			if (method_exists($this, 'get_field_value')) {
				// WooCommerce 2.6 Method
				$fieldValue = $this->get_field_value($fieldKey, $field);
			} else {
				// Pre-WooCommerce 2.6 method
				$fieldValue = $this->getFieldValueForBackwardsCompatibility($fieldKey, $field);
			}

			$this->sanitized_fields[$fieldKey] = $fieldValue;
		}

		$currentSettings = $this->settings;
		$newSettings = $this->sanitized_fields;
		$environmentBag = new EnvironmentInformationBag($currentSettings);

		try {
			if(!$existingAuthentication = $this->collivery->isCurrentInstanceAuthenticated()) {
				if($currentSettings['mds_user'] != 'api@collivery.co.za' || $currentSettings['mds_pass'] != 'api123') {
					$this->sanitized_fields['mds_user'] = 'api@collivery.co.za';
					$this->sanitized_fields['mds_pass'] = 'api123';
					throw new InvalidColliveryDataException('Incorrect MDS account details', 'WC_Mds_Shipping_Method::validate_settings_fields', $environmentBag->loggerFormat(), $form_fields);
				} else {
					$this->collivery_service->cache->delete();
					throw new InvalidColliveryDataException('Current instance is not authenticated', 'WC_Mds_Shipping_Method::validate_settings_fields', $environmentBag->loggerFormat(), $form_fields);
				}
			} elseif($currentSettings['mds_user'] != $newSettings['mds_user'] || $currentSettings['mds_pass'] != $newSettings['mds_pass']) {
				$newAuthentication = $this->collivery->isNewInstanceAuthenticated(array(
					'email' => $newSettings['mds_user'],
					'password' => $newSettings['mds_pass']
				));

				if(!$newAuthentication) {
					throw new InvalidColliveryDataException('Incorrect MDS account details', 'WC_Mds_Shipping_Method::validate_settings_fields', $environmentBag->loggerFormat(), $form_fields);
				}
			}
		} catch (InvalidColliveryDataException $e) {
			$this->errors[] = "Your MDS account details are incorrect, new settings have been discarded.";
			return false;
		}

		$this->collivery_service->newInstance($newSettings);
		$this->setttings = $newSettings;
		$this->init_mds_collivery();

		return true;
	}

	/**
	 * Function used by Woocommerce to fetch shipping price
	 *
	 * @param array $package
	 */
	function calculate_shipping($package = array())
	{
		if($this->collivery_service->validPackage($package)) {
			if(isset($package['service']) && $package['service'] == 'free') {

				if(isset($package['local']) && $package['local'] == 'yes') {
					$id = 'mds_' . $this->settings['free_local_default_service'];
				} else {
					$id = 'mds_' . $this->settings['free_default_service'];
				}

				$rate = array(
					'id' => $id,
					'label' => (!empty($this->settings["wording_free"])) ? $this->settings["wording_free"] : "Free Delivery",
					'cost' => 0.0,
				);

				$this->add_rate($rate);
			} elseif(!isset($package['service']) || (isset($package['service']) && $package['service'] != 'free')) {
				$services = $this->collivery->getServices();
				if(is_array($services)) {
					// Get pricing for each service
					foreach ($services as $id => $title) {
						if ($this->settings["method_$id"] == 'yes') {
							// Now lets get the price for
							$riskCover = 0;
							$cartTotal = $package['cart']['total'];
							if ($this->settings['risk_cover'] == 'yes' && ($cartTotal >= $this->settings['risk_cover_threshold'])) {
								$riskCover = 1;
							}

							$data = array(
								"to_town_id" => $package['destination']['to_town_id'],
								"from_town_id" => $package['destination']['from_town_id'],
								"to_location_type" => $package['destination']['to_location_type'],
								"from_location_type" => $package['destination']['from_location_type'],
								"cover" => $riskCover,
								"weight" => $package['cart']['weight'],
								"num_package" => $package['cart']['count'],
								"parcels" => $package['cart']['products'],
								"exclude_weekend" => 1,
								"service" => $id,
							);

							try {
								$price = $this->collivery_service->getPrice($data, $id, $package['cart']['total']);

								if((empty($this->settings["wording_$id"]) || $this->settings["wording_$id"] != $title) && ($id == 1 || $id == 2)) {
									$title = $title . ', additional 24 hours on outlying areas';
								}

								$label = (!empty($this->settings["wording_$id"])) ? $this->settings["wording_$id"] : $title;

								if($price <= 0) {
									$price = 0.00;
									$label .= ' - FREE!';
								}

								$this->add_rate(array(
									'id' => 'mds_' . $id,
									'value' => $id,
									'label' => $label,
									'cost' => $price,
								));
							} catch(InvalidColliveryDataException $e) {}
						}
					}
				}
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
	function get_cart_content($package)
	{
		return $this->collivery_service->getCartContent($package);
	}

	/**
	 * Creates an error message in the admin section
	 *
	 * @param string $message
	 */
	function admin_add_error($message)
	{
		$admin_settings = new WC_Admin_Settings();
		$admin_settings->add_error($message, "woocommerce-mds-shipping");
	}

	/**
	 * This function adds support for clients using WooCommerce < 2.6
	 *
	 * @param $key
	 * @param $field
	 *
	 * @return array
	 */
	protected function getFieldValueForBackwardsCompatibility($key, $field)
	{
		$type = empty($field['type']) ? 'text' : $field['type'];

		if (method_exists($this, 'validate_' . $key . '_field')) {
			return $this->{'validate_' . $key . '_field'}($key);
		} elseif (method_exists($this, 'validate_' . $type . '_field')) {
			return $this->{'validate_' . $type . '_field'}($key);
		}

		return $this->validate_text_field($key);
	}
}
