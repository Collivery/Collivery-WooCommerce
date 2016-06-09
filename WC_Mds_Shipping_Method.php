<?php

/**
 * WC_Mds_Shipping_Method class extending from WC_Shipping_Method class
 */
class WC_Mds_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * self
	 */
	private static $instance;

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

	public static function get_instance()
	{
		if (! self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function __construct()
	{
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
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title = $this->settings['title'];
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
			)
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

		$fields['include_product_titles'] = array(
			'title' => __('Include product titles', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'description' => __('Includes product titles in the delivery instructions, max 4096 characters', 'woocommerce-mds-shipping'),
			'default' => 'no',
		);

		$fields['risk_cover'] = array(
			'title' => "MDS " . __('Risk Cover', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'description' => __('Risk cover, up to a maximum of R5000.', 'woocommerce-mds-shipping'),
			'default' => 'yes',
		);

		$fields['risk_cover_threshold'] = array(
			'title' => __('Risk cover minimum'),
			'type' => 'decimal',
			'description' => __('The minimum price of cart items to enable') . ' MDS ' . __('risk cover<br><strong>Please read the <a href="https://collivery.net/terms" target="_blank">terms and conditions</a> on our website</strong>'),
			'default' => 0.00
        );

		$fields['round'] = array(
			'title' => "MDS " . __('Round Price', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'description' => __('Rounds price up.', 'woocommerce-mds-shipping'),
			'default' => 'yes',
		);

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

		$fields['free_default_service'] = array(
			'title' => __('Free Delivery Default Service', 'woocommerce-mds-shipping'),
			'type' => 'select',
			'options' => $services,
			'default' => 5,
			'description' => __('When free delivery is enabled, which default service do we use.', 'woocommerce-mds-shipping'),
		);

		$fields['free_local_default_service'] = array(
			'title' => __('Free Delivery Local Only Default Service', 'woocommerce-mds-shipping'),
			'type' => 'select',
			'options' => $services,
			'default' => 2,
			'description' => __('When free local only delivery is enabled, which default service do we use.', 'woocommerce-mds-shipping'),
		);

		$fields['toggle_automatic_mds_processing'] = array(
			'title' => __('Automatic MDS Processing', 'woocommerce-mds-shipping'),
			'type' => 'checkbox',
			'description' => __('When enabled deliveries for an order will be automatically processed. Please reffer to the manual for detailed information on implications on using this <a target="_blank" href="http://collivery.github.io/Collivery-WooCommerce/">Manual</a>', 'woocommerce-mds-shipping'),
			'default' => 'no',
		);

		$this->form_fields = $fields;
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

		foreach ( $form_fields as $k => $v ) {

			if ( empty( $v['type'] ) ) {
				$v['type'] = 'text'; // Default to "text" field type.
			}

			// Look for a validate_FIELDID_field method for special handling
			if ( method_exists( $this, 'validate_' . $k . '_field' ) ) {
				$field = $this->{'validate_' . $k . '_field'}( $k );
				$this->sanitized_fields[ $k ] = $field;

				// Look for a validate_FIELDTYPE_field method
			} elseif ( method_exists( $this, 'validate_' . $v['type'] . '_field' ) ) {
				$field = $this->{'validate_' . $v['type'] . '_field'}( $k );
				$this->sanitized_fields[ $k ] = $field;

				// Default to text
			} else {
				$field = $this->{'validate_text_field'}( $k );
				$this->sanitized_fields[ $k ] = $field;
			}
		}

		$currentSettings = $this->settings;
		$newSettings = $this->sanitized_fields;

		if(!$existingAuthentication = $this->collivery->isCurrentInstanceAuthenticated()) {
			if($currentSettings['mds_user'] != 'api@collivery.co.za' || $currentSettings['mds_pass'] != 'api123') {
				$this->admin_add_error("The current MDS account details were incorrect, account details have been reset to the test account.");
				$this->sanitized_fields['mds_user'] = 'api@collivery.co.za';
				$this->sanitized_fields['mds_pass'] = 'api123';
				return true;
			}
		} elseif($currentSettings['mds_user'] != $newSettings['mds_user'] || $currentSettings['mds_pass'] != $newSettings['mds_pass']) {
			$newAuthentication = $this->collivery->isNewInstanceAuthenticated(array(
				'email' => $newSettings['mds_user'],
				'password' => $newSettings['mds_pass']
			));

			if(!$newAuthentication) {
				$this->admin_add_error("Your MDS account details are incorrect, new settings have been discarded.");
				$this->errors[] = "Your MDS account details are incorrect, new settings have been discarded.";
				return false;
			}
		}

		return true;
	}

	/**
	 * Function used by Woocommerce to fetch shipping price
	 *
	 * @param array $package
	 * @return bool
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
								'value' => $id,
								'label' => (!empty($this->settings["wording_$id"])) ? $this->settings["wording_$id"] : $title,
								'cost' => $this->collivery_service->addMarkup($response['price']['inc_vat'], $this->settings['markup_' . $id]),
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
}
