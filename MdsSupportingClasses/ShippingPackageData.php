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
     * @throws \MdsExceptions\SoapConnectionException
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
        $requiredFields = (object) array(
            'to_town_id' => $this->getTownId($extractedFields),
            'to_town_type' => $this->getLocationType($extractedFields)
        );

        if (!isset($requiredFields->to_town_id) || ($requiredFields->to_town_id == '')) {
            return $packages;
        }

        $towns = $this->collivery->getTowns();
        $location_types = $this->collivery->getLocationTypes();

        $package['cart'] = $cart;
        $package['method_free'] = $this->settings->getValue('method_free');
        $package['free_min_total'] = $this->settings->getValue('free_min_total');
        $package['free_local_only'] = $this->settings->getValue('free_local_only');
        $package['shipping_cart_total'] = $this->getShippingCartTotal();

        $package['destination'] = array(
            'from_town_id' => (int) $defaults['address']['town_id'],
            'from_location_type' => (int) $defaults['address']['location_type'],
            'city' => $requiredFields->to_town_id,
            'to_town_id' => (int) array_search($requiredFields->to_town_id, $towns),
            'to_location_type' => (int) array_search($requiredFields->to_town_type, $location_types),
            'country' => 'ZA',
        );

        $customer = WC ()->customer;
        if ( !isset($_POST['ship_to_different_address']) || $_POST['ship_to_different_address'] != true) {
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
                $data = array(
                        'num_package' => 1,
                        'service' => 2,
                        'exclude_weekend' => 1,
                    ) + $package['destination'];

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
                $to_town_id = $postData['billing_city'];
                $to_town_type = $postData['billing_location_type'];
            } else {
                $to_town_id = $postData['shipping_city'];
                $to_town_type = $postData['shipping_location_type'];
            }
        } elseif (isset($array['ship_to_different_address'])) {
            if (!isset($array['ship_to_different_address']) || $array['ship_to_different_address'] != true) {
                $to_town_id = $array['billing_city'];
                $to_town_type = $array['billing_location_type'];
            } else {
                $to_town_id = $array['shipping_city'];
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

        return (object) compact('to_town_id', 'to_town_type');
    }

    /**
     * @param stdClass $fields
     * @return int|null|string
     */
    private function getTownId(stdClass $fields)
    {
        $to_town_id = $fields->to_town_id;

        if (empty($fields->to_town_id) && get_current_user_id() > 0) {
            $to_town_id = $this->service->extractUserProfileField(get_current_user_id(), 'billing_city');
        }

        return $to_town_id;
    }

    /**
     * @param stdClass $fields
     * @return int|null|string
     */
    private function getLocationType(stdClass $fields)
    {
        $to_town_type = property_exists($fields, 'to_town_type')? $fields->to_town_type : 1;

        if (empty($fields->to_town_type) && get_current_user_id() > 0) {
            $to_town_type = $this->service->extractUserProfileField(get_current_user_id(), 'billing_location_type');
            if (empty($to_town_type)) {
                $to_town_type = 'Private House';
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
