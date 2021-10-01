<?php

namespace MdsSupportingClasses;

use stdClass;

class ShippingPackageData
{
    /**
     * @var MdsColliveryService
     */
    protected $service;
    /**
     * @var Collivery
     */
    protected $collivery;
    /**
     * @var MdsSettings
     */
    protected $settings;

    /** @var \WP_User */
    protected $user;

    /**
     * ShippingPackageData constructor.
     */
    public function __construct()
    {
        $this->service = MdsColliveryService::getInstance();
        $this->settings = $this->service->returnPluginSettings();
        $this->collivery = $this->service->returnColliveryClass();
        $this->user = wp_get_current_user();
    }

    /**
     * @param $packages
     * @param $input
     *
     * @return array
     */
    public function build($packages, $input)
    {
	    $defaults = $this->service->returnDefaultAddress();
	    if ( $this->settings->getValue('enabled') == 'no' || ! $defaults ) {
            return $packages;
        }

	    $packageKey = array_keys($packages)[0];
        $package = $this->appendPackageData($packages[$packageKey]);

        $extractedFields = $this->extractRequiredFields($input, $packages);
        $requiredFields = [
	        'to_town_id'   => $this->getTownId($extractedFields),
	        'to_town_type' => $this->getLocationType($extractedFields)];

        if (!isset($requiredFields['to_town_id']) || ($requiredFields['to_town_id'] == '')) {
            return $packages;
        }

        if (!is_int($requiredFields['to_town_id'])) {
            // Assume it's the town name.
            $towns = $this->collivery->getTowns();
            foreach ($towns as $item) {
                if ($requiredFields['to_town_id'] == $item['name']) {
                    $requiredFields['to_town_id'] = $item['id'];
                    break;
                }
            }
        }
        
        if (is_int($requiredFields['to_town_id'])) {
            $suburb = $this->collivery->getSuburbs($requiredFields['to_town_id']);
            $city = $suburb[0]['town']['name'];
            $country = 'ZA';
        } else {
            $city = $requiredFields['to_town_id'];
            $country = $packages[0]['destination']['country'];
        }

	    $destination = [
		    'from_town_id' => (int) $defaults['address']['town_id'],
		    'from_location_type' => (int) $defaults['address']['location_type'],
		    'city' => $city,
		    'to_town_id' => (int) $requiredFields['to_town_id'],
		    'to_location_type' => (int) $requiredFields['to_town_type'],
		    'country' => $country,
	    ];

        $customer = WC()->customer;
        $ship_to_different_address = false;

        if (isset($input['post_data'])) {
            parse_str($input['post_data'], $postData);
            $ship_to_different_address = $postData['ship_to_different_address'] ?? $ship_to_different_address;
        } else {
            $ship_to_different_address = $input['ship_to_different_address'] ?? $ship_to_different_address;
        }

		if ($ship_to_different_address) {
            $destination['state'] = $customer->get_billing_state();
            $destination['postcode'] = $customer->get_billing_postcode();
            $destination['address'] = $customer->get_billing_address_1();
            $destination['address_2'] = $customer->get_billing_address_2();
        } else {
            $destination['state'] = $customer->get_shipping_state();
            $destination['postcode'] = $customer->get_shipping_postcode();
            $destination['address'] = $customer->get_shipping_address_1();
            $destination['address_2'] = $customer->get_shipping_address_2();
        }

	    $package['destination'] = array_merge($package['destination'], $destination);

	    if (!$this->service->validPackage($package)) {
            return $packages;
        }

        if ($this->shouldBeFree() && !$this->applyFreeDeliveryBlacklist()) {
            $package['service'] = 'free';
            if ($this->settings->getValue('free_local_only') == 'yes') {
	            $defaults  = [
                    'delivery_town'            => $package['destination']['to_town_id'],
                    'collection_town'          => $package['destination']['from_town_id'],
                    'delivery_location_type'   => $package['destination']['to_location_type'],
                    'collection_location_type' => $package['destination']['from_location_type'],
		            'num_package'     => 1,
		            'services'         => [$this->settings->getInstanceValue('free_default_service', 2)],
		            'exclude_weekend' => 1,
	            ];
	            $data = array_merge($defaults, $package['destination']);

                // Query the API to test if this is a local delivery
                $response = $this->collivery->getPrice($data);
                if (isset($response['delivery_type']) && $response['delivery_type'] == 'local') {
                    $package['local'] = 'yes';
                } else {
                    $package['service'] = null;
                    $package['local'] = 'no';
                }
            }
        }

        $package['contents_cost'] = $package['contents_cost'] ?? $package['shipping_cart_total'];

	    // The return data should be the entire `$packages` array
	    return [$packageKey => $package];
    }

    /**
     * @param array $array
     *
     * @param array $packages
     * @return stdClass
     */
    public function extractRequiredFields(array $array, array $packages)
    {
    	$to_town_id = $to_town_type = null;
        if (isset($array['post_data'])) {
            parse_str($array['post_data'], $postData);
            if (!isset($postData['ship_to_different_address']) || $postData['ship_to_different_address'] != true) {
                $to_town_id = isset($postData['billing_city']) ? $postData['billing_city'] : $postData['billing_city_int'];
                $to_town_type = $postData['billing_location_type'];
            } else {
                $to_town_id = isset($postData['shipping_city']) ? $postData['shipping_city'] : $postData['shipping_city_int'];
                $to_town_type = $postData['shipping_location_type'];
            }
        } elseif (isset($array['ship_to_different_address'])) {
            if (!isset($array['ship_to_different_address']) || $array['ship_to_different_address'] != true) {
                $to_town_id = isset($array['billing_city']) ? $array['billing_city'] : $array['billing_city_int'];
                $to_town_type = $array['billing_location_type'];
            } else {
                $to_town_id = isset($array['shipping_city']) ? $array['shipping_city'] : $array['shipping_city_int'];
                $to_town_type = $array['shipping_location_type'];
            }
        } elseif (isset($packages[0]['destination'])) {
            $to_town_id = $packages[0]['destination']['city'];
            if (!isset($array['ship_to_different_address']) || $array['ship_to_different_address'] != true) {
                if (isset($array['billing_location_type'])) {
                    $to_town_type = $array['billing_location_type'];
                }
            } else {
                if (isset($array['shipping_location_type'])) {
                    $to_town_type = $array['shipping_location_type'];
                }
            }
        }

        return ["to_town_id" => $to_town_id, "to_town_type" => $to_town_type];
        //return (object) compact('to_town_id', 'to_town_type');
    }

    /**
     * @param array $fields
     * @return int|null|string
     */
    private function getTownId($fields)
    {
        $to_town_id = $fields['to_town_id'];

        if (empty($fields['to_town_id']) && get_current_user_id() > 0) {
            $to_town_id = $this->service->extractUserProfileField(get_current_user_id(), 'billing_city');
        }

        return $to_town_id;
    }

    /**
     * @param array $fields
     * @return int|null
     */
    private function getLocationType($fields)
    {
        $to_town_type = empty($fields['to_town_type']) == 0 ? $fields['to_town_type'] : 15;

        if (empty($to_town_type) && get_current_user_id() > 0) {
            $to_town_type = $this->service->extractUserProfileField(get_current_user_id(), 'billing_location_type');
            if (empty($to_town_type)) {
                $to_town_type = 15;
            }
        }

        return $to_town_type;
    }

    /**
     * @return boolean
     */
    protected function applyFreeDeliveryBlacklist()
    {
        if (!$this->user->ID) {
            return false;
        }

        $blacklist = $this->settings->getValue('free_delivery_blacklist');
        $blacklist = explode(',', $blacklist);

        foreach ($blacklist as $role) {
            if (strtolower($role) && in_array($role, $this->user->roles)) {
                return true;
            }
        }

        return false;
    }

	private function shouldBeFree()
	{
        $freeActivated = $this->settings->getValue( 'method_free' ) == 'yes';
        $minimum       = $this->settings->getValue( 'free_min_total' );
        $cartTotal     = $this->getShippingCartTotal();

        return $freeActivated && $cartTotal >= $minimum;
	}

    private function getShippingCartTotal() {
        /** @var \WC_Cart $cart */
        $cart                      = WC()->cart->get_cart();
        $shouldExcludeVirtual      = $this->settings->getValue( 'fee_exclude_virtual' ) === 'yes';
        $shouldExcludeDownloadable = $this->settings->getValue( 'fee_exclude_downloadable' ) === 'yes';
        $cartTotal                 = intval(WC()->cart->get_cart_tax());

        if ( $shouldExcludeVirtual ) {
            $cart = array_filter( $cart, function ( $item ) {
                return ! $item['data']->is_virtual();
            } );
        }
        if ( $shouldExcludeDownloadable ) {
            $cart = array_filter( $cart, function ( $item ) {
                return ! $item['data']->is_downloadable();
            } );
        }

        foreach ( $cart as $item ) {
            /** @var \WC_Product $product */
            $product      = $item['data'];
            $productPrice = $product->get_price() * $item['quantity'];
            $cartTotal    += $productPrice;
        }

        return $cartTotal;
    }

    /**
     * Merge in the package data without overwriting all of the $package data
     * Overwriting will have bad side-effects on any other plugins that deal with shipping
     *
     * @param array $package The data received from WC woocommerce_cart_shipping_packages hook
     * @return array
     */
	public function appendPackageData(array $package) {
		$converter = $this->service->returnConverterClass();
		$package['method_free'] = $this->settings->getValue( 'method_free' );
		$package['free_min_total'] = $this->settings->getValue( 'free_min_total' );
		$package['free_local_only'] = $this->settings->getValue( 'free_local_only' );
		$package['shipping_cart_total'] = $this->getShippingCartTotal();
		$package['count'] = 0;
		$package['total'] = 0;
		$package['weight'] = 0;
		$package['max_weight'] = 0;

		if ( ! isset( $package['contents'] ) || ! count( $package['contents'] ) ) {
			return $package;
		}

		foreach ( $package['contents'] as $hash => $item ) {
			/** @var WC_Product $product */
			$product = $item['data'];
			$quantity = $item['quantity'];

			$dimensionUnit = strtolower(get_option('woocommerce_dimension_unit'));
			$weightUnit = strtolower(get_option('woocommerce_weight_unit'));

			$length = (int) $product->get_length();
			$width  = (int) $product->get_width();
			$height = (int) $product->get_height();
			$weight = (float) $product->get_weight();

			// Length conversion, mds collivery only accepts cm
			if ($dimensionUnit !== 'cm') {
				$length = $converter->convert($length, $dimensionUnit, 'cm', 2);
				$width  = $converter->convert($width, $dimensionUnit, 'cm', 2);
				$height = $converter->convert($height, $dimensionUnit, 'cm', 2);
			}

			// Weight conversion, mds collivery only accepts kg
			if ($weightUnit !== 'kg') {
				$weight = $converter->convert($weight, $weightUnit, 'kg', 2);
			}

			$item['length'] = $length;
			$item['width'] = $width;
			$item['height'] = $height;
			$item['weight'] = $weight;
			$item['line_subtotal'] = $product->get_price() * $quantity;
			$item['description'] = $product->get_title();

			// Update our cart item
			$package['contents'][$hash] = $item;

			// Work out Volumetric Weight based on MDS calculations
			$volWeight = ($length * $width * $height) / 4000;

			$package['max_weight'] += $volWeight > $weight ?
				$volWeight * $quantity :
				$weight * $quantity;

			$package['count']  += $quantity;
			$package['total']  += $item['line_subtotal'];
			$package['weight'] += $weight * $quantity;
		}

		return $package;
	}

}
