<?php

if (!function_exists('mds_custom_override_default_address_fields')) {
    /**
     * Override the Billing and Shipping fields.
     *
     * @param $address_fields
     *
     * @return array
     */
    function mds_custom_override_default_address_fields($address_fields)
    {
        $mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);

        return $mdsCheckoutFields->getCheckoutFields();
    }

    add_filter('woocommerce_default_address_fields', 'mds_custom_override_default_address_fields');
}

if (!function_exists('mds_custom_override_checkout_fields')) {
    /**
     * Override the Billing and Shipping fields in Checkout.
     *
     * @param $address_fields
     *
     * @return array
     */
    function mds_custom_override_checkout_fields($address_fields)
    {
        $mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);

        $address_fields['billing'] = $mdsCheckoutFields->getCheckoutFields('billing');
        $address_fields['shipping'] = $mdsCheckoutFields->getCheckoutFields('shipping');

        return $address_fields;
    }

    add_filter('woocommerce_checkout_fields', 'mds_custom_override_checkout_fields');
}

if (!function_exists('mds_custom_checkout_field_update_order_meta')) {
    /**
     * Save our location type field.
     *
     * @param $order_id
     */
    function mds_custom_checkout_field_update_order_meta($order_id)
    {
        $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();

        if ($mds->isEnabled()) {
            foreach (array('shipping_', 'billing_') as $prefix) {
                foreach (array('suburb', 'location_type') as $field) {
                    if (!empty($_POST[$prefix.$field])) {
                        update_post_meta($order_id, $prefix.$field, sanitize_text_field($_POST[$prefix.$field]));
                    }
                }
            }
        }
    }

    add_action('woocommerce_checkout_update_order_meta', 'mds_custom_checkout_field_update_order_meta');
}

if (!function_exists('generate_towns')) {
    /**
     * Get the towns on province Change.
     *
     * @return string
     */
    function generate_towns()
    {
        $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();

        $selectedTown = null;
        if (get_current_user_id() > 0) {
            $selectedTown = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'].'city');
        }

        if (isset($_POST['parentValue']) && $_POST['parentValue'] != '') {
            $collivery = $mds->returnColliveryClass();
            $provinceMap = array(
                'EC' => 'EC',
                'FS' => 'OFS',
                'GP' => 'GAU',
                'KZN' => 'KZN',
                'LP' => 'NP',
                'MP' => 'MP',
                'NC' => 'NC',
                'NW' => 'NW',
                'WC' => 'CAP',
            );
            $province = isset($provinceMap[$_POST['parentValue']]) ? $provinceMap[$_POST['parentValue']] : 'unknown';
            echo View::make('_options', array(
                'fields' => $collivery->getTowns('ZAF', $province),
                'placeholder' => 'Select town/city',
                'selectedValue' => $selectedTown,
            ));
        } else {
            echo View::make('_options', array(
                'placeholder' => 'First select province',
            ));
        }
    }

    add_action('wp_ajax_mds_collivery_generate_towns', 'generate_towns');
    add_action('wp_ajax_nopriv_mds_collivery_generate_towns', 'generate_towns');
}

if (!function_exists('generate_suburbs')) {
    /**
     * Get the Suburbs on Town Change.
     *
     * @return string
     */
    function generate_suburbs()
    {
        $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();

        $selectedSuburb = null;
        if (get_current_user_id() > 0) {
            $selectedSuburb = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'].'suburb');
        }

        if ((isset($_POST['parentValue'])) && ($_POST['parentValue'] != '')) {
            $collivery = $mds->returnColliveryClass();
            $town_id = array_search($_POST['parentValue'], $collivery->getTowns());
            $fields = $collivery->getSuburbs($town_id);

            if (!empty($fields)) {
                echo View::make('_options', array(
                    'fields' => $fields,
                    'placeholder' => 'Select suburb',
                    'selectedValue' => $selectedSuburb,
                ));
            } else {
                echo View::make('_options', array(
                    'placeholder' => 'Error retrieving data from server. Please try again later...',
                ));
            }
        } else {
            echo View::make('_options', array(
                'placeholder' => 'First Select Town...',
            ));
        }
    }

    add_action('wp_ajax_mds_collivery_generate_suburbs', 'generate_suburbs');
    add_action('wp_ajax_nopriv_mds_collivery_generate_suburbs', 'generate_suburbs');
}
