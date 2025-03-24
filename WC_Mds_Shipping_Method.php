<?php

if (!defined('ABSPATH')) {
    exit;
}

use MdsExceptions\CurlConnectionException;
use MdsExceptions\InvalidColliveryDataException;
use MdsExceptions\InvalidResourceDataException;
use MdsSupportingClasses\Collivery;
use MdsSupportingClasses\MdsColliveryService;
use MdsSupportingClasses\MdsFields;
use MdsSupportingClasses\MdsLogger;
use MdsSupportingClasses\MdsSettings;

/**
 * WC_Mds_Shipping_Method class extending from WC_Shipping_Method class.
 */
class WC_Mds_Shipping_Method extends WC_Shipping_Method
{
    /**
     * @var MdsSupportingClasses\Collivery
     */
    public $collivery;

    /**
     * @var MdsSupportingClasses\MdsCache
     */
    public $cache;
    /**
     * @var MdsSupportingClasses\MdsColliveryService
     */
    public $collivery_service;

    /**
     * @var MdsSettings
     */
    private $mdsSettings;
    public $admin_page_description;
    public $admin_page_heading;
    public $method_description;
    public $method_title;

    /**
     * WC_Mds_Shipping_Method constructor.
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);

        $this->id = 'mds_collivery';
        $this->method_title = __('MDS Collivery shipping');
        $this->method_description = __('MDS Collivery offers range of different delivery services');
        $this->admin_page_heading = __('MDS Collivery shipping');
        $this->admin_page_description = __('Seamlessly integrate your website with MDS Collivery');

        $this->supports = [
            'settings',
            'shipping-zones',
            'instance-settings',
        ];

        $this->init();

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
    }

    /**
     * Instantiates the plugin.
     */
    public function init()
    {
        $this->title = $this->method_title;

        // Load the form fields.
        $this->init_form_fields();
        $this->init_mds_collivery();
        $this->init_instance_form_fields();
        $this->mdsSettings = $this->collivery_service->initSettings($this->settings, $this->instance_settings);

        $this->enabled = $this->mdsSettings->getValue('enabled', $this->enabled);

        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
    }

    /**
     * Instantiates the MDS Collivery class.
     */
    public function init_mds_collivery()
    {
        $this->collivery_service = MdsColliveryService::getInstance($this->settings);
        $this->collivery = $this->collivery_service->returnColliveryClass();
        $this->cache = $this->collivery_service->returnCacheClass();
    }

    /**
     * Initial Plugin Settings.
     */
    public function init_form_fields()
    {
        $this->form_fields = MdsFields::getFields();
        $this->init_settings();
    }

    /**
     * Next Plugin Settings after class is instantiated.
     */
    public function init_instance_form_fields()
    {
        $this->instance_form_fields = MdsFields::instanceFields($this->collivery_service);
        $this->init_instance_settings();
    }

    public function generate_radio_html($name, array $options) {
        $fieldName = esc_attr($this->get_field_key($name));
        $defaults  = [
            'title'             => '',
            'label'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'type'              => 'radio',
            'desc_tip'          => false,
            'description'       => '',
            'default'           => false,
            'custom_attributes' => [],
            'options'           => [],
        ];

        $options = wp_parse_args($options, $defaults);

        if (!$options['label']) {
            $options['label'] = $options['title'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo $fieldName; ?>">
                    <?php echo wp_kses_post($options['title']); ?><?php echo $this->get_tooltip_html($options); ?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($options['title']); ?></span></legend>
                        <?php foreach ($options['options'] as $key => $text): ?>
                        <?php $uniqid = $fieldName.'_'.esc_attr($key); ?>
                            <label for="<?php echo $uniqid; ?>">
                                <input
                                    <?php disabled($options['disabled'], true); ?>
                                    class="<?php echo esc_attr($options['class']); ?>"
                                    type="radio"
                                    name="<?php echo $fieldName; ?>"
                                    id="<?php echo $uniqid; ?>"
                                    style="<?php echo esc_attr($options['css']); ?>"
                                    value="<?php echo esc_attr($key); ?>"
                                    <?php checked($this->get_option($name), $key); ?>
                                    <?php echo $this->get_custom_attribute_html($options);?> />

                                <?php echo esc_attr($text); ?>
                          </label> <br>
                        <?php endforeach; ?>
                    <?php echo $this->get_description_html($options); ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Function used by Woocommerce to fetch shipping price.
     *
     * @param array $package
     */
    public function calculate_shipping($package = []) {
        if ( ! isset( $package['destination']['from_town_id'] ) || !$this->collivery_service->validPackage( $package ) ) {
            return;
        }
        if ( ( $package['service'] ?? false ) == 'free' ) {
            if ( isset( $package['local'] ) && $package['local'] == 'yes' ) {
                $id = 'mds_' . $this->mdsSettings->getInstanceValue( 'free_local_default_service' );
            } else {
                $id = 'mds_' . $this->mdsSettings->getInstanceValue( 'free_default_service' );
            }

            $this->id = $id;
            $this->add_rate( [
                'id'    => $id,
                'label' => $this->mdsSettings->getInstanceValue( 'wording_free', 'Free Delivery' ),
                'cost'  => 0.0,
            ] );

            return;
        }
        try {
            $services = array_filter(
                $this->collivery->getServices(),
                function (array $service) {
                    return $this->mdsSettings->getInstanceValue( 'method_' . $service['id'] ) === 'yes';
                }
            );
            if ( ! is_array( $services ) ) {
                return;
            }
            // Get pricing for each service
            foreach ( $services as $service ) {
                // Now lets get the price for
                $adjustedTotal    = $package['shipping_cart_total'];
                $riskCoverEnabled = $this->mdsSettings->getValue( 'risk_cover' ) == 'yes';
                $overThreshold    = $adjustedTotal >= $this->mdsSettings->getValue(
                        'risk_cover_threshold',
                        1000
                    );
                $riskCover        = $riskCoverEnabled && $overThreshold;

                $data = [
                    'delivery_town'            => $package['destination']['to_town_id'],
                    'collection_town'          => $package['destination']['from_town_id'],
                    'delivery_location_type'   => $package['destination']['to_location_type'],
                    'collection_location_type' => $package['destination']['from_location_type'],
                    'risk_cover'               => $riskCover,
                    'parcels'                  => $package['contents'],
                    'exclude_weekend'          => true,
                    'services'                 => [ $service['id'] ],
                ];

                // Add the requested time to ONX before 10
                if ( $service['id'] === Collivery::ONX_10 ) {
                    $data['delivery_time'] = 'next weekday 10 am';
                    $data['services']      = [ Collivery::ONX ];
                }

                // Looks like it's being executed here;
                $price = $this->collivery_service->getPrice(
                    $data,
                    $adjustedTotal,
                    $this->mdsSettings->getInstanceValue( 'markup_' . $service['id'] ),
                    $this->mdsSettings->getInstanceValue( 'fixed_price_' . $service['id'] ) );

                if ( $this->mdsSettings->getInstanceValue( 'wording_' . $service['id'] ) !== '' ) {
                    $service['text'] = $this->mdsSettings->getInstanceValue( 'wording_' . $service['id'] );
                }
                if ( in_array( $service['id'], [ Collivery::ONX, Collivery::ONX_10 ] ) ) {
                    $service['text'] .= ', additional 24 hours on outlying areas';
                }

                $label = $service['text'];
                if ($price <= 0) {
                    $price = 0.00;
                    wc_add_notice(
                        __('Due to the dimensions of the product and location of the delivery, there is no price available. Please contact us for a quote.'),
                        'notice'
                    );
                    return;
                }

                $this->id = 'mds_' . $service['id'];
                $this->add_rate( [
                    'id'    => 'mds_' . $service['id'],
                    'value' => $service['id'],
                    'label' => $label,
                    'cost'  => $price,
                ] );
            }
        } catch ( CurlConnectionException $e ) {
            ( new MdsLogger() )->error( 'WC_Mds_Shipping_Method::calculate_shipping()',
                $e->getMessage(),
                $this->collivery_service->loggerSettingsArray(),
                $package );
        } catch ( InvalidColliveryDataException $e ) {
            ( new MdsLogger() )->error( 'WC_Mds_Shipping_Method::calculate_shipping()',
                $e->getMessage(),
                $this->collivery_service->loggerSettingsArray(),
                $package );
        }
    }

    /**
     * Before saving the username and password we need to validate them.
     *
     * @return bool
     */
    public function process_admin_options()
    {
        if ($this->instance_id) {
            return parent::process_admin_options();
        }

        $error = false;
        $authentication = true;
        $postData = $this->get_post_data();
        $userNameKey = $this->plugin_id.$this->id.'_';
        $passwordKey = $this->plugin_id.$this->id.'_';
        $userName = trim($postData[$userNameKey.'mds_user']);
        $password = trim($postData[$passwordKey.'mds_pass']);

        if (!filter_var($userName, FILTER_VALIDATE_EMAIL)) {
            $error = 'Your MDS Username is not a valid email address, unable to save your Your MDS Username or Password';
        } else {
            $authentication = $this->collivery->makeAuthenticationRequest([
                'email' => $userName,
                'password' => $password,
            ]);
    
            try {
                if (!$authentication) {
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

        if ($error) {
            unset($postData[$userNameKey.'mds_user']);
            unset($postData[$passwordKey.'mds_pass']);
            $this->set_post_data($postData);
            $this->add_error($error);
        }

        $this->display_errors();

        $result = $authentication && parent::process_admin_options();
        if ($result && !$error) {
            $this->collivery_service = $this->collivery_service->newInstance($this->settings);

            return true;
        } else {
            return false;
        }
    }
}
