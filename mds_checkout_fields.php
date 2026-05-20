<?php

use MdsSupportingClasses\MdsColliveryService;

$mds = MdsColliveryService::getInstance();

if ($mds->isEnabled()) {
    if (!function_exists('mds_is_checkout_blocks_context')) {
        function mds_is_checkout_blocks_context()
        {
            if (defined('REST_REQUEST') && REST_REQUEST && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wc/store/') !== false) {
                return true;
            }

            if (!function_exists('is_checkout') || !is_checkout()) {
                return false;
            }

            $post = get_post();

            return $post && function_exists('has_block') && has_block('woocommerce/checkout', $post);
        }
    }

    if (!function_exists('mds_custom_override_default_address_fields')) {
        /**
         * Override the Billing and Shipping fields.
         *
         * @param $address_fields
         *
         * @return array
         */
        function mds_custom_override_default_address_fields($address_fields) {
            if (mds_is_checkout_blocks_context()) {
                return $address_fields;
            }

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
            if (mds_is_checkout_blocks_context()) {
                return $address_fields;
            }

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
                        $order = wc_get_order( $order_id );
                        $order->update_meta_data($order_id, $prefix . $field, sanitize_text_field($_POST[$prefix . $field]));
                        $order->save();                    }
                }
            }
        }

        add_action('woocommerce_checkout_update_order_meta', 'mds_custom_checkout_field_update_order_meta');
    }

    if (!function_exists('mds_custom_address_fields_display')) {
        /**
         * Display address fields
         *
         * @param $address_fields
         */
        function mds_custom_address_fields_display($address_fields)
        {
            $suburbId = explode(',', $address_fields['city'])[0];
            $service = MdsColliveryService::getInstance();
            if(is_numeric($suburbId)) {
                $mdsSuburb = (object)$service->returnColliveryClass()->getSuburb($suburbId);
                $mdsTown = (object)$mdsSuburb->town;
                $address_fields['city'] = "{$mdsSuburb->name}, {$mdsTown->name}";
            }
            return $address_fields;
        }

        add_action('woocommerce_my_account_my_address_formatted_address', 'mds_custom_address_fields_display');
    }

    if (!function_exists('mds_custom_order_address_fields_display')) {
        /**
         * Display address fields
         *
         * @param $address_fields
         */
        function mds_custom_order_address_fields_display($address_fields)
        {
            $checkoutTownId = explode(',', $address_fields['city'])[0];
            if(is_numeric($checkoutTownId)) {
                $service = MdsColliveryService::getInstance();
                $towns = $service->returnColliveryClass()->getTowns();
                $townIndex = array_search($checkoutTownId, array_column($service->returnColliveryClass()->getTowns(), 'id'));
                $mdsTown = (object)$towns[$townIndex];
                $address_fields['city'] = $mdsTown->name;
            }
            return $address_fields;
        }
        add_action('woocommerce_get_order_address', 'mds_custom_order_address_fields_display');
    }

    if (!function_exists('mds_custom_order_billing_address_fields_display_with_suburb')) {
        /**
         * Display address fields
         *
         * @param $address_fields
         */
        function mds_custom_order_billing_address_fields_display_with_suburb($address_fields)
        {
            return getFields($address_fields, 'billing_');
        }

        function mds_custom_order_shipping_address_fields_display_with_suburb($address_fields)
        {
            return getFields($address_fields, 'shipping_');
        }

        function getFields($address_fields, string $prefix)
        {
            $service = MdsColliveryService::getInstance();
            if(WC()->cart) {
                $checkoutData = WC_Checkout::instance()->get_posted_data();
                if (array_key_exists("{$prefix}suburb", $checkoutData) && $checkoutData["{$prefix}suburb"]) {
                    $mdsSuburb = (object)$service->returnColliveryClass()->getSuburb($checkoutData["{$prefix}suburb"]);
                    $mdsSuburbName = $mdsSuburb->name;
                    $mdsTown = (object)$mdsSuburb->town;
                    $mdsTownName = $mdsTown->name;
                    $address_fields['city'] = "{$mdsSuburbName}, {$mdsTownName}";
                }
            }

            return $address_fields;
        }
        add_action('woocommerce_order_formatted_billing_address', 'mds_custom_order_billing_address_fields_display_with_suburb');
        add_action('woocommerce_order_formatted_shipping_address', 'mds_custom_order_shipping_address_fields_display_with_suburb');

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
                if ((isset($_POST['parentItem'])) && ($_POST['parentItem'] != '')) {
                    $province = str_replace('-', ' ',$_POST['parentItem']);
                    $towns = $collivery->getTowns('',$province);

                }else {
                    $towns = $collivery->getTowns();
                }
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
                            array_push($key_value_array,['id'=>$item['suburb']['id'],'text'=>$item['formatted_result']]);
                        }
                        wp_send_json($key_value_array);
                    } else {
                        wp_send_json(['id'=>0,'text'=>'Error retrieving data from server. Please try again later...']);
                    }
                } else {
                    wp_send_json(['id'=>0,'text'=>'Type in search item']);
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
                        wp_send_json($selectedSuburb);
                    } else {
                        wp_send_json('');
                    }
                } else {
                    wp_send_json('');
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
                        $id =$item['town']['id'];
                        wp_send_json($id);

                    } else {
                        wp_send_json('');

                    }
                } else {
                    wp_send_json('');
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
                        wp_send_json('');
                    }
                } else {
                    wp_send_json('');
                }
            }

            add_action('wp_ajax_mds_collivery_generate_province', 'generate_province');
            add_action('wp_ajax_nopriv_mds_collivery_generate_province', 'generate_province');
        }
    }
}

add_action('woocommerce_init', function () {
    if (!function_exists('woocommerce_register_additional_checkout_field')) return;

    $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
    $resources = \MdsSupportingClasses\MdsFields::getResources($mds);

    $location_types = [];
    $towns = [
        [
            'label' => 'Select Town',
            'value' => '',
        ],
    ];

    foreach (($resources['location_types'] ?? []) as $type) {
        $location_types[] = [
            'label' => $type['name'],
            'value' => (string) $type['id'],
        ];
    }

    foreach (($resources['towns'] ?? []) as $town) {
        $towns[] = [
            'label' => $town['name'],
            'value' => (string) $town['id'],
        ];
    }

    woocommerce_register_additional_checkout_field([
        'id'       => 'mds/location_type',
        'label'    => 'Location Type',
        'location' => 'address',
        'type'     => 'select',
        'required' => true,
        'options'  => $location_types,
    ]);

    if ($mds->isTownsSuburbsSearchEnabled()) {
        woocommerce_register_additional_checkout_field([
            'id'       => 'mds/suburb',
            'label'    => 'Town / City Search',
            'location' => 'address',
            'type'     => 'text',
            'required' => false,
        ]);
    } else {
        woocommerce_register_additional_checkout_field([
            'id'       => 'mds/town',
            'label'    => 'Town / City',
            'location' => 'address',
            'type'     => 'select',
            'required' => true,
            'options'  => $towns,
        ]);

        woocommerce_register_additional_checkout_field([
            'id'       => 'mds/suburb',
            'label'    => 'Suburb',
            'location' => 'address',
            'type'     => 'text',
            'required' => false,
        ]);
    }
});

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;

    $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
    $search_enabled = $mds->isTownsSuburbsSearchEnabled();

    wp_enqueue_script('selectWoo');

    wp_register_style('mds-blocks-checkout-fields', false);
    wp_enqueue_style('mds-blocks-checkout-fields');
    wp_add_inline_style('mds-blocks-checkout-fields', '
        .mds-blocks-suburb-field {
            display: block;
            width: 100%;
        }

        .mds-blocks-suburb-field .select2-container {
            display: block;
            height: 50px;
            width: 100% !important;
        }

        .mds-blocks-suburb-field .select2-selection--single {
            background-color: #fff;
            border: 1px solid #50575e;
            border-radius: 4px;
            box-sizing: border-box;
            display: block;
            font-family: inherit;
            height: 50px;
            min-height: 50px;
            padding: 22px 46px 4px 16px;
        }

        .mds-blocks-suburb-field .select2-selection__rendered {
            color: #2f3337 !important;
            font-family: inherit;
            font-size: 16px !important;
            font-weight: 400;
            line-height: 1.2;
            padding: 0 !important;
        }

        .mds-blocks-suburb-field .select2-selection__placeholder {
            color: #2f3337 !important;
            font-family: inherit;
            font-size: 16px;
            font-weight: 400;
        }

        .mds-blocks-suburb-field .select2-selection__arrow {
            height: 50px;
            right: 14px;
            top: 0;
        }

        .mds-blocks-suburb-field .select2-selection__arrow b {
            border-color: #2f3337 transparent transparent transparent;
        }

        .mds-blocks-suburb-field-label {
            color: #6b6f76;
            display: block;
            font-family: inherit;
            font-size: 13px;
            font-weight: 400;
            left: 16px;
            line-height: 1;
            pointer-events: none;
            position: absolute;
            top: 9px;
            z-index: 1;
        }

        .mds-blocks-suburb-field-wrap {
            position: relative;
            width: 100%;
        }

        .mds-blocks-suburb-field-error {
            color: #cc1818;
            display: none;
            font-size: 13px;
            font-weight: 600;
            margin-top: 8px;
        }

        .mds-blocks-suburb-field.has-error .select2-selection--single {
            border-color: #cc1818;
        }

        .mds-blocks-suburb-field.has-error .mds-blocks-suburb-field-error {
            display: block;
        }

        .wc-block-components-validation-error:has([id*="mds-suburb"]),
        .wc-block-components-validation-error:has([name*="mds/suburb"]) {
            display: none;
        }

        .mds-hide-woo-city {
            display: none !important;
        }
    ');

    $search_enabled_js = $search_enabled ? 'true' : 'false';

    wp_add_inline_script('selectWoo', "
        jQuery(function($) {
            var mdsTownSuburbSearchEnabled = {$search_enabled_js};

            function initMdsBlocksSuburb() {
                var field = $('input[name=\"mds/suburb\"], input[id*=\"mds-suburb\"], input[name*=\"mds/suburb\"]');

                if (!mdsTownSuburbSearchEnabled || !field.length || $('#mds_blocks_suburb_search').length) return;

                field.hide();
                field.siblings('label').hide();
                if (field.attr('id')) {
                    $('label[for=\"' + field.attr('id') + '\"]').hide();
                }

                var wrapper = $('<div class=\"mds-blocks-suburb-field mds-blocks-suburb-field-wrap\"></div>');
                var label = $('<span class=\"mds-blocks-suburb-field-label\">Town / City Search</span>');
                var select = $('<select id=\"mds_blocks_suburb_search\" style=\"width:100%\"></select>');
                var error = $('<div class=\"mds-blocks-suburb-field-error\" role=\"alert\">Please select a town / city search result</div>');

                field.before(wrapper);
                wrapper.append(label).append(select).append(error);

                select.selectWoo({
                    minimumInputLength: 3,
                    placeholder: 'Search town / city',
                    ajax: {
                        url: '" . admin_url('admin-ajax.php') . "',
                        type: 'POST',
                        dataType: 'json',
                        delay: 300,
                        data: function(params) {
                            return {
                                action: 'mds_collivery_generate_town_city_search',
                                search_text: params.term,
                                db_prefix: 'shipping_'
                            };
                        },
                        processResults: function(data) {
                            return { results: data };
                        }
                    }
                });

                select.on('select2:select', function(e) {
                    var selected = e.params.data;
                    var value = selected.id || '';
                    var label = selected.text || selected.id || '';
                    var input = field.get(0);

                    field.val(value).attr('value', value);

                    if (input) {
                        var valueDescriptor = Object.getOwnPropertyDescriptor(input, 'value');
                        var prototype = Object.getPrototypeOf(input);
                        var prototypeValueDescriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
                        var valueSetter = valueDescriptor && valueDescriptor.set;
                        var prototypeValueSetter = prototypeValueDescriptor && prototypeValueDescriptor.set;

                        if (valueSetter && prototypeValueSetter && valueSetter !== prototypeValueSetter) {
                            prototypeValueSetter.call(input, value);
                        } else if (valueSetter) {
                            valueSetter.call(input, value);
                        } else if (prototypeValueSetter) {
                            prototypeValueSetter.call(input, value);
                        }

                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        input.dispatchEvent(new Event('blur', { bubbles: true }));
                    }

                    $.post('" . admin_url('admin-ajax.php') . "', {
                        action: 'mds_blocks_set_town_city_search',
                        suburb_id: value,
                        label: label
                    });

                    document.cookie = 'mds_blocks_suburb_id=' + encodeURIComponent(value) + '; path=/; SameSite=Lax';
                    document.cookie = 'mds_blocks_town_city_label=' + encodeURIComponent(label) + '; path=/; SameSite=Lax';
                    wrapper.removeClass('has-error');

                    if (window.wp && window.wp.data && window.wc && window.wc.wcBlocksData && window.wc.wcBlocksData.validationStore) {
                        var validation = window.wp.data.dispatch(window.wc.wcBlocksData.validationStore);

                        if (validation && validation.clearValidationError) {
                            validation.clearValidationError('mds/suburb');
                            validation.clearValidationError('shipping_address_mds/suburb');
                            validation.clearValidationError('billing_address_mds/suburb');
                        }
                    }
                });
            }

            function initMdsBlocksTownSuburbSelects() {
                if (mdsTownSuburbSearchEnabled || $('#mds_blocks_suburb_select').length) return;

                var town = $('select[name=\"mds/town\"], select[id*=\"mds-town\"], select[name*=\"mds/town\"]');
                var suburbField = $('input[name=\"mds/suburb\"], input[id*=\"mds-suburb\"], input[name*=\"mds/suburb\"]');

                if (!town.length || !suburbField.length) return;

                suburbField.hide();
                suburbField.siblings('label').hide();
                if (suburbField.attr('id')) {
                    $('label[for=\"' + suburbField.attr('id') + '\"]').hide();
                }

                var wrapper = $('<div class=\"mds-blocks-suburb-field mds-blocks-suburb-field-wrap\"></div>');
                var label = $('<span class=\"mds-blocks-suburb-field-label\">Suburb</span>');
                var suburbSelect = $('<select id=\"mds_blocks_suburb_select\" style=\"width:100%\"><option value=\"\">First select town/city</option></select>');
                var error = $('<div class=\"mds-blocks-suburb-field-error\" role=\"alert\">Please select a suburb</div>');

                suburbField.before(wrapper);
                wrapper.append(label).append(suburbSelect).append(error);

                suburbSelect.selectWoo({
                    placeholder: 'Select suburb',
                    minimumResultsForSearch: 8
                });

                function setSuburbValue(value) {
                    suburbField.val(value).attr('value', value);
                    suburbField.trigger('input').trigger('change').trigger('blur');
                    wrapper.removeClass('has-error');
                    document.cookie = 'mds_blocks_suburb_id=' + encodeURIComponent(value) + '; path=/; SameSite=Lax';
                    document.cookie = 'mds_blocks_town_city_label=; Max-Age=0; path=/; SameSite=Lax';
                }

                function loadSuburbs(townId) {
                    setSuburbValue('');
                    suburbSelect.html('<option value=\"\">Loading suburbs...</option>').trigger('change.select2');

                    if (!townId) {
                        suburbSelect.html('<option value=\"\">First select town/city</option>').trigger('change.select2');
                        return;
                    }

                    $.ajax({
                        url: '" . admin_url('admin-ajax.php') . "',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'mds_collivery_generate_suburbs',
                            parentValue: townId,
                            db_prefix: 'shipping_'
                        },
                        success: function(response) {
                            suburbSelect.html(response || '<option value=\"\">Select suburb</option>').val('').trigger('change.select2');
                        },
                        error: function() {
                            suburbSelect.html('<option value=\"\">Error loading suburbs</option>').trigger('change.select2');
                        }
                    });
                }

                town.on('change', function() {
                    loadSuburbs($(this).val());
                    document.cookie = 'mds_blocks_town_id=' + encodeURIComponent($(this).val() || '') + '; path=/; SameSite=Lax';
                });

                suburbSelect.on('change', function() {
                    setSuburbValue($(this).val() || '');
                });

                if (town.val()) {
                    document.cookie = 'mds_blocks_town_id=' + encodeURIComponent(town.val() || '') + '; path=/; SameSite=Lax';
                    loadSuburbs(town.val());
                }
            }

            initMdsBlocksSuburb();
            initMdsBlocksTownSuburbSelects();

            function hideWooBlocksCityFields() {
                $('input, select').filter(function() {
                    var name = ($(this).attr('name') || '').toLowerCase();
                    var id = ($(this).attr('id') || '').toLowerCase();
                    var autocomplete = ($(this).attr('autocomplete') || '').toLowerCase();

                    if ($(this).closest('.mds-blocks-suburb-field').length) {
                        return false;
                    }

                    return name === 'city' ||
                        name === 'shipping_city' ||
                        name === 'billing_city' ||
                        id === 'shipping-city' ||
                        id === 'billing-city' ||
                        autocomplete === 'address-level2';
                }).each(function() {
                    var field = $(this);
                    var wrapper = field.closest('.wc-block-components-text-input, .wc-block-components-address-form__city, .wc-block-components-combobox, .wc-block-components-form-row, .form-row');

                    if (wrapper.length) {
                        wrapper.addClass('mds-hide-woo-city');
                    } else {
                        field.addClass('mds-hide-woo-city');
                        if (field.attr('id')) {
                            $('label[for=\"' + field.attr('id') + '\"]').addClass('mds-hide-woo-city');
                        }
                    }
                });
            }

            hideWooBlocksCityFields();
            setInterval(function() {
                initMdsBlocksSuburb();
                initMdsBlocksTownSuburbSelects();
                hideWooBlocksCityFields();
            }, 1000);

            function requireMdsBlocksSuburb(e) {
                var field = $('input[name=\"mds/suburb\"], input[id*=\"mds-suburb\"], input[name*=\"mds/suburb\"]');
                var wrapper = $('#mds_blocks_suburb_search, #mds_blocks_suburb_select').closest('.mds-blocks-suburb-field');
                var hasValue = Boolean(field.val() || $('#mds_blocks_suburb_search').val() || $('#mds_blocks_suburb_select').val());

                if (!wrapper.length || hasValue) {
                    return;
                }

                e.preventDefault();
                e.stopImmediatePropagation();
                wrapper.addClass('has-error');
                $('html, body').animate({ scrollTop: wrapper.offset().top - 120 }, 250);
            }

            $(document).on('click', '.wc-block-components-checkout-place-order-button, .wc-block-checkout__actions button[type=\"submit\"]', requireMdsBlocksSuburb);
            $(document).on('submit', 'form.wc-block-components-form, form.wc-block-checkout__form', requireMdsBlocksSuburb);
        });
    ");
});

add_action('woocommerce_set_additional_field_value', function ($key, $value, $group, $object) {
    if (!in_array($key, ['mds/location_type', 'mds/town', 'mds/suburb'], true)) return;

    $prefix = $group === 'shipping' ? '_shipping_' : '_billing_';

    if ($key === 'mds/location_type') {
        $object->update_meta_data($prefix . 'location_type', sanitize_text_field($value));
    }

    if ($key === 'mds/town') {
        $object->update_meta_data($prefix . 'city_int', sanitize_text_field($value));
    }

    if ($key === 'mds/suburb') {
        $object->update_meta_data($prefix . 'suburb', sanitize_text_field($value));
    }
}, 10, 4);

add_action('wp_ajax_mds_blocks_set_town_city_search', 'mds_blocks_set_town_city_search');
add_action('wp_ajax_nopriv_mds_blocks_set_town_city_search', 'mds_blocks_set_town_city_search');

if (!function_exists('mds_blocks_set_town_city_search')) {
    function mds_blocks_set_town_city_search()
    {
        if (!function_exists('WC') || !WC()->session) {
            wp_send_json_error();
        }

        $suburb_id = isset($_POST['suburb_id']) ? sanitize_text_field(wp_unslash($_POST['suburb_id'])) : '';
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';

        if ($suburb_id === '') {
            WC()->session->__unset('mds_blocks_town_city_search');
            wp_send_json_error();
        }

        WC()->session->set('mds_blocks_town_city_search', [
            'suburb_id' => $suburb_id,
            'label'     => $label,
        ]);

        wp_send_json_success();
    }
}

if (!function_exists('mds_blocks_get_town_city_search')) {
    function mds_blocks_get_town_city_search()
    {
        $town_city_search = [];

        if (function_exists('WC') && WC()->session) {
            $session_value = WC()->session->get('mds_blocks_town_city_search');

            if (is_array($session_value)) {
                $town_city_search = $session_value;
            }
        }

        if (empty($town_city_search['suburb_id']) && !empty($_COOKIE['mds_blocks_suburb_id'])) {
            $town_city_search['suburb_id'] = sanitize_text_field(wp_unslash($_COOKIE['mds_blocks_suburb_id']));
        }

        if (empty($town_city_search['label']) && !empty($_COOKIE['mds_blocks_town_city_label'])) {
            $town_city_search['label'] = sanitize_text_field(wp_unslash($_COOKIE['mds_blocks_town_city_label']));
        }

        if (empty($town_city_search['town_id']) && !empty($_COOKIE['mds_blocks_town_id'])) {
            $town_city_search['town_id'] = sanitize_text_field(wp_unslash($_COOKIE['mds_blocks_town_id']));
        }

        return $town_city_search;
    }
}

if (!function_exists('mds_blocks_apply_town_city_search_to_order')) {
    function mds_blocks_apply_town_city_search_to_order($order)
    {
        $town_city_search = mds_blocks_get_town_city_search();

        if (empty($town_city_search['suburb_id'])) {
            return;
        }

        $suburb_id = sanitize_text_field($town_city_search['suburb_id']);
        $label = isset($town_city_search['label']) ? sanitize_text_field($town_city_search['label']) : '';
        $suburb_value = $suburb_id;
        $town_value = '';

        if (is_numeric($suburb_id)) {
            $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
            $suburb = $mds->getSuburb($suburb_id);

            if (is_array($suburb) && isset($suburb['name'])) {
                $suburb_value = $suburb['name'];
            }

            if (is_array($suburb) && isset($suburb['town']['name'])) {
                $town_value = $suburb['town']['name'];
            }
        }

        $order->update_meta_data('_shipping_suburb', $suburb_value);
        $order->update_meta_data('_billing_suburb', $suburb_value);

        if ($town_value !== '') {
            $order->set_shipping_city($town_value);
            $order->set_billing_city($town_value);
        }

        if ($label !== '') {
            $order->update_meta_data('_shipping_town_city_search', $label);
            $order->update_meta_data('_billing_town_city_search', $label);
        }
    }
}

add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {
    if (!$request instanceof WP_REST_Request || $request->get_route() !== '/wc/store/v1/checkout') {
        return $response;
    }

    $town_city_search = mds_blocks_get_town_city_search();
    $suburb_id = '';
    $label = '';
    $town_id = !empty($town_city_search['town_id']) ? sanitize_text_field($town_city_search['town_id']) : '';
    $town_name = '';
    $province = '';

    if (!empty($town_city_search['suburb_id'])) {
        $suburb_id = sanitize_text_field($town_city_search['suburb_id']);
        $label = isset($town_city_search['label']) ? sanitize_text_field($town_city_search['label']) : '';

        if (is_numeric($suburb_id)) {
            $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
            $suburb = $mds->getSuburb($suburb_id);

            if (is_array($suburb) && isset($suburb['town']['id'])) {
                $town_id = (string) $suburb['town']['id'];
            }

            if (is_array($suburb) && isset($suburb['town']['name'])) {
                $town_name = (string) $suburb['town']['name'];
            }

            if (is_array($suburb) && isset($suburb['town']['province'])) {
                $province = (string) $suburb['town']['province'];
            }
        }
    }

    $billing_address = (array) $request->get_param('billing_address');
    $billing_email = !empty($billing_address['email']) ? sanitize_email($billing_address['email']) : '';

    foreach (['shipping_address', 'billing_address'] as $address_key) {
        $address = (array) $request->get_param($address_key);

        if ($town_id === '' && !empty($address['mds/town'])) {
            $town_id = sanitize_text_field($address['mds/town']);
        }

        if ($suburb_id !== '' && empty($address['mds/suburb'])) {
            $address['mds/suburb'] = $suburb_id;
        }

        if ($suburb_id !== '' && empty($address['suburb'])) {
            $address['suburb'] = $suburb_id;
        }

        if ($suburb_id !== '' && empty($address['town_city_search'])) {
            $address['town_city_search'] = $suburb_id;
        }

        if ($label !== '' && empty($address['mds/town_city_search_label'])) {
            $address['mds/town_city_search_label'] = $label;
        }

        if ($town_id !== '' && empty($address['city_int'])) {
            $address['city_int'] = $town_id;
        }

        if ($town_name !== '') {
            $address['city'] = $town_name;
        }

        if ($province !== '' && empty($address['state'])) {
            $address['state'] = $province;
        }

        if (empty($address['location_type']) && !empty($address['mds/location_type'])) {
            $address['location_type'] = sanitize_text_field($address['mds/location_type']);
        }

        if (empty($address['email']) && $billing_email !== '') {
            $address['email'] = $billing_email;
        }

        $request->set_param($address_key, $address);
    }

    return $response;
}, 5, 3);

add_action('woocommerce_checkout_create_order', function ($order) {
    mds_blocks_apply_town_city_search_to_order($order);
}, 5);

add_action('woocommerce_store_api_checkout_update_order_from_request', function ($order) {
    mds_blocks_apply_town_city_search_to_order($order);
}, 5);

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    mds_blocks_apply_town_city_search_to_order($order);
    $order->save();
}, 5);
