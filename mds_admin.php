<?php

use MdsExceptions\InvalidColliveryDataException;
use MdsSupportingClasses\View;

/*******************************************************************************
 * This file contains all the functions used for the admin side of the plugin. *
 *******************************************************************************/

/*
 * Add our Admin menu items
 */
add_action('admin_menu', 'mds_admin_menu');

if (is_admin()) {
    if (isset($_GET['page'])) {
        if ($_GET['page'] == 'mds-confirmed-order-view-pdf') {
            add_action('wp_loaded', 'mds_confirmed_order_view_pdf');
        } elseif ($_GET['page'] == 'mds_download_log_files') {
            add_action('wp_loaded', 'mds_download_log_files');
        } elseif ($_GET['page'] == 'mds_clear_cache_files') {
            add_action('wp_loaded', 'mds_clear_cache_files');
        }
    }
}

/**
 * Add plugin admin menu items.
 */
function mds_admin_menu()
{
    $first_page = add_submenu_page('woocommerce', 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds-already-confirmed', 'mds_confirmed_orders');
    add_submenu_page($first_page, 'MDS Confirmed', 'MDS Confirmed', 'manage_options', 'mds_confirmed', 'mds_confirmed_order');
}

/**
 * Download the error log file.
 */
function mds_download_log_files()
{
    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    if ($file = $mds->downloadLogFiles()) {
        $file_name = basename($file);
        header('Content-Type: text/plain');
        header("Content-Disposition: attachment; filename=$file_name");
        header('Content-Length: '.filesize($file));

        readfile($file);
        exit;
    } else {
        echo View::make('document_not_found', ['url' => get_admin_url().'admin.php?page=wc-settings&tab=shipping&section=mds_collivery', 'urlText' => 'Back to MDS Settings Page']);
    }
}

/**
 * Download the error log file.
 */
function mds_clear_cache_files()
{
    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $cache = $mds->returnCacheClass();
    $cache->delete();

    wp_redirect(get_admin_url().'admin.php?page=wc-settings&tab=shipping&section=mds_collivery');
    exit;
}

/**
 * Function used to display index of all our deliveries already accepted and sent to MDS Collivery.
 */
function mds_confirmed_orders()
{
    global $wpdb;
    wp_register_style('mds_collivery_css', plugin_dir_url(__FILE__).'/Views/css/mds_collivery.css');
    wp_enqueue_style('mds_collivery_css');

    $post = $_POST;
    $status = (isset($post['status']) && $post['status'] != '') ? $post['status'] : 1;
    $waybill = (isset($post['waybill']) && $post['waybill'] != '') ? $post['waybill'] : false;

    $table_name = $wpdb->prefix.'mds_collivery_processed';
    if (isset($post['waybill']) && $post['waybill'] != '') {
        $colliveries = $wpdb->get_results(
        	$wpdb->prepare(
	            "SELECT * FROM `{$table_name}` WHERE status=%d and waybill=%d ORDER BY id DESC;",
		        $status,
	            $waybill
	        ),
	        OBJECT);
    } else {
        $colliveries = $wpdb->get_results(
	        $wpdb->prepare(
	            "SELECT * FROM `{$table_name}` WHERE status=%d ORDER BY id DESC;",
		        $status
	        ),
        OBJECT);
    }

    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $services = $mds->returnColliveryClass()->getServices();
    echo View::make('index', compact('services', 'colliveries'));
}

/**
 * View our Collivery once it has been accepted.
 */
function mds_confirmed_order()
{
    global $wpdb;
    wp_register_script('mds_collivery_js', plugin_dir_url(__FILE__).'/Views/js/mds_collivery.js');
    wp_enqueue_script('mds_collivery_js');

    $table_name = $wpdb->prefix.'mds_collivery_processed';
    $data_ = $wpdb->get_results(
    	$wpdb->prepare(
		    "SELECT * FROM `{$table_name}` WHERE waybill=%d;",
		    $_GET['waybill']
	    ),
    OBJECT);
    $data = $data_[0];

    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $directory = getcwd().'/cache/mds_collivery/waybills/'.$data->waybill;

    // Do we have images of the parcels
    if ($pod = $collivery->getPod($data->waybill)) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.'/'.$pod['filename'], base64_decode($pod['file']));
    }

    // Do we have proof of delivery
    if ($parcels = $collivery->getParcelImageList($data->waybill)) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach ($parcels as $parcel) {
            $size = $parcel['size'];
            $mime = $parcel['mime'];
            $filename = $parcel['filename'];
            $parcel_id = $parcel['parcel_id'];

            if ($image = $collivery->getParcelImage($parcel_id)) {
                file_put_contents($directory.'/'.$filename, base64_decode($image['file']));
            }
        }
    }

    // Get our tracking information
    $tracking = $collivery->getStatus($data->waybill);
    $validation_results = json_decode($data->validation_results);

    $collection_address = $collivery->getAddress($validation_results->collivery_from);
    $destination_address = $collivery->getAddress($validation_results->collivery_to);
    $collection_contacts = $collivery->getContacts($validation_results->collivery_from);
    $destination_contacts = $collivery->getContacts($validation_results->collivery_to);

    $closedStatusList = [
        '6' => 'Invoiced',
        '8' => 'Delivered',
        '20' => 'POD Received',
        '4' => 'Quote Rejected',
        '28' => 'Credited',
        '5' => 'Cancelled',
    ];

    if (isset($closedStatusList[$tracking['status_id']])) {
        $wpdb->query($wpdb->prepare(
        	"UPDATE `{$table_name}` SET `status` = 0 WHERE `waybill` = %d;",
	        $data->waybill
        ));
    }

    $image_list = glob($directory.'/*.{jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG}', GLOB_BRACE);

    echo View::make('view', compact(
        'data',
        'tracking',
        'image_list',
        'validation_results',
        'collection_address',
        'destination_address',
        'collection_contacts',
        'destination_contacts'
    ));
}

/**
 * View Waybill in PDF format.
 */
function mds_confirmed_order_view_pdf()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $waybill_number = !empty($_GET['waybill']) ? $_GET['waybill'] : 0;

    if (isset($_GET['type'])) {
        if ($_GET['type'] === 'pod') {
            $file = $collivery->getPod($waybill_number);
        } elseif ($_GET['type'] === 'waybill') {
            $file = $collivery->getWaybill($waybill_number);
        } else {
            $collivery->setError('invalid_argument', 'Invalid option');
        }
    }

    if ($mds->getColliveryErrors()) {
        echo View::make('document_not_found', ['url' => get_admin_url().'admin.php?page=mds_confirmed&waybill='.$waybill_number, 'urlText' => 'Back to MDS Confirmed Page']);
    } else {
        header('Content-Type: application/pdf');
        header('Content-Length: '.$file['size']);
        echo base64_decode($file['file']);
        exit;
    }
}

/*
 * Add a button to order page to register shipping with MDS
 */
add_action('woocommerce_order_actions', 'mds_order_actions');

/**
 * Add Order actions process MDS Shipping.
 *
 * @param $actions
 *
 * @return mixed
 */
function mds_order_actions($actions)
{
    $actions['confirm_shipping'] = 'Confirm MDS Shipping';

    return $actions;
}

/*
 * Redirect Admin to plugin page to register the Collivery
 */
add_action('woocommerce_order_action_confirm_shipping', 'mds_process_order_meta', 20, 2);

/**
 * @param $order
 */
function mds_process_order_meta($order)
{
    wp_redirect(admin_url().'edit.php?page=mds_register&post_id='.$order->get_id());
    die();
}

/*
 * Ajax for getting suburbs in admin section
 */
add_action('wp_ajax_suburbs_admin', 'suburbs_admin_callback');

/**
 * Ajax get suburbs for town.
 */
function suburbs_admin_callback()
{
    if ((isset($_POST['town'])) && ($_POST['town'] != '')) {
        $mds = \MdsSupportingClasses\MdsColliveryService::getInstance();
        $collivery = $mds->returnColliveryClass();
        $fields = $collivery->getSuburbs($_POST['town']);
        if (!empty($fields)) {
            wp_die(View::make('_options', [
                'fields' => $fields,
                'placeholder' => 'Select suburb',
            ]));
        } else {
            wp_die(View::make('_options', [
                'placeholder' => 'Error retrieving data from server. Please try again later...',
            ]));
        }
    } else {
        wp_die(View::make('_options', [
            'placeholder' => 'First Select Town...',
        ]));
    }
}

// Ajax for getting suburbs in admin section
add_action('wp_ajax_contacts_admin', 'contacts_admin_callback');

/**
 * Ajax get contacts for address.
 */
function contacts_admin_callback()
{
    if ((isset($_POST['address_id'])) && ($_POST['address_id'] != '')) {
        $mds = MdsColliveryService::getInstance();
        $collivery = $mds->returnColliveryClass();
        $fields = $collivery->getContacts($_POST['address_id']);
        if (!empty($fields)) {
            wp_die(View::make('_options', [
                'fields' => $fields,
                'placeholder' => 'Select contact',
            ]));
        } else {
            wp_die(View::make('_options', [
                'placeholder' => 'Error retrieving data from server. Please try again later...',
            ] ));
        }
    } else {
        wp_die(View::make('_options', [
            'placeholder' => 'First select address...',
        ] ));
    }
}

/*
 * Ajax get a quote
 */
add_action('wp_ajax_quote_admin', 'quote_admin_callback');

/**
 * Ajax quote.
 */
function quote_admin_callback()
{
    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $services = $collivery->getServices();
    $post = $_POST;

    // Now lets get the price for
    $data = [
        'num_package' => count($post['parcels']),
        'service' => $post['service'],
        'parcels' => $post['parcels'],
        'exclude_weekend' => 1,
        'cover' => $post['cover'],
    ];

    // Check which collection address we using
    if ($post['which_collection_address'] == 'default') {
        $data['from_town_id'] = $post['collection_town'];
        $data['from_location_type'] = $post['collection_location_type'];
    } else {
        $data['collivery_from'] = $post['collivery_from'];
        $data['contact_from'] = $post['contact_from'];
    }

    // Check which delivery address we using
    if ($post['which_delivery_address'] == 'default') {
        $data['to_town_id'] = $post['delivery_town'];
        $data['to_location_type'] = $post['delivery_location_type'];
    } else {
        $data['collivery_to'] = $post['collivery_to'];
        $data['contact_to'] = $post['contact_to'];
    }

    try {
        $response = $collivery->getPrice($data);
        if (!isset($response['service'])) {
            throw new InvalidColliveryDataException('Unable to get response from MDS API', 'quote_admin_callback', $mds->loggerSettingsArray(), ['data' => $data, 'errors' => $mds->getColliveryErrors()]);
        }

        wp_die('<p class="mds_response"><b>Service: </b>'.$services[$response['service']].' - Price incl: R'.$response['price']['inc_vat'].'</p>');
    } catch (InvalidColliveryDataException $e) {
        wp_die('<p class="mds_response"><b>Error: </b>'.$e->getMessage().'</p>');
    }
}

/*
 * Ajax accept quote
 */
add_action('wp_ajax_accept_admin', 'accept_admin_callback');

/**
 * Ajax accept quote and add delivery request to MDS.
 */
function accept_admin_callback()
{
    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $post = $_POST;

    try {
        $order = new WC_Order($post['order_id']);
        if (!$mds->hasOrderBeenProcessed($order->get_id())) {
            // Check which collection address we using and if we need to add the address to collivery api
            if ($post['which_collection_address'] == 'default') {
                $collection_address = $mds->addColliveryAddress([
                    'company_name' => ($post['collection_company_name'] != '') ? $post['collection_company_name'] : 'Private',
                    'building' => $post['collection_building_details'],
                    'street' => $post['collection_street'],
                    'location_type' => $post['collection_location_type'],
                    'suburb' => $post['collection_suburb'],
                    'town' => $post['collection_town'],
                    'full_name' => $post['collection_full_name'],
                    'phone' => preg_replace('/[^0-9]/', '', $post['collection_phone']),
                    'cellphone' => preg_replace('/[^0-9]/', '', $post['collection_cellphone']),
                    'email' => $post['collection_email'],
                ]);

                // Check for any problems
                if ($collivery->hasErrors()) {
                    wp_send_json([
                        'redirect' => false,
                        'message' => '<p class="mds_response">'.implode(', ', $collivery->getErrors()).'</p>',
                    ]);
                } else {
                    // set the collection address and contact from the returned array
                    $collivery_from = $collection_address['address_id'];
                    $contact_from = $collection_address['contact_id'];
                }
            } else {
                $collivery_from = $post['collivery_from'];
                $contact_from = $post['contact_from'];
            }

            // Check which delivery address we using and if we need to add the address to collivery api
            if ($post['which_delivery_address'] == 'default') {
                $delivery_address = $mds->addColliveryAddress([
                    'company_name' => ($post['delivery_company_name'] != '') ? $post['delivery_company_name'] : 'Private',
                    'building' => $post['delivery_building_details'],
                    'street' => $post['delivery_street'],
                    'location_type' => $post['delivery_location_type'],
                    'suburb' => $post['delivery_suburb'],
                    'town' => $post['delivery_town'],
                    'full_name' => $post['delivery_full_name'],
                    'phone' => preg_replace('/[^0-9]/', '', $post['delivery_phone']),
                    'cellphone' => preg_replace('/[^0-9]/', '', $post['delivery_cellphone']),
                    'email' => $post['delivery_email'],
                    'custom_id' => $order->get_user_id(),
                ]);

                // Check for any problems
                if ($collivery->hasErrors()) {
                    wp_send_json([
                        'redirect' => false,
                        'message' => '<p class="mds_response">'.implode(', ', $collivery->getErrors()).'</p>',
                    ]);
                } else {
                    $collivery_to = $delivery_address['address_id'];
                    $contact_to = $delivery_address['contact_id'];
                }
            } else {
                $collivery_to = $post['collivery_to'];
                $contact_to = $post['contact_to'];
            }

            $collivery_id = $mds->addCollivery([
                'collivery_from' => $collivery_from,
                'contact_from' => $contact_from,
                'collivery_to' => $collivery_to,
                'contact_to' => $contact_to,
                'cust_ref' => 'Order number: '.$order->get_id(),
                'instructions' => $post['instructions']." ".$order->get_customer_note(),
                'collivery_type' => 2, // Package
                'service' => $post['service'],
                'cover' => $post['cover'],
                'collection_time' => strtotime($post['collection_time']),
                'parcel_count' => count($post['parcels']),
                'parcels' => $post['parcels'],
            ]);

            // Check for any problems validating
            if (!$collivery_id) {
                $mds->updateStatusOrAddNote($order, 'There was a problem sending this the delivery request to MDS Collivery, you will need to manually process.', true, 'processing');

                wp_send_json([
                    'redirect' => false,
                    'message' => '<p class="mds_response">'.implode(', ', $collivery->getErrors()).'</p>',
                ]);
            } else {
                $validatedData = $mds->returnColliveryValidatedData();
                $collection_time = (isset($validatedData['collection_time'])) ? ' anytime from: '.date('Y-m-d H:i', $validatedData['collection_time']) : '';

                // Save the results from validation into our table
                $mds->addColliveryToProcessedTable($collivery_id, $order->get_id());
                $message = 'Order has been sent to MDS Collivery, Waybill Number: '.$collivery_id.', please have order ready for collection'.$collection_time.'.';
                $mds->updateStatusOrAddNote($order, $message, false, 'completed');

                wp_send_json([
                    'redirect' => true,
                    'message' => '<p class="mds_response">'.$message.' You will be redirect to your order in 5 seconds.</p>',
                ]);
            }
        } else {
            wp_send_json([
                'redirect' => false,
                'message' => '<p class="mds_response">Sorry, this order has already been processed.</p>',
            ]);
        }
    } catch (Exception $e) {
        wp_send_json([
            'redirect' => false,
            'message' => '<p class="mds_response">'.$e->getMessage().'</p>',
        ]);
    }
}

/*
 * WordPress Backend to Register Collivery
 */
add_action('admin_menu', 'mds_add_options');

/**
 * Add javascript files.
 */
function mds_add_options()
{
    $submenu = add_submenu_page(null, 'Register Collivery', null, 'manage_options', 'mds_register', 'mds_register_collivery');

    // load JS conditionally
    add_action('load-'.$submenu, 'mds_load_admin_js');
}

add_action('admin_enqueue_scripts', 'mds_enqueue_admin_js');
/**
 * this action is only called on the register page load.
 */
function mds_load_admin_js()
{
    // Unfortunately we can't just enqueue our scripts here - it's too early. So
    // register against the proper action hook to do it
    add_action('admin_enqueue_scripts', 'mds_enqueue_admin_js');
}

/**
 * Enqueue all admin scripts.
 */
function mds_enqueue_admin_js()
{
    wp_register_script('jquery.datetimepicker.min_js', plugin_dir_url(__FILE__).'/Views/js/jquery.datetimepicker.min.js');
    wp_enqueue_script('jquery.datetimepicker.min_js');
    wp_register_script('mds_collivery_js', plugin_dir_url(__FILE__).'/Views/js/mds_collivery.js');
    wp_enqueue_script('mds_collivery_js');
    wp_register_script('jquery.validate.min_js', plugin_dir_url(__FILE__).'/Views/js/jquery.validate.min.js');
    wp_enqueue_script('jquery.validate.min_js');

    wp_register_style('mds_collivery_css', plugin_dir_url(__FILE__).'/Views/css/mds_collivery.css');
    wp_enqueue_style('mds_collivery_css');
    wp_register_style('jquery.datetimepicker_css', plugin_dir_url(__FILE__).'/Views/css/jquery.datetimepicker.css');
    wp_enqueue_style('jquery.datetimepicker_css');
}

/**
 * Order actions process MDS Shipping.
 */
function mds_register_collivery()
{
    $order = new WC_Order($_GET['post_id']);
    $order_id = $_GET['post_id'];

    /** @var \MdsSupportingClasses\MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $settings = $mds->returnPluginSettings();
    $parcels = $mds->getOrderContent($order->get_items());
    $defaults = $mds->returnDefaultAddress();
    $addresses = $collivery->getAddresses();
    $total = $order->get_subtotal() + $order->get_cart_tax();
    $riskCover = $settings->getValue('risk_cover') === 'yes';

    $instructions = '';
    if ($settings->getValue('include_customer_note') == 'yes') {
        $instructions .= $order->get_customer_note();
    }
    if ($settings->getValue('include_order_number') == 'yes') {
        if(strlen($instructions)>0){
            $instructions .= ' ';
        }
        $instructions .= 'Order number: ' . $order_id;
    }
    if ($settings->getValue('include_product_titles') == 'yes') {
        $count = 1;
        if(strlen($instructions)>0){
            $instructions .= ' ';
        }
        $instructions .= ': ';
        foreach ($parcels as $parcel) {
            if (isset($parcel['description'])) {
                $ending = ($count == count($parcels)) ? '' : ', ';
                $instructions .= $parcel['quantity'] . ' X ' . $parcel['description'] . $ending;
                ++$count;
            }
        }
    }

    $include_product_titles = true;
    $towns = $collivery->getTowns();
    $services = $collivery->getServices();
    $location_types = $collivery->getLocationTypes();
    $suburbs = [0 => 'Select Town'];
    $populatedSuburbs = $suburbs + $collivery->getSuburbs(array_search($order->get_shipping_city(), $collivery->getTowns()));

    $shipping_method = null;
    foreach ($services as $id => $value) {
        if ($order->has_shipping_method('mds_'.$id)) {
            $shipping_method = $id;
        }
    }

    echo View::make('order', compact('order', 'total', 'shipping_method', 'collivery', 'parcels', 'defaults', 'addresses', 'instructions', 'include_product_titles', 'towns', 'location_types', 'suburbs', 'populatedSuburbs', 'services', 'riskCover'));
}
