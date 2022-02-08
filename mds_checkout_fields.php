<?php

use MdsSupportingClasses\MdsColliveryService;

$mds = MdsColliveryService::getInstance();

if ($mds->isEnabled()) {
    if (!function_exists('mds_custom_override_default_address_fields')) {
        /**
         * Override the Billing and Shipping fields.
         *
         * @param $address_fields
         *
         * @return array
         */
        function mds_custom_override_default_address_fields($address_fields) {
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
        function mds_custom_override_checkout_fields($address_fields) {
            $mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);

            $address_fields['billing'] = $mdsCheckoutFields->getCheckoutFields('billing');
            $address_fields['shipping'] = $mdsCheckoutFields->getCheckoutFields('shipping');

            return $address_fields;
        }

        add_filter('woocommerce_checkout_fields', 'mds_custom_override_checkout_fields', 10);
    }

    if (!function_exists('mds_custom_checkout_field_update_order_meta')) {
        /**
         * Save our location type field.
         *
         * @param $order_id
         */
        function mds_custom_checkout_field_update_order_meta($order_id)
        {
            foreach (['shipping_', 'billing_'] as $prefix) {
                foreach (['suburb', 'location_type'] as $field) {
                    if (!empty($_POST[$prefix . $field])) {
                        update_post_meta($order_id, $prefix . $field, sanitize_text_field($_POST[$prefix . $field]));
                    }
                }
            }
        }

        add_action('woocommerce_checkout_update_order_meta', 'mds_custom_checkout_field_update_order_meta');
    }

    if (!function_exists('mds_custom_override_checkout_fields')) {
        /**
         * Override the Billing and Shipping fields in Checkout.
         *
         * @param $address_fields
         *
         * @return array
         */
        function mds_custom_override_checkout_fields($address_fields) {
            $mdsCheckoutFields = new \MdsSupportingClasses\MdsCheckoutFields($address_fields);
            $mds = MdsColliveryService::getInstance();
            $address_fields['billing'] = $mdsCheckoutFields->getCheckoutFields('billing');
            $address_fields['shipping'] = $mdsCheckoutFields->getCheckoutFields('shipping');

            $only_virtual = true;

            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                // Check if there are non-virtual products
                if (!$cart_item['data']->is_virtual()) $only_virtual = false;
            }

            if ($only_virtual) {
                unset($address_fields['billing']['billing_company']);
                unset($address_fields['billing']['billing_address_1']);
                unset($address_fields['billing']['billing_address_2']);
                unset($address_fields['billing']['billing_city']);
                unset($address_fields['billing']['billing_postcode']);
                unset($address_fields['billing']['billing_country']);
                unset($address_fields['billing']['billing_state']);
                unset($address_fields['billing']['billing_suburb']);
                unset($address_fields['billing']['billing_phone']);
                unset($address_fields['billing']['billing_city_int']);
                unset($address_fields['billing']['billing_location_type']);

            }
            if(!$mds->isTownsSuburbsSearchEnabled())            {
                unset($address_fields['billing']['town_city_search']);
                unset($address_fields['shipping']['town_city_search']);
            }
            return $address_fields;
        }

        add_filter('woocommerce_checkout_fields', 'mds_custom_override_checkout_fields');
    }

    if(!$mds->isTownsSuburbsSearchEnabled()) {
        if (!function_exists('generate_towns')) {
            /**
             * Get the towns on province Change.
             *
             * @return string
             */
            function generate_towns()
            {
                $mds = MdsColliveryService::getInstance();

                $selectedTown = null;
                if (get_current_user_id() > 0) {
                    $selectedTown = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'] . 'city');
                }

                $collivery = $mds->returnColliveryClass();

                $towns = $collivery->getTowns();

                $key_value_array = [];
                foreach ($towns as $item) {
                    $key_value_array[$item['id']] = $item['name'];
                }

                wp_send_json(View::make('_options', [
                    'fields' => $key_value_array,
                    'placeholder' => 'Select town/city',
                    'selectedValue' => $selectedTown,
                ]));

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
                $mds = MdsColliveryService::getInstance();

                $selectedSuburb = null;
                if (get_current_user_id() > 0) {
                    $selectedSuburb = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'] . 'suburb');
                }

                if ((isset($_POST['parentValue'])) && ($_POST['parentValue'] != '')) {
                    $collivery = $mds->returnColliveryClass();

                    $towns = $collivery->getTowns();

                    $key_value_array = [];

                    foreach ($towns as $item) {
                        $key_value_array[$item['id']] = $item['name'];
                    }


                    $town_id = is_numeric($_POST['parentValue']) ?
                        $_POST['parentValue'] :
                        array_search($_POST['parentValue'], $key_value_array);


                    $fields = $collivery->getSuburbs($town_id);

                    if (!empty($fields)) {

                        $key_value_array = [];
                        foreach ($fields as $item) {
                            $key_value_array[$item['id']] = $item['name'];
                        }

                        wp_send_json(View::make('_options', [
                            'fields' => $key_value_array,
                            'placeholder' => 'Select suburb',
                            'selectedValue' => $selectedSuburb,
                        ]));
                    } else {
                        wp_send_json(View::make('_options', [
                            'placeholder' => 'Error retrieving data from server. Please try again later...',
                        ]));
                    }
                } else {
                    wp_send_json(View::make('_options', [
                        'placeholder' => 'First Select Town...',
                    ]));
                }
            }

            add_action('wp_ajax_mds_collivery_generate_suburbs', 'generate_suburbs');
            add_action('wp_ajax_nopriv_mds_collivery_generate_suburbs', 'generate_suburbs');
        }
    }

    if($mds->isTownsSuburbsSearchEnabled()) {
        if (!function_exists('generate_town_city_search')) {
            /**
             * Get the results on Town Suburb search
             *
             * @return string
             */
            function generate_town_city_search()
            {
                $mds = MdsColliveryService::getInstance();

                $selectedSuburb = null;
                if (get_current_user_id() > 0) {
                    $selectedSuburb = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'] . 'suburb');
                }

                if ((isset($_POST['search_text'])) && ($_POST['search_text'] != '')) {
                    $collivery = $mds->returnColliveryClass();
                    $fields = $collivery->searchTownSuburbs($_POST['search_text']);
                    if (!empty($fields)) {

                        $key_value_array = [];
                        foreach ($fields as $item) {
                            $key_value_array[$item['suburb']['id']]=$item['formatted_result'] ;

                        }
                        wp_send_json(View::make('_options', [
                            'fields' => $key_value_array,
                            'placeholder' => 'Select suburb',
                            'selectedValue' => $selectedSuburb,
                        ]));
                    } else {
                        wp_send_json(View::make('_options', [
                            'placeholder' => 'Error retrieving data from server. Please try again later...',
                        ]));
                    }
                } else {
                    wp_send_json(View::make('_options', [
                        'placeholder' => 'First Select Town...',
                    ]));
                }
            }

            add_action('wp_ajax_mds_collivery_generate_town_city_search', 'generate_town_city_search');
            add_action('wp_ajax_nopriv_mds_collivery_generate_town_city_search', 'generate_town_city_search');
        }

        if (!function_exists('generate_suburb')) {
            /**
             * Get the results on Suburb show
             *
             * @return string
             */
            function generate_suburb()
            {
                $mds = MdsColliveryService::getInstance();

                $selectedSuburb = null;
                if (get_current_user_id() > 0) {
                    $selectedSuburb = $mds->extractUserProfileField(get_current_user_id(), $_POST['db_prefix'] . 'suburb');
                }

                if ((isset($_POST['suburb_id'])) && ($_POST['suburb_id'] != '')) {
                    $collivery = $mds->returnColliveryClass();
                    $item = $collivery->getSuburb($_POST['suburb_id']);
                    if ($item !=null) {
                        $key_value_array[$item['id']] = $item['name'];

                        if($selectedSuburb===null)
                            $selectedSuburb= $item['id'];
                        wp_send_json(View::make('_options', [
                            'fields' => $key_value_array,
                            'placeholder' => 'Select suburb',
                            'selectedValue' => $selectedSuburb,
                        ]));
                    } else {
                        wp_send_json(View::make('_options', [
                            'placeholder' => 'Error retrieving data from server. Please try again later...',
                        ]));
                    }
                } else {
                    wp_send_json(View::make('_options', [
                        'placeholder' => 'First Select Town...',
                    ]));
                }
            }

            add_action('wp_ajax_mds_collivery_generate_suburb', 'generate_suburb');
            add_action('wp_ajax_nopriv_mds_collivery_generate_suburb', 'generate_suburb');
        }

        if (!function_exists('generate_town')) {
            /**
             * Get the results of Town search by suburb
             *
             * @return string
             */
            function generate_town()
            {
                $mds = MdsColliveryService::getInstance();

                if ((isset($_POST['suburb_id'])) && ($_POST['suburb_id'] != '')) {
                    $collivery = $mds->returnColliveryClass();
                    $item = $collivery->getSuburb($_POST['suburb_id']);
                    if ($item != null) {
                        $key_value_array[$item['town']['id']] = $item['town']['name'];
                        $id =$item['town']['id'];

                        wp_send_json(View::make('_options', [
                            'fields' => $key_value_array,
                            'placeholder' => 'Select suburb',
                            'selectedValue' => $id,
                        ]));
                    } else {
                        wp_send_json(View::make('_options', [
                            'placeholder' => 'Error retrieving data from server. Please try again later...',
                        ]));
                    }
                } else {
                    wp_send_json(View::make('_options', [
                        'placeholder' => 'First search suburb...',
                    ]));
                }
            }

            add_action('wp_ajax_mds_collivery_generate_town', 'generate_town');
            add_action('wp_ajax_nopriv_mds_collivery_generate_town', 'generate_town');
        }
        if (!function_exists('generate_province')) {
            /**
             * Get the results of Town search by suburb
             *
             * @return string
             */
            function generate_province()
            {
                $mds = MdsColliveryService::getInstance();

                if ((isset($_POST['suburb_id'])) && ($_POST['suburb_id'] != '')) {
                    $collivery = $mds->returnColliveryClass();
                    $item = $collivery->getSuburb($_POST['suburb_id']);
                    if ($item != null) {
                      $province = $item['town']['province'];
                        wp_send_json($province);
                    } else {
                        wp_send_json(View::make('_options', [
                            'placeholder' => 'Error retrieving data from server. Please try again later...',
                        ]));
                    }
                } else {
                    wp_send_json(View::make('_options', [
                        'placeholder' => 'First search suburb...',
                    ]));
                }
            }

            add_action('wp_ajax_mds_collivery_generate_province', 'generate_province');
            add_action('wp_ajax_nopriv_mds_collivery_generate_province', 'generate_province');
        }
    }
}
