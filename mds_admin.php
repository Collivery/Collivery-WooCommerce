<?php

use MdsExceptions\InvalidColliveryDataException;
use MdsSupportingClasses\MdsColliveryService;
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
    /** @var MdsColliveryService $mds */
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
    /** @var MdsColliveryService $mds */
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

    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $services = $mds->returnColliveryClass()->make_key_value_array($mds->returnColliveryClass()->getServices(), 'id', 'text');
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

    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $settings = $mds->returnPluginSettings();
    $directory = getcwd().'/cache/mds_collivery/waybills/'.$data->waybill;

    $order = wc_get_order($data->order_id);

    // Getting Total Weight;

    $total_weight = 0;
    foreach ($order->get_items() as $item_id => $product_item) {
        $qty = $product_item->get_quantity();
        $product = $product_item->get_product();
        $product_weight = $product->get_weight();

        $total_weight += floatval($product_weight) * floatval($qty);
    }

    // Shipping Cost
    $shipping_total = $order->get_shipping_total();
    if ($settings->getValue('include_vat') === 'yes') {
        $shipping_total *= 1.15;
    }

    $quoted_data = ["Shipping_Total" => $shipping_total, "Total_Weight" => $total_weight];

    // Do we have images of the parcels
    if ($pod = $collivery->getPod($data->waybill)) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.'/'.$pod['file_name'], base64_decode($pod['image']));
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
    $tracking = $tracking[count($tracking)-1]; // The Last status update.

    $validation_results = json_decode($data->validation_results);
    if (isset($validation_results->data)) {
        $validation_results = $validation_results->data;

        $collection_address = $collivery->getAddress($validation_results->collection_address_id);
        $destination_address = $collivery->getAddress($validation_results->delivery_address_id);
        $collection_contacts = $collivery->getContacts($validation_results->collection_contact_id);
        $destination_contacts = $collivery->getContacts($validation_results->delivery_contact_id);
    } else {
        $collection_address = $collivery->getAddress($validation_results->collivery_from);
        $destination_address = $collivery->getAddress($validation_results->collivery_to);
        $collection_contacts = $collivery->getContacts($validation_results->contact_from);
        $destination_contacts = $collivery->getContacts($validation_results->contact_to);
    }

    if (!isset($validation_results->risk_cover) && isset($validation_results->cover)) {
        $validation_results->risk_cover = $validation_results->cover;
    } else if (!isset($validation_results->risk_cover) && !isset($validation_results->cover)) {
        $validation_results->risk_cover = false;
        $waybill = $collivery->getCollivery($data->waybill);
        if (isset($waybill['risk_cover'])) {
            $validation_results->risk_cover = $waybill['risk_cover'];
        }
    }

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

    $image_list = glob($directory.'/*.{jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG}', GLOB_BRACE); // Empty

    echo View::make('view', compact(
        'data',
        'tracking',
        'image_list',
        'validation_results',
        'collection_address',
        'destination_address',
        'collection_contacts',
        'destination_contacts',
        'quoted_data'
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

    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $waybill_number = !empty($_GET['waybill']) ? $_GET['waybill'] : 0;
    $file = false;

    if (isset($_GET['type'])) {
        if ($_GET['type'] === 'pod') {
            $file = $collivery->getPod($waybill_number);
        } elseif ($_GET['type'] === 'waybill') {
            $file = $collivery->getWaybill($waybill_number);
        } else {
            $collivery->setError('invalid_argument', 'Invalid option');
        }
    }

    if ($mds->getColliveryErrors() || $file == false) {
        echo View::make('document_not_found', ['url' => get_admin_url().'admin.php?page=mds_confirmed&waybill='.$waybill_number, 'urlText' => 'Back to MDS Confirmed Page']);
    } else {
        header('Content-Type: '.$file['mime']);
        header('Content-Length: '.$file['size']);
        echo base64_decode($file['image']);
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
    $actions['confirm_shipping_international'] = 'Confirm MDS International Shipping';

    return $actions;
}


/*
 * Redirect Admin to plugin page to register the Collivery
 */
add_action('woocommerce_order_action_confirm_shipping_international', 'mds_process_intl_order_meta', 20, 2);

/**
 * @param $order
 */
function mds_process_intl_order_meta($order)
{
    wp_redirect(admin_url().'edit.php?page=mds_register&post_id='.$order->get_id().'&is_intl=true');
    die();
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
        $mds = MdsColliveryService::getInstance();
        $collivery = $mds->returnColliveryClass();
        $fields = $collivery->getSuburbs($_POST['town']);
        if (!empty($fields)) {
            wp_die(View::make('_options', [
                'fields' => $collivery->make_key_value_array($fields, 'id', 'name'),
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
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    if ((isset($_POST['address_id'])) && ($_POST['address_id'] != '')) {
        
        $fields = $collivery->getContacts($_POST['address_id']);
        if (!empty($fields)) {
            wp_die(View::make('_options', [
                'fields' => $collivery->make_key_value_array($fields, 'id', 'full_name', true),
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
    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $services = $collivery->make_key_value_array($collivery->getServices(), 'id', 'text');
    $post = $_POST;

    // Now lets get the price for
    $data = [
        'services' => [$post['service']],
        'parcels' => $post['parcels'],
        'exclude_weekend' => true,
        'risk_cover' => $post['cover'],
    ];

    // Check which collection address we using
    if ($post['which_collection_address'] == 'default') {
        $data['collection_town'] = $post['collection_town'];
        $data['collection_location_type'] = $post['collection_location_type'];
    } else {
        $data['collection_address'] = $post['collivery_from'];
    }

    // Check which delivery address we using
    if ($post['which_delivery_address'] == 'default') {
        $data['delivery_town'] = $post['delivery_town'];
        $data['delivery_location_type'] = $post['delivery_location_type'];
    } else {
        $data['delivery_address'] = $post['collivery_to'];
    }

    try {
        
        $response = $collivery->getPrice($data);
        if (!isset($response['data'][0]['service_type'])) {
            throw new InvalidColliveryDataException('Unable to get response from MDS API', 'quote_admin_callback', $mds->loggerSettingsArray(), ['data' => $data, 'errors' => $mds->getColliveryErrors(), 'results' => $response]);
        }

        wp_die('<p class="mds_response"><b>Service: </b>'.$services[$response['data'][0]['service_type']].' - Price incl: R'.($response['data'][0]['total']*1.15).'</p>');
    } catch (InvalidColliveryDataException $e) {
        wp_die('<p class="mds_response"><b>Error: </b>'.$e->getMessage().'</p>');
    }
}
/*
 * Ajax update order
 */
add_action('wp_ajax_update_international_order_admin', 'update_order_international_order_admin_callback');


/**
 * Ajax update waybill number for international orders.
 */
function update_order_international_order_admin_callback()
{
    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $overrides = $_POST;
    $order = new WC_Order($overrides['order_id']);

    try {
        if (!isset($overrides['waybill_number'])) {
            wp_send_json([
                'redirect' => false, 
                'message' => '<p class="mds_response">' . 'Waybill Number not set.' . '</p>'
            ]);
        }

        $mds->linkWaybillNumber($order, $overrides['waybill_number']);

        $message = "Order: " . $overrides['order_id'] . " has been linked with Waybill Number: " . $overrides['waybill_number'] . ". You will be redirected to your order in 5 seconds";

        wp_send_json([
            'redirect' => true, 
            'message' => '<p class="mds_response">' . $message . '</p>'
        ]);
    } catch (Exception $e) {
        $mds->updateStatusOrAddNote($order, $e->getMessage(), true, 'processing');
        wp_send_json([
            'redirect' => false,
            'message'  => '<p class="mds_response">' . $e->getMessage() . '</p>',
        ]);
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
    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $overrides = $_POST;
    $order = new WC_Order($overrides['order_id']);

    try {
        $collivery = $mds->orderToCollivery($order, $overrides);
        $collection_time = (isset($collivery['data']['collection_time']))
            ? ' anytime from: ' . date('Y-m-d H:i', $collivery['data']['collection_time'])
            : '';
        $colliveryId = $collivery['data']['id'];
        $message = "Order has been sent to MDS Collivery, Waybill Number: {$colliveryId}, please have order ready for collection{$collection_time}.";

        wp_send_json([
            'redirect' => true,
            'message'  => '<p class="mds_response">' . $message . ' You will be redirect to your order in 5 seconds.</p>',
        ]);
    } catch (Exception $e) {
        $mds->updateStatusOrAddNote($order, $e->getMessage(), true, 'processing');
        wp_send_json([
            'redirect' => false,
            'message'  => '<p class="mds_response">' . $e->getMessage() . '</p>',
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
    wp_register_script('mds_collivery_js', plugin_dir_url(__FILE__).'/Views/js/mds_collivery.js', ['jquery'], MDS_VERSION);
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

    /** @var MdsColliveryService $mds */
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();
    $settings = $mds->returnPluginSettings();
    $parcels = $mds->getOrderContent($order->get_items());
    $defaults = $mds->returnDefaultAddress();
    $defaults['contacts'] = $collivery->make_key_value_array($defaults['contacts'], 'id', 'full_name', true);
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

    $towns = $collivery->make_key_value_array($collivery->getTowns(), 'id', 'name');
    $services = $collivery->make_key_value_array($collivery->getServices(), 'id', 'text');
    $location_types = $collivery->make_key_value_array($collivery->getLocationTypes(), 'id', 'name');

    $suburbs = [0 => 'Select Town'];
    $populatedSuburbs = $suburbs + $collivery->make_key_value_array($collivery->getSuburbs(array_search($order->get_shipping_city(), $towns)), 'id', 'name');

    $shipping_method = null;
    foreach ($services as $id => $value) {
        if ($order->has_shipping_method('mds_'.$id)) {
            $shipping_method = $id;
        }
    }

    echo View::make('order', compact('order', 'total', 'shipping_method', 'collivery', 'parcels', 'defaults', 'addresses', 'instructions', 'include_product_titles', 'towns', 'location_types', 'suburbs', 'populatedSuburbs', 'services', 'riskCover'));
}
