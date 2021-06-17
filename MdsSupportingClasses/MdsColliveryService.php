<?php

namespace MdsSupportingClasses;

use WC_Order;
use WC_Product;
use WC_Product_Variation;
use WC_Order_Item_Product;
use MdsExceptions\ProductOutOfException;
use MdsExceptions\InternationalAutomatedException;
use MdsExceptions\InvalidServiceException;
use MdsExceptions\CurlConnectionException;
use MdsExceptions\InvalidCartPackageException;
use MdsExceptions\InvalidAddressDataException;
use MdsExceptions\InvalidColliveryDataException;
use MdsExceptions\OrderAlreadyProcessedException;

class MdsColliveryService
{
    const TWENTY_FOUR_HOURS = 24;
    /**
     * self.
     */
    private static $instance;

	/**
     * @var UnitConverter
     */
    private $converter;

    /**
     * @var Collivery
     */
    private $collivery;

    /**
     * @var MdsCache
     */
    private $cache;

    /**
     * @var MdsLogger
     */
    private $logger;

    /**
     * @var array
     */
    private $validated_data;

    /**
     * @var MdsSettings
     */
    private $settings;

	/**
	 * @var EnvironmentInformationBag
	 */
	private $environment;


	/**
     * @param array|null $settings
     *
     * @return MdsColliveryService
     */
    public static function getInstance($settings = null)
    {
        if (!self::$instance) {
            self::$instance = new self($settings);
        }

        return self::$instance;
    }

    /**
     * @param $settings
     *
     * @return MdsColliveryService
     */
    public function newInstance($settings)
    {
        self::$instance = new self($settings);

        return self::$instance;
    }

    /**
     * MdsColliveryService constructor.
     *
     * @param null $settings
     */
    private function __construct($settings = null)
    {
        $this->converter   = new UnitConverter();
        $this->cache       = new MdsCache(ABSPATH . 'cache/mds_collivery/');
        $this->logger      = new MdsLogger();
        $this->environment = new EnvironmentInformationBag();

        $this->initSettings($settings);
        $this->initMdsCollivery();
    }

    /**
     *  Sets up the settings array by fetching all of the options out of the database.
     *
     * @param null $settings
     * @param array $instanceSettings
     *
     * @return MdsSettings
     */
    public function initSettings($settings = null, $instanceSettings = [])
    {
        if (is_array($settings)) {
            $this->settings = new MdsSettings($settings, $instanceSettings);
        } else {
            $settings = get_option('woocommerce_mds_collivery_settings', null);

            // If there are no settings defined, use defaults.
            if (!is_array($settings)) {
                $form_fields = MdsFields::getFields();
                $settings = array_merge(
                    array_fill_keys(array_keys($form_fields), ''),
                    wp_list_pluck($form_fields, 'default')
                );
            }

            $this->settings = new MdsSettings($settings, $instanceSettings);
        }

        $this->environment->setSettings( $this->settings->settings + $instanceSettings);

        return $this->settings;
    }

    /**
     * Instantiates the MDS Collivery class.
     */
    public function initMdsCollivery()
    {
        $colliveryInitData = [
            'app_name' => $this->environment->appName,
            'app_version' => $this->environment->appVersion,
            'app_host' => $this->environment->appHost,
            'app_url' => $this->environment->appUrl,
            'user_email' => wp_specialchars_decode($this->settings->getValue('mds_user')),
            'user_password' => wp_specialchars_decode($this->settings->getValue('mds_pass')),
        ];

        $this->collivery = new Collivery($colliveryInitData, $this->cache);
    }

    /**
     * Work through our shopping cart
     * Convert lengths and weights to desired unit.
     *
     * @param $package
     *
     * @return null|array
     */
    public function getCartContent($package)
    {
        if (isset($package['contents']) && sizeof($package['contents']) > 0) {
            $cart = [
                'count' => 0,
                'total' => 0,
                'weight' => 0,
                'max_weight' => 0,
                'products' => [],
            ];

            foreach ($package['contents'] as $item_id => $values) {
                $_product = $values['data']; // = WC_Product class
                $qty = $values['quantity'];
                $parcel['quantity'] = $qty;

                $cart['count'] += $qty;
                $cart['total'] += $values['line_subtotal'];
                $cart['weight'] += (float)$_product->get_weight() * $qty;

                // Work out Volumetric Weight based on MDS calculations
                $vol_weight =  (((int)($_product->get_length()) * ((int)$_product->get_width()) * ((int)$_product->get_height())) / 4000);

                if ($vol_weight > (float)$_product->get_weight()) {
                    $cart['max_weight'] += $vol_weight * $qty;
                } else {
                    $cart['max_weight'] += (float)$_product->get_weight() * $qty;
                }

                // Length conversion, mds collivery only accepts cm
                if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
                    $parcel['length'] = $this->converter->convert((int)$_product->get_length(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
                    $parcel['width'] = $this->converter->convert((int)$_product->get_width(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
                    $parcel['height'] = $this->converter->convert((int)$_product->get_height(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
                } else {
                    $parcel['length'] = (int)$_product->get_length();
                    $parcel['width'] = (int)$_product->get_width();
                    $parcel['height'] = (int)$_product->get_height();
                }

                // Weight conversion, mds collivery only accepts kg
                if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
                    $parcel['weight'] = $this->converter->convert((float)$_product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
                } else {
                    $parcel['weight'] = (float)$_product->get_weight();
                }

                $parcel['description'] = $_product->get_title();

                $cart['products'][] = $parcel;
            }

            return $cart;
        }

        return null;
    }

    /**
     * Validate the package before using the package to get prices.
     *
     * @param $package
     *
     * @return bool
     */
    public function validPackage($package)
    {
        // $package must be an array and not empty
        if (!is_array($package) || empty($package)) {
            return false;
        }

        try {
            $this->validatePackageField($package, 'destination', 'array');
            $this->validatePackageField($package['destination'], 'to_town_id');
            $this->validatePackageField($package['destination'], 'from_town_id');
            $this->validatePackageField($package['destination'], 'to_location_type');
            $this->validatePackageField($package['destination'], 'from_location_type');

            $this->validatePackageField($package, 'cart', 'array');
            $this->validatePackageField($package['cart'], 'max_weight', 'numeric');
            $this->validatePackageField($package['cart'], 'count', 'numeric');
            $this->validatePackageField($package['cart'], 'products', 'array');

            return true;
        } catch (InvalidCartPackageException $e) {
            return false;
        }
    }

    /**
     * @param $array
     * @param $field
     * @param string $type
     *
     * @throws InvalidCartPackageException
     */
    private function validatePackageField($array, $field, $type = 'int')
    {
        if (!is_array($array)) {
            throw new InvalidCartPackageException('Unable to validate field"' . $field . '" as its parent is not an array, possible due to when the cart page loads', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), []);
        }

        if (!isset($array[$field])) {
            throw new InvalidCartPackageException($field . ' does not exist in array', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
        }

        if ($type == 'int') {
            if (!is_integer($array[$field])) {
                throw new InvalidCartPackageException($field . ' is not an integer', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
            }
        }

        if ($type == 'numeric') {
            if (!is_numeric($array[$field])) {
                throw new InvalidCartPackageException($field . ' is not numeric', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
            }
        }

        if ($type == 'array') {
            if (!is_array($array[$field])) {
                throw new InvalidCartPackageException($field . ' is not an array', 'MdsColliveryService::validPackage()', $this->loggerSettingsArray(), $array);
            }
        }
    }

    /**
     * Used to build the package for use out of the shipping class.
     *
     * @param $cart
     *
     * @return array
     */
    public function buildPackageFromCart($cart)
    {
        $package = [];

        if (!empty($cart)) {
            foreach ($cart as $item) {
                $product = $item['data'];

                $package['contents'][$product->get_id()] = [
                    'data' => $item['data'],
                    'quantity' => $item['quantity'],
                    'price' => $product->get_price(),
                    'line_subtotal' => $product->get_price() * $item['quantity'],
                    'weight' => (float)$product->get_weight() * $item['quantity'],
                ];
            }
        }

        return $package;
    }

    /**
     * Work through our order items and return an array of parcels.
     *
     * @param $items
     *
     * @return array
     */
    public function getOrderContent($items)
    {
        $parcels = [];
        /**  @var WC_Order_Item_Product $item */
        foreach ($items as $item_id => $item) {
            /** @var WC_Product|WC_Product_Variation $product */
            if ($item->get_variation_id()) {
                $product = new WC_Product_Variation($item->get_variation_id());
            } else {
                $product = new WC_Product($item->get_product_id());
            }

            $parcel['quantity'] = $item->get_quantity();

            // Length conversion, mds collivery only accepts cm
            if (strtolower(get_option('woocommerce_dimension_unit')) != 'cm') {
                $parcel['length'] = $this->converter->convert((int)$product->get_length(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
                $parcel['width'] = $this->converter->convert((int)$product->get_width(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
                $parcel['height'] = $this->converter->convert((int)$product->get_height(), strtolower(get_option('woocommerce_dimension_unit')), 'cm', 6);
            } else {
                $parcel['length'] = (int)$product->get_length();
                $parcel['width'] = (int)$product->get_width();
                $parcel['height'] = (int)$product->get_height();
            }

            // Weight conversion, mds collivery only accepts kg
            if (strtolower(get_option('woocommerce_weight_unit')) != 'kg') {
                $parcel['weight'] = $this->converter->convert($product->get_weight(), strtolower(get_option('woocommerce_weight_unit')), 'kg', 6);
            } else {
                $parcel['weight'] = (float)$product->get_weight();
            }

            $parcel['description'] = $product->get_title();

            $parcels[] = $parcel;
        }

        return $parcels;
    }

    /**
     * Goes through and order and checks if all products are in stock.
     *
     * @param $items
     *
     * @return bool
     * @throws ProductOutOfException
     */
    public function isOrderInStock($items) {
        $stock = 0;
        /**  @var WC_Order_Item_Product $item */
        foreach ($items as $item_id => $item) {
            $qty     = $item->get_quantity();
            $product = new WC_Product($item->get_product_id());

            /** @var WC_Product|WC_Product_Variation $product */
            if ($item->get_variation_id()) {
                $productVariation = new WC_Product_Variation($item->get_variation_id());
                if ($productVariation->get_manage_stock()) {
                    $stock = $product->get_stock_quantity();
                }
            }

            if ($stock) {
                if ($stock < $qty) {
                    throw new ProductOutOfException($product->get_formatted_name() . ' is out of stock', 'isOrderInStock', $this->loggerSettingsArray(), $items);
                }
            }
        }

        return true;
    }

    /**
     * @param WC_Order $order
     * @param $message
     * @param $processing
     * @param null $status
     */
    public function updateStatusOrAddNote(WC_Order $order, $message, $processing, $status = null)
    {
        if (!$processing) {
            $order->update_status($status, $message);
        } else {
            $order->add_order_note($message, 0);
        }
    }

    /**
     * @param array $array
     * @param float $adjustedTotal
     * @param float $markup
     * @param float $fixedPrice
     *
     * @return float
     *
     * @throws InvalidColliveryDataException
     * @throws CurlConnectionException
     */
    public function getPrice(array $array, $adjustedTotal, $markup, $fixedPrice)
    {
        if ($fixedPrice) {
            return $fixedPrice;
        }
        if (!$result = $this->collivery->getPrice($array)) {
            if ($result == null) {
                $this->initMdsCollivery();
                if (!$result = $this->collivery->getPrice($array)) {
                    throw new InvalidColliveryDataException('There was a problem sending this the delivery request to MDS Collivery, you will need to manually process. Error: Unable to get price from MDS', 'MdsColliveryService::getPrice', $this->loggerSettingsArray(), ['errors' => $this->collivery->getErrors(), 'data' => $array ]);
                }
            } else {
                throw new InvalidColliveryDataException('There was a problem sending this the delivery request to MDS Collivery, you will need to manually process. Error: Unable to get price from MDS', 'MdsColliveryService::getPrice', $this->loggerSettingsArray(), ['errors' => $this->collivery->getErrors(), 'data' => $array ]);
            }
        }

        $discountEnabled = $this->settings->getValue( 'method_free' ) === 'discount';
        $overThreshold   = $adjustedTotal >= $this->settings->getValue( 'free_min_total' );

        if ( $discountEnabled && $overThreshold ) {
            $discount = $this->settings->getValue('shipping_discount_percentage');
        } else {
            $discount = 0;
        }

        $returnedAmount = $result['data'][0]['total'];

        if ($this->settings->getValue('include_vat') === 'yes') {
            $returnedAmount *= 1.15;
        }
        
        return Money::make($returnedAmount, $markup, $fixedPrice, $discount, $this->settings->getValue('round') == 'yes')->amount;
    }

	/**
	 * Adds the delivery request to MDS Collivery.
	 *
	 * @param array $array
	 *
	 * @return bool
	 * @throws InvalidColliveryDataException
	 */
    public function addCollivery(array $array)
    {
        $this->validated_data = $validatedData = $this->validateCollivery($array);

	    if (empty($validatedData) || is_bool($validatedData)) {
		    return false;
	    }

        if (isset($this->validated_data['time_changed']) && $this->validated_data['time_changed'] == 1) {
            $id = $this->validated_data['service'];
            $services = $this->collivery->getServices();

            if ($this->settings->getValue("wording_$id")) {
                $reason = preg_replace('|' . preg_quote($services[$id]) . '|', $this->settings->getValue("wording_$id"), $this->validated_data['time_changed_reason']);
            } else {
                $reason = $this->validated_data['time_changed_reason'];
            }

            $reason = preg_replace('|collivery|i', 'delivery', $reason);
            $reason = preg_replace('|The delivery time has been CHANGED to|i', 'the approximate delivery day is', $reason);

            if (function_exists('wc_add_notice')) {
                wc_add_notice(sprintf(__($reason, 'woocommerce-mds-shipping')));
            }
        }

        $collivery_object = $this->collivery->addCollivery($this->validated_data);

        if ($this->settings->getValue('auto_accept', 'yes') === 'yes') {
            return ($this->collivery->acceptCollivery($collivery_object['data']['id'])) ? $collivery_object : false;
        }

        return $collivery_object;
    }

    /**
     * Validate delivery request before adding the request to MDS Collivery.
     *
     * @param array $array
     *
     * @throws InvalidColliveryDataException
     *
     * @return bool|array
     */
    public function validateCollivery(array $array)
    {
        if (empty($array['collivery_from'])) {
            throw new InvalidColliveryDataException('Invalid collection address', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['collivery_to'])) {
            throw new InvalidColliveryDataException('Invalid destination address', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['contact_from'])) {
            throw new InvalidColliveryDataException('Invalid collection contact', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['contact_to'])) {
            throw new InvalidColliveryDataException('Invalid destination contact', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['collivery_type'])) {
            throw new InvalidColliveryDataException('Invalid parcel type', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['service'])) {
            throw new InvalidColliveryDataException('Invalid service', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if ($array['cover'] != 1 && $array['cover'] != 0) {
            throw new InvalidColliveryDataException('Invalid risk cover option', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['parcels']) || !is_array($array['parcels'])) {
            throw new InvalidColliveryDataException('Invalid parcels', 'MdsColliveryService::validateCollivery()', $this->loggerSettingsArray(), $array);
        }

        if ($array['service'] == Collivery::ONX_10) {
            $collectionTime = array_key_exists('collection_time', $array) ?
                new \DateTime($array['collection_time']) :
                new \DateTime();
            $deliveryTime = clone $collectionTime;
            $deliveryTime->modify('+1 day');
            $deliveryTime->setTime(10, 0);

            while (in_array($deliveryTime->format('D'), ['Sat', 'Sun'])) {
                $deliveryTime->modify('+1 day');
            }

            $array['delivery_time'] = $deliveryTime->format('Y-m-d H:i:s');
            $array['service'] = Collivery::ONX;
        }

        return $array;
    }

    public function linkWaybillNumber(WC_Order $order, int $waybill_number) {
        if ($this->hasOrderBeenProcessed($order->get_id())) {
            throw new OrderAlreadyProcessedException('Could not link MDS Collivery waybill number, order has already been linked.', $this->loggerSettingsArray(), [
                'order_id' => $order->get_id(),
                'data' => $overrides,
            ]);
        }

        // In order to keep it in the format as it's saved in the local table.
        $collivery['data'] = $this->collivery->getCollivery($waybill_number);

        $collectionTime = (isset($collivery['data']['collection_time'])) ? ' anytime from: ' . date('Y-m-d H:i', $collivery['data']['collection_time']) : '';
        if ($collivery['data']['id']) {
            // Save the results from validation into our table
            $this->addColliveryToProcessedTable($collivery, $order->get_id());
            $this->updateStatusOrAddNote($order, 'Order has been linked to MDS Collivery, Waybill Number: ' . $collivery['data']['id'] . ', please have order ready for collection' . $collectionTime . '.', false, 'completed');

            return $collivery;
        } else {
            $errors = $this->collivery->getErrors();
            throw new InvalidColliveryDataException('Error linking to Collivery: ' . implode(', ', $errors), 'automatedOrderToCollivery', $this->loggerSettingsArray(), ['order' => $order, 'errors' => $errors, 'result' => $collivery]);
        }
    }

    /**
     * @param WC_Order $order
     * @param int    $overrides
     *
     * @return int
     * @throws OrderAlreadyProcessedException
     * @throws ProductOutOfException
     * @throws CurlConnectionException
     * @throws InternationalAutomatedException
     */
    public function orderToCollivery(WC_Order $order, array $overrides) {

        if ($order->get_shipping_country() != "ZA" && $order->get_shipping_country() != "South Africa") {
            throw new InternationalAutomatedException('International shipping request detected! Please manually link the waybill using the "Link International MDS Waybill" found on the Order.', $this->loggerSettingsArray(), [
                'order_id' => $order->get_id(),
                'data' => $overrides,
            ]);
        }

        if ($this->hasOrderBeenProcessed($order->get_id())) {
            throw new OrderAlreadyProcessedException('Could not add MDS Collivery waybill, order already sent to MDS.', $this->loggerSettingsArray(), [
                'order_id' => $order->get_id(),
                'data' => $overrides,
            ]);
        }

        if (isset($overrides['service'])) {
            $serviceId = $overrides['service'];
        } else {
            foreach ($order->get_shipping_methods() as $shipping) {
                if (preg_match('/mds_/', $shipping['method_id'])) {
                    $serviceId = str_replace('mds_', '', $shipping['method_id']);
                }
            }
        }

        if (!isset($serviceId)) {
            throw new InvalidServiceException('No MDS shipping method used', 'automatedOrderToCollivery', $this->loggerSettingsArray(), $order->get_shipping_methods());
        }

        $processing = $overrides['processing'] ?? false;
        $this->isOrderInStock($order->get_items());
        $parcels = $overrides['parcels'] ?? $this->getOrderContent($order->get_items());
        $defaults = $this->returnDefaultAddress();

        if (!isset($overrides['which_collection_address']) && !isset($overrides['collivery_from'])) {
            $collivery_from = $defaults['default_address_id'];
            $contact_from = $defaults['contacts'][0]['id'];
        } elseif (isset($overrides['which_collection_address']) && $overrides['which_collection_address'] === 'saved') {
            $collivery_from = $overrides['collivery_from'];
            $contact_from   = $overrides['contact_from'];
        } else {
            $collectionAddress = $this->addColliveryAddress([
                'company_name'  => ($overrides['collection_company_name'] != '') ?
                    $overrides['collection_company_name'] :
                    'Private',
                'building'      => $overrides['collection_building_details'],
                'street'        => $overrides['collection_street'],
                'location_type' => $overrides['collection_location_type'],
                'suburb'        => $overrides['collection_suburb'],
                'town'          => $overrides['collection_town'],
                'full_name'     => $overrides['collection_full_name'],
                'phone'         => preg_replace('/[^0-9]/', '', $overrides['collection_phone']),
                'cellphone'     => preg_replace('/[^0-9]/', '', $overrides['collection_cellphone']),
                'email'         => $overrides['collection_email'],
                'custom_id' => ("wp_".$this->collivery->getColliveryUserId().hash("adler32", str_replace(' ', '', $overrides['collection_email'])))
            ]);

            if (!is_array($collectionAddress)) {
                $errors = $this->collivery->getErrors();
                throw new InvalidColliveryDataException('Error sending to Collivery: ' . implode(', ', $errors), 'automatedOrderToCollivery', $this->loggerSettingsArray(), ['overrides' => $overrides, 'errors' => $errors]);
            }

            $collivery_from = $collectionAddress['id'];
            $contact_from = $collectionAddress['contacts'][0]['id'];
        }

        if (!isset($overrides['which_delivery_address']) && !isset($overrides['collivery_to'])) {
            $deliveryAddress = $this->addColliveryAddress([
                'company_name'  => ($order->get_shipping_company() != '') ? $order->get_shipping_company() : 'Private',
                'building'      => $order->get_shipping_address_2(),
                'street'        => $order->get_shipping_address_1(),
                'location_type' => $order->get_meta('_shipping_location_type'),
                'suburb'        => $order->get_meta('_shipping_suburb'),
                'town'          => $order->get_shipping_city(),
                'full_name'     => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'cellphone'     => preg_replace('/[^0-9]/', '', $order->get_billing_phone()),
                'email'         => str_replace(' ', '', $order->get_billing_email()),
                'custom_id'     => ($this->collivery->getColliveryUserId().$order->get_user_id().hash("adler32", str_replace(' ', '', $order->get_billing_email()))),
            ]);

            if (!is_array($deliveryAddress)) {
                $errors = $this->collivery->getErrors();
                throw new InvalidColliveryDataException('Error sending to Collivery: ' . implode(', ', $errors), 'automatedOrderToCollivery', $this->loggerSettingsArray(), ['overrides' => $overrides, 'errors' => $errors]);
            }

            $collivery_to = $deliveryAddress['id'];
            $contact_to   = $deliveryAddress['contacts'][0]['id'];
        } elseif (isset($overrides['which_delivery_address']) && $overrides['which_delivery_address'] === 'saved') {
            $collivery_to = $overrides['collivery_to'];
            $contact_to   = $overrides['contact_to'];
        } else {
            $deliveryAddress = $this->addColliveryAddress([
                'company_name'  => ($overrides['delivery_company_name'] != '') ?
                    $overrides['delivery_company_name'] :
                    'Private',
                'building'      => $overrides['delivery_building_details'],
                'street'        => $overrides['delivery_street'],
                'location_type' => $overrides['delivery_location_type'],
                'suburb'        => $overrides['delivery_suburb'],
                'town'          => $overrides['delivery_town'],
                'full_name'     => $overrides['delivery_full_name'],
                'phone'         => preg_replace('/[^0-9]/', '', $overrides['delivery_phone']),
                'cellphone'     => preg_replace('/[^0-9]/', '', $overrides['delivery_cellphone']),
                'email'         => $overrides['delivery_email'],
                'custom_id'     => ($this->collivery->getColliveryUserId().$order->get_user_id().hash("adler32", $overrides['delivery_email'])),
            ]);

            if (!is_array($deliveryAddress)) {
                $errors = $this->collivery->getErrors();
                throw new InvalidColliveryDataException('Error sending to Collivery: ' . implode(', ', $errors), 'automatedOrderToCollivery', $this->loggerSettingsArray(), ['overrides' => $overrides, 'errors' => $errors]);
            }

            $collivery_to = $deliveryAddress['id'];
            $contact_to   = $deliveryAddress['contacts'][0]['id'];
        }


        $instructions = $overrides['instructions'] ?? '';
        $customReference  = '';
        $orderNumberPrefix = $this->settings->getValue('order_number_prefix');
        if ($this->settings->getValue('include_order_number') == 'yes') {
            $customReference = $orderNumberPrefix.' ' . $order->get_id();
            $instructions .= $customReference;
        }
        if ($this->settings->getValue('include_customer_note') == 'yes') {
            if (strlen($instructions) > 0) {
                $instructions .= '. ';
            }
            $instructions .= $order->get_customer_note();
        }
        if ($this->settings->getValue('include_product_titles') == 'yes') {
            $count = 1;
            if (strlen($instructions) > 0) {
                $instructions .= ' ';
            }
            $instructions .= ': ';
            foreach ($parcels as $parcel) {
                if (isset($parcel['description'])) {
                    $ending       = ($count == count($parcels)) ? '' : ', ';
                    $instructions .= $parcel['quantity'] . ' X ' . $parcel['description'] . $ending;
                    ++ $count;
                }
            }
        }

        $orderTotal = $this->getOrderTotal($order);

        if (isset($overrides['cover'])) {
            $riskCover = (bool) $overrides['cover'];
        } else {
            $riskCoverEnabled = $this->settings->getValue('risk_cover') == 'yes';
            $overThreshold    = $orderTotal > $this->settings->getValue('risk_cover_threshold', 0);
            $riskCover        = $riskCoverEnabled && $overThreshold;
        }
        $colliveryOptions = [
            'collivery_from' => (int) $collivery_from,
            'contact_from'   => (int) $contact_from,
            'collivery_to'   => (int) $collivery_to,
            'contact_to'     => (int) $contact_to,
            'cust_ref'       => $customReference,
            'instructions'   => $instructions,
            'collivery_type' => 2,
            'service'        => (int) $serviceId,
            'cover'          => $riskCover ? 1 : 0,
            'parcel_count'   => count($parcels),
            'parcels'        => $parcels,
        ];

        if (isset($overrides['collection_time']) && $overrides['collection_time']) {
            $colliveryOptions['collection_time'] = $overrides['collection_time'];
        } else {
            $leadTime = $this->settings->getValue('lead_time') ?? self::TWENTY_FOUR_HOURS;
            $collectionTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s')." + {$leadTime} hours + 5 minutes"));
            // Ensure it's a week day
            while(date('N', strtotime($collectionTime)) >= 6) {
                $collectionTime = date('Y-m-d H:i:s', strtotime($collectionTime.' + 1 days'));
            }
            $colliveryOptions['collection_time'] = $collectionTime;
        }

        if ($serviceId == Collivery::ONX_10) {
            $deliveryTime =  date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime($colliveryOptions['collection_time'])).' 10:00:00 + 2 days'));
            // Ensure it's a week day
            while(date('N', strtotime($deliveryTime)) >= 6) {
                $deliveryTime = date('Y-m-d H:i:s', strtotime($deliveryTime.' + 1 days'));
            }
        }

        $collivery = $this->addCollivery($colliveryOptions);

        if ($collivery['data']['id']) {
            // Save the results from validation into our table
            $this->addColliveryToProcessedTable($collivery, $order->get_id());
            $this->updateStatusOrAddNote($order, 'Order has been sent to MDS Collivery, Waybill Number: ' . $collivery['data']['id'] . ', please have order ready for collection any time from ' . date('Y-m-d H:i', $collivery['data']['collection_time']) . '.', $processing, 'completed');

            return $collivery;
        } else {
            $errors = $this->collivery->getErrors();
            throw new InvalidColliveryDataException('Error sending to Collivery: ' . implode(', ', $errors), 'automatedOrderToCollivery', $this->loggerSettingsArray(), ['data' => $colliveryOptions, 'errors' => $errors, 'result' => $collivery]);
        }
    }

	/**
	 * Auto process to send collection requests to MDS.
	 *
	 * @param      $order_id
	 * @param bool $processing
	 *
	 * @return void
	 */
    public function automatedOrderToCollivery($order_id, $processing = false)
    {
        $order = new WC_Order($order_id);

        try {
            $this->updateStatusOrAddNote($order, 'MDS auto processing has begun.', $processing, 'processing');
            $this->orderToCollivery($order, compact('processing'));
        } catch (OrderAlreadyProcessedException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        } catch (InvalidServiceException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        } catch (InternationalAutomatedException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        } catch (ProductOutOfException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        }  catch (InvalidColliveryDataException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        } catch (InvalidAddressDataException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        } catch (CurlConnectionException $e) {
            $this->updateStatusOrAddNote($order, $e->getMessage(), $processing, 'processing');
        }
    }

    /**
     * Adds the new collivery to our mds processed table.
     *
     * @param int $collivery_id
     * @param int $order_id
     *
     * @return void
     */
    public function addColliveryToProcessedTable($collivery, $order_id)
    {
        global $wpdb;

        // Save the results from validation into our table
        $table_name = $wpdb->prefix . 'mds_collivery_processed';
        $data = [
            'status' => 1,
            'order_id' => $order_id,
            'validation_results' => json_encode($collivery),
            'waybill' => $collivery['data']['id'],
        ];

        $wpdb->insert($table_name, $data);
    }

    /**
     * Adds an address to MDS Collivery.
     *
     * @param array $array
     *
     * @return array
     *
     * @throws InvalidAddressDataException
     * @throws CurlConnectionException
     */
    public function addColliveryAddress(array $array)
    {
        $towns = $this->collivery->make_key_value_array($this->collivery->getTowns(), 'id', 'name');
        $location_types = $this->collivery->getLocationTypes();

        if (!is_numeric($array['town'])) {
            $town_id = (int)array_search($array['town'], $towns);
        } else {
            $town_id = $array['town'];
        }

        $suburbs = $this->collivery->make_key_value_array($this->collivery->getSuburbs($town_id), 'id', 'name');

        if (!is_numeric($array['suburb'])) {
            $suburb_id = (int)array_search($array['suburb'], $suburbs);
        } else {
            $suburb_id = $array['suburb'];
        }

        if (!is_numeric($array['location_type'])) {
            $location_type_id = (int)array_search($array['location_type'], $location_types);
        } else {
            $location_type_id = $array['location_type'];
        }

        if (empty($array['location_type']) || !isset($location_types[$location_type_id])) {
            throw new InvalidAddressDataException('Invalid location type', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), [$array, $location_types, $location_type_id]);
        }

        if (empty($array['town']) || !isset($towns[$town_id])) {
            throw new InvalidAddressDataException('Invalid town', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), [$array, $towns, $town_id]);
        }

        if (empty($array['suburb']) || !isset($suburbs[$suburb_id])) {
            throw new InvalidAddressDataException('Invalid suburb', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), [$array, $suburbs, $suburb_id, $town_id]);
        }

        if (empty($array['cellphone']) || !is_numeric($array['cellphone'])) {
            throw new InvalidAddressDataException('Invalid cellphone number', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
        }

        if (empty($array['email']) || !filter_var($array['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidAddressDataException('Invalid email address', 'MdsColliveryService::addColliveryAddress()', $this->loggerSettingsArray(), $array);
        }

        $newAddress = [
            'company_name' => $array['company_name'],
            'building' => $array['building'],
            'street' => $array['street'],
            'location_type' => $location_type_id,
            'suburb_id' => $suburb_id,
            'town_id' => $town_id,
            'contact' => [
                'full_name' => $array['full_name'], 
                'cellphone' => preg_replace('/[^0-9]/', '', $array['cellphone']), 
                'work_phone' => (!empty($array['phone'])) ? preg_replace('/[^0-9]/', '', $array['phone']) : '',
                'email_address' => $array['email']],
            'custom_id' => $array['custom_id']
        ];

        // Before adding an address lets search MDS and see if we have already added this address
        $searchAddresses = $this->searchAndMatchAddress([
            'custom_id' => $array['custom_id']
        ], $newAddress, $array['custom_id'], 0);

        if (is_array($searchAddresses)) {
            return $searchAddresses;
        } else {
            $this->cache->clear(['addresses', 'contacts']);
            $newAddress['custom_id'] = $searchAddresses;
            return $this->collivery->addAddress($newAddress);
        }
    }

    /**
     * Searches for an address and matches each important field.
     * If it doesn't matches, it tries again adding a character to the end of the custom_id which increments each try.
     * If a matches it returns the new Address
     * If it doesn't match and an address isn't found with the new custom_id it returns the custom_id so that it can be added.
     *
     * @param array $filters
     * @param array $newAddress
     * @param string $original_custom_id
     * @param integer $counter
     *
     * @return array|string (returns address found or custom_id to use when inserting new address)
     */
    public function searchAndMatchAddress(array $filters, array $newAddress, $original_custom_id, $counter)
    {
        if ($counter > 0) {
            $filters['custom_id'] = $original_custom_id.$counter;
        }
        $searchAddresses = $this->collivery->getAddresses($filters);
        if (!empty($searchAddresses)) {
            $match = true;

            $matchAddressFields = [
                'company_name' => 'company_name',
                'building_details' => 'building',
                'street' => 'street',
                'location_type' => 'location_type',
                'suburb_id' => 'suburb_id',
                'town_id' => 'town_id',
                'custom_id' => 'custom_id',
            ];

            foreach ($searchAddresses as $address) {
                foreach ($matchAddressFields as $mdsField => $newField) {
                    if (!array_key_exists($mdsField, $address) || $address[$mdsField] != $newAddress[$newField]) {
                        $match = false;
                    }
                }

                if ($match) {
                    if (!isset($address['contact_id'])) {
                        $contacts = $this->collivery->getContacts($address['address_id']);
                        [$contact_id] = array_keys($contacts);
                        $address['contact_id'] = $contact_id;
                    }

                    return $address;
                }
            }

            if (!$match) {
                $counter++;
                return $this->searchAndMatchAddress($filters, $newAddress, $original_custom_id, $counter);
            }
        } else {
            $this->collivery->clearErrors();
        }

        return $filters['custom_id'];
    }

    /**
     * Get Town and Location Types for Checkout selects from MDS.
     *
     * @return array|bool
     */
    public function returnFieldDefaults()
    {
        $towns = $this->collivery->getTowns();
        $location_types = $this->collivery->getLocationTypes();

        if (!is_array($towns) || !is_array($location_types)) {
            return false;
        }

        return [
            'towns' => array_combine($towns, $towns),
            'location_types' => array_combine($location_types, $location_types)
        ];
    }

    /**
     * If the exception is thrown the base exception class will log this along with a check to see if the processed table exists.
     *
     * @param int $orderId
     *
     * @return int
     *
     * @throws OrderAlreadyProcessedException
     */
    public function hasOrderBeenProcessed($orderId)
    {
        global $wpdb;

        /** @noinspection SqlResolve */
        $result = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(*)
			FROM {$wpdb->prefix}mds_collivery_processed
			WHERE order_id=%d
		", $orderId));

        $tableName = $wpdb->prefix . 'mds_collivery_processed';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            throw new OrderAlreadyProcessedException("Database table $tableName does not exists so we cannot confirm if the order has been processed, unable to continue", $this->loggerSettingsArray(), [
                'order_id' => $orderId,
                'table' => $tableName,
            ]);
        }

        return $result;
    }

    /**
     * @param $userId
     * @param $field
     *
     * @return null|string
     */
    public function extractUserProfileField($userId, $field)
    {
        global $wpdb;

        /** @noinspection SqlResolve */
        return $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}usermeta WHERE user_id=%d AND meta_key=%s", $userId, $field));
    }

    /**
     * Returns the MDS Collivery class.
     *
     * @return Collivery
     */
    public function returnColliveryClass()
    {
        return $this->collivery;
    }

    /**
     * Returns the MDS Cache class.
     *
     * @return MdsCache
     */
    public function returnCacheClass()
    {
        return $this->cache;
    }

    /**
     * Returns the UnitConverter class.
     *
     * @return UnitConverter
     */
    public function returnConverterClass()
    {
        return $this->converter;
    }

    /**
     * Returns the WC_Mds_Shipping_Method plugin settings.
     *
     * @return MdsSettings
     */
    public function returnPluginSettings()
    {
        return $this->settings;
    }

    /**
     * Returns true or false depending on if the plugin is enabled or not.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->settings->getValue('enabled') == 'yes';
    }

    /**
     * Gets default address of the MDS Account.
     *
     * @return array|bool
     */
    public function returnDefaultAddress()
    {
        try {
            $default_address_id = $this->collivery->getDefaultAddressId();
            if (!$default_address = $this->collivery->getAddress($default_address_id)) {
                return false;
            }

            $data = [
                'address' => $default_address,
                'default_address_id' => $default_address_id,
            ];

            if (!isset($default_address['contacts'])) {
                $data['contacts'] = $this->collivery->getContacts($default_address_id);
            } else {
                $data['contacts'] = $default_address['contacts'];
            }

            return $data;
        } catch (CurlConnectionException $e) {
            return [];
        }
    }

    /**
     * @return null|array
     */
    public function returnColliveryValidatedData()
    {
        return $this->validated_data;
    }

    /**
     * @return array
     */
    public function loggerSettingsArray()
    {
        return $this->environment->loggerFormat();
    }

    /**
     * @return array
     */
    public function getColliveryErrors()
    {
        return $this->collivery->getErrors();
    }

    /**
     * @return bool|string
     */
    public function downloadLogFiles()
    {
        if ($file = $this->logger->downloadErrorFile()) {
            return $file;
        } else {
            $this->logger->error('', '', $this->environment->loggerFormat());
            if ($file = $this->logger->downloadErrorFile()) {
                return $file;
            }
        }

        return false;
    }

    private function getOrderTotal(\WC_Order $order) {
        $items                     = $order->get_items();
        $shouldExcludeVirtual      = $this->settings->getValue( 'fee_exclude_virtual' ) === 'yes';
        $shouldExcludeDownloadable = $this->settings->getValue( 'fee_exclude_downloadable' ) === 'yes';
        $cartTotal                 = $order->get_cart_tax();

        if ( $shouldExcludeVirtual ) {
            $items = array_filter( $items, function ( \WC_Order_Item_Product $item ) {
                return !$item->get_product()->is_virtual();
            } );
        }
        if ( $shouldExcludeDownloadable ) {
            $items = array_filter( $items, function ( $item ) {
                return !$item->get_product()->is_virtual();
            } );
        }

        /** @var WC_Order_Item_Product $item */
        foreach ( $items as $item ) {
            /** @var \WC_Product $product */
            $product      = $item->get_product();
            $productPrice = $product->get_price() * $item->get_quantity();
            $cartTotal    += $productPrice;
        }

        return $cartTotal;
    }

    /**
     * @param string|int $town
     * @param string     $suburbName
     *
     * @return int|null
     * @throws CurlConnectionException
     */
    public function searchSuburbByName( $town, $suburbName )
    {
        if(empty($town) || empty($suburbName)) {
            return;
        }

        if (is_numeric($suburbName)) {
            return (int) $suburbName;
        }

        $collivery = $this->returnColliveryClass();

        if(!is_numeric($town)) {
            $towns     = $collivery->searchTowns($town);
            if (!isset($towns['towns']) || $towns['towns'] == null) {
                return $suburbName;
            }
            $towns     = array_keys($towns['towns']);
            $town      = reset($towns);
        }

        $suburbs = $collivery->make_key_value_array($collivery->getSuburbs($town), 'id', 'name');

        return (int)array_search($suburbName, $suburbs);
    }
}
