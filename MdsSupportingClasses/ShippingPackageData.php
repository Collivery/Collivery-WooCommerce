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
     * @return mixed
     */
    public function build($packages, $input)
    {   
        if ($this->settings->getValue('enabled') == 'no' || !$defaults = $this->service->returnDefaultAddress()) {
            return $packages;
        }

        $package = $this->service->buildPackageFromCart(WC()->cart->get_cart());
        $cart = $this->service->getCartContent($package);

        if (!is_array($cart) || !isset($cart['total'])) {
            return $packages;
        }

        $extractedFields = $this->extractRequiredFields($input, $packages);
        $requiredFields = ["to_town_id" => $this->getTownId($extractedFields), "to_town_type" => $this->getLocationType($extractedFields)];

        if (!isset($requiredFields['to_town_id']) || ($requiredFields['to_town_id'] == '')) {
            return $packages;
        }

        

        if (!is_integer($requiredFields['to_town_id'])) {
            // Assume it's the town name.
            $towns = $this->collivery->getTowns();
            foreach ($towns as $item) {
                if ($requiredFields['to_town_id'] == $item['name']) {
                    $requiredFields['to_town_id'] = $item['id'];
                    break;
                }
            }
        }

        
        $location_types = $this->collivery->getLocationTypes();
        if (is_integer($requiredFields['to_town_id'])) {
            $suburb = $this->collivery->getSuburbs($requiredFields['to_town_id']);
            $city = $suburb[0]['town']['name'];
            $country = "ZA";
        } else {
            $city = $requiredFields['to_town_id'];
            $country = $packages[0]['destination']['country'];
        }
        
        $package['cart'] = $cart;
        $package['method_free'] = $this->settings->getValue('method_free');
        $package['free_min_total'] = $this->settings->getValue('free_min_total');
        $package['free_local_only'] = $this->settings->getValue('free_local_only');
        $package['shipping_cart_total'] = $this->getShippingCartTotal();

        $package['destination'] = [
            'from_town_id' => (int) $defaults['address']['town_id'],
            'from_location_type' => (int) $defaults['address']['location_type'],
            'city' => $city,
            'to_town_id' => (int) $requiredFields['to_town_id'],
            'to_location_type' => (int) $requiredFields['to_town_type'],
            'country' => $country,
        ];

        $customer = WC ()->customer;
        $ship_to_different_address = false;

        if (isset($input['post_data'])) {
            parse_str($input['post_data'], $postData);
            $ship_to_different_address = isset($postData['ship_to_different_address']) ? $postData['ship_to_different_address'] : $ship_to_different_address;
        } else {
            $ship_to_different_address = isset($input['ship_to_different_address']) ? $input['ship_to_different_address'] : $ship_to_different_address;
        }

		if ($ship_to_different_address) {
            $package['destination']['state'] = $customer->get_billing_state();
            $package['destination']['postcode'] = $customer->get_billing_postcode();
            $package['destination']['address'] = $customer->get_billing_address_1();
            $package['destination']['address_2'] = $customer->get_billing_address_2();
        } else {
            $package['destination']['state'] = $customer->get_shipping_state();
            $package['destination']['postcode'] = $customer->get_shipping_postcode();
            $package['destination']['address'] = $customer->get_shipping_address_1();
            $package['destination']['address_2'] = $customer->get_shipping_address_2();
        }

        if (!$this->service->validPackage($package)) {
            return $packages;
        }

        if ($this->shouldBeFree() && !$this->applyFreeDeliveryBlacklist()) {
            $package['service'] = 'free';
            if ($this->settings->getValue('free_local_only') == 'yes') {
                $data = [
                        'num_package' => 1,
                        'service' => 2,
                        'exclude_weekend' => 1,
                    ] + $package['destination'];

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

        $packages[0] = $package;
        
        return $packages;
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
}
