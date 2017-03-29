<?php

use MdsExceptions\InvalidColliveryDataException;
use MdsExceptions\SoapConnectionException;
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
	}

	/**
	 * Next Plugin Settings after class is instantiated
	 */
	function init_ws_form_fields()
	{

			$this->init_settings();
			$this->collivery_service = $this->collivery_service->newInstance($this->settings);
			$this->collivery = $this->collivery_service->returnColliveryClass();
			$this->converter = $this->collivery_service->returnConverterClass();
			$this->cache = $this->collivery_service->returnCacheClass();
			return true;
		}
	}

	/**
	 * Function used by Woocommerce to fetch shipping price
	 *
	 * @param array $package
	 * @throws InvalidResourceDataException
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
				try {
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
							}
						}
					}
				} catch (SoapConnectionException $e) {
				} catch (InvalidColliveryDataException $e) {
				}
			}
		}
	}

	/**
	 * Before saving the username and password we need to validate them
	 *
	 * @return bool
	 */
	public function process_admin_options()
	{
		if ($this->instance_id) {
			return parent::process_admin_options();
		}

		$error = false;
		$newAuthentication = true;
		$postData = $this->get_post_data();
		$userNameKey = $this->plugin_id.$this->id.'_';
		$passwordKey = $this->plugin_id.$this->id.'_';
		$userName = $postData[$userNameKey.'mds_user'];
		$password = $postData[$passwordKey.'mds_pass'];

		if ($this->get_option('mds_user') != $userName || $this->get_option('mds_pass') != $password) {
			if (!filter_var($userName, FILTER_VALIDATE_EMAIL)) {
				$error = 'Your MDS Username is not a valid email address, unable to save your Your MDS Username or Password';
			} else {
				$newAuthentication = $this->collivery->isNewInstanceAuthenticated(
					array(
						'email' => $postData[$this->plugin_id.$this->id.'_mds_user'],
						'password' => $postData[$this->plugin_id.$this->id.'_mds_pass'],
					)
				);
				try {
					if (!$newAuthentication) {
						throw new InvalidColliveryDataException(
							'Incorrect MDS account details, username and password discarded',
							'WC_Mds_Shipping_Method::validate_settings_fields',
							$this->collivery_service->loggerSettingsArray(),
							$postData
						);
					}
				} catch (InvalidColliveryDataException $e) {
					$error = $e->getMessage();
				}
			}
		} elseif (!$this->collivery->isCurrentInstanceAuthenticated()) {
			$this->add_error(
				'Your current MDS Username and or password was not valid, we have replaced them with the default'
			);
			$postData[$userNameKey.'mds_user'] = 'api@collivery.co.za';
			$postData[$passwordKey.'mds_pass'] = 'api123';
		}

		if ($error) {
			unset($postData[$userNameKey.'mds_user']);
			unset($postData[$passwordKey.'mds_pass']);
			$this->set_post_data($postData);
			$this->add_error($error);
		}

		$this->display_errors();

		$result = $newAuthentication && parent::process_admin_options();
		if ($result && !$error) {
			$this->collivery_service = $this->collivery_service->newInstance($this->settings);
			return true;
		} else {
			return false;
		}
	}
}
