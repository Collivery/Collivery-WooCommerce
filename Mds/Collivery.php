<?php

namespace Mds;

use SoapClient; // Use PHP Soap Client
use SoapFault;  // Use PHP Soap Fault

class Collivery {

    protected $token;
    protected $client;
    protected $config;
    protected $errors = array();
    protected $check_cache = 2;
    protected $default_address_id;
    protected $client_id;
    protected $user_id;

    /**
     * Setup class with basic Config
     *
     * @param Array   $config Configuration Array
     * @param Class   $cache  Caching Class with functions has, get, put, forget
     */
    function __construct(array $config = array(), $cache = null) {
        if (is_null($cache)) {
            $this->cache = new Cache();
        } else {
            $this->cache = $cache;
        }

        $this->config = (object) array(
                    'app_name' => 'Default App Name', // Application Name
                    'app_version' => '0.0.1', // Application Version
                    'app_host' => '', // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / 'Joomla! 2.5.17 VirtueMart 2.0.26d'
                    'app_url' => '', // URL your site is hosted on
                    'user_email' => 'demo@collivery.co.za',
                    'user_password' => 'demo',
                    'demo' => false,
        );

        foreach ($config as $key => $value) {
            $this->config->$key = $value;
        }

        if ($this->config->demo) {
            $this->config->user_email = 'demo@collivery.co.za';
            $this->config->user_password = 'demo';
        }

        $this->authenticate();
    }

    /**
     * Setup the Soap Object
     *
     * @return SoapClient MDS Collivery Soap Client
     */
    protected function init() {
        if (!$this->client) {
            try {
                $this->client = new SoapClient(// Setup the soap client
                        'http://www.collivery.co.za/wsdl/v2', // URL to WSDL File
                        array('cache_wsdl' => WSDL_CACHE_NONE) // Don't cache the WSDL file
                );
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if the Soap Client has been set, and returns it.
     *
     * @return  SoapClient  Webserver Soap Client
     */
    protected function client() {
        if (!$this->client) {
            $this->init();
        }

        if (!$this->token) {
            $this->authenticate();
        }

        return $this->client;
    }

    /**
     * Authenticate and set the token
     *
     * @return string
     */
    protected function authenticate() {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.auth')) {
            $authenticate = $this->cache->get('collivery.auth');

            $this->default_address_id = $authenticate['default_address_id'];
            $this->client_id = $authenticate['client_id'];
            $this->user_id = $authenticate['user_id'];
            $this->token = $authenticate['token'];

            return true;
        } else {
            if (!$this->init())
                return false;

            $user_email = $this->config->user_email;
            $user_password = $this->config->user_password;

            try {
                $authenticate = $this->client->authenticate($user_email, $user_password, $this->token, array(
                    'name' => $this->config->app_name . ' mds/collivery/class',
                    'version' => $this->config->app_version,
                    'host' => $this->config->app_host,
                    'url' => $this->config->app_url,
                    'lang' => 'PHP ' . phpversion(),
                        ));
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (is_array($authenticate) && isset($authenticate['token'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.auth', $authenticate, 50);

                $this->default_address_id = $authenticate['default_address_id'];
                $this->client_id = $authenticate['client_id'];
                $this->user_id = $authenticate['user_id'];
                $this->token = $authenticate['token'];

                return true;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns a list of towns and their ID's for creating new addresses.
     * Town can be filtered by country of province (ZAF Only).
     *
     * @param string  $country  Filter towns by Country
     * @param string  $province Filter towns by South African Provinces
     * @return array            List of towns and their ID's
     */
    public function getTowns($country = "ZAF", $province = null) {
        if (( $this->check_cache == 2 ) && is_null($province) && $this->cache->has('collivery.towns.' . $country)) {
            return $this->cache->get('collivery.towns.' . $country);
        } elseif (( $this->check_cache == 2 ) && !is_null($province) && $this->cache->has('collivery.towns.' . $country . '.' . $province)) {
            return $this->cache->get('collivery.towns.' . $country . '.' . $province);
        } else {
            try {
                $result = $this->client()->get_towns($this->token, $country, $province);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['towns'])) {
                if (is_null($province)) {
                    if ($this->check_cache != 0)
                        $this->cache->put('collivery.towns.' . $country, $result['towns'], 60 * 24);
                } else {
                    if ($this->check_cache != 0)
                        $this->cache->put('collivery.towns.' . $country . '.' . $province, $result['towns'], 60 * 24);
                }
                return $result['towns'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Allows you to search for town and suburb names starting with the given string.
     * The minimum string length to search is two characters.
     * Returns a list of towns, suburbs, and the towns the suburbs belong to with their ID's for creating new addresses.
     * The idea is that this could be used in an auto complete function.
     *
     * @param string  $name Start of town/suburb name
     * @return array          List of towns and their ID's
     */
    public function searchTowns($name) {
        if (strlen($name) < 2) {
            return $this->get_towns();
        } elseif (( $this->check_cache == 2 ) && $this->cache->has('collivery.search_towns.' . $name)) {
            return $this->cache->get('collivery.search_towns.' . $name);
        } else {
            try {
                $result = $this->client()->search_towns($name, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result)) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.search_towns.' . $name, $result, 60 * 24);

                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns all the suburbs of a town.
     *
     * @param int     $town_id ID of the Town to return suburbs for
     * @return array
     */
    public function getSuburbs($town_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.suburbs.' . $town_id)) {
            return $this->cache->get('collivery.suburbs.' . $town_id);
        } else {
            try {
                $result = $this->client()->get_suburbs($town_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['suburbs'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.suburbs.' . $town_id, $result['suburbs'], 60 * 24 * 7);
                return $result['suburbs'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the type of Address Locations.
     * Certain location type incur a surcharge due to time spent during
     * delivery.
     *
     * @return array
     */
    public function getLocationTypes() {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.location_types')) {
            return $this->cache->get('collivery.location_types');
        } else {
            try {
                $result = $this->client()->get_location_types($this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['results'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.location_types', $result['results'], 60 * 24 * 7);
                return $result['results'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the available Collivery services types.
     *
     * @return array
     */
    public function getServices() {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.services')) {
            return $this->cache->get('collivery.services');
        } else {
            try {
                $result = $this->client()->get_services($this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['services'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.services', $result['services'], 60 * 24 * 7);
                return $result['services'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No services returned.');

                return false;
            }
        }
    }

    /**
     * Returns the available Parcel Type ID and value array for use in adding a collivery.
     *
     * @return array  Parcel  Types
     */
    public function getParcelTypes() {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.parcel_types')) {
            return $this->cache->get('collivery.parcel_types');
        } else {
            try {
                $result = $this->client()->get_parcel_types($this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (is_array($result)) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.parcel_types', $result, 60 * 24 * 7);
                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the available Parcel Type ID and value array for use in adding a collivery.
     *
     * @param int     $address_id The ID of the address you wish to retrieve.
     * @return array               Address
     */
    public function getAddress($address_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.address.' . $this->client_id . '.' . $address_id)) {
            return $this->cache->get('collivery.address.' . $this->client_id . '.' . $address_id);
        } else {
            try {
                $result = $this->client()->get_address($address_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['address'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.address.' . $this->client_id . '.' . $address_id, $result['address'], 60 * 24);
                return $result['address'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns all the addresses belonging to a client.
     *
     * @param array   $filter Filter Addresses
     * @return array
     */
    public function getAddresses(array $filter = array()) {
        if (( $this->check_cache == 2 ) && empty($filter) && $this->cache->has('collivery.addresses.' . $this->client_id)) {
            return $this->cache->get('collivery.addresses.' . $this->client_id);
        } else {
            try {
                $result = $this->client()->get_addresses($this->token, $filter);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['addresses'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.addresses.' . $this->client_id, $result['addresses'], 60 * 24);
                return $result['addresses'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the Contact people of a given Address ID.
     *
     * @param int     $address_id Address ID
     * @return array
     */
    public function getContacts($address_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.contacts.' . $this->client_id . '.' . $address_id)) {
            return $this->cache->get('collivery.contacts.' . $this->client_id . '.' . $address_id);
        } else {
            try {
                $result = $this->client()->get_contacts($address_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['contacts'])) {
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.contacts.' . $this->client_id . '.' . $address_id, $result['contacts'], 60 * 24);
                return $result['contacts'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the POD image for a given Waybill Number.
     *
     * @param int     $collivery_id Collivery waybill number
     * @return array
     */
    public function getPod($collivery_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.pod.' . $this->client_id . '.' . $collivery_id)) {
            return $this->cache->get('collivery.pod.' . $this->client_id . '.' . $collivery_id);
        } else {
            try {
                $result = $this->client()->get_pod($collivery_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['pod'])) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.pod.' . $this->client_id . '.' . $collivery_id, $result['pod'], 60 * 24);

                return $result['pod'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns a list of avaibale parcel images for a given Waybill Number.
     *
     * @param int     $collivery_id Collivery waybill number
     * @return array
     */
    public function getParcelImageList($collivery_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id)) {
            return $this->cache->get('collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id);
        } else {
            try {
                $result = $this->client()->get_parcel_image_list($collivery_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['images'])) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.parcel_image_list.' . $this->client_id . '.' . $collivery_id, $result['images'], 60 * 12);

                return $result['images'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the image of a given parcel-id of a waybill.
     * If the Waybill number is 54321 and there are 3 parcels, they would
     * be referenced by id's 54321-1, 54321-2 and 54321-3.
     *
     * @param string  $parcel_id Parcel ID
     * @return array               Array containing all the information
     *                             about the image including the image
     *                             itself in base64
     */
    public function getParcelImage($parcel_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.parcel_image.' . $this->client_id . '.' . $parcel_id)) {
            return $this->cache->get('collivery.parcel_image.' . $this->client_id . '.' . $parcel_id);
        } else {
            try {
                $result = $this->client()->get_parcel_image($parcel_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['image'])) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.parcel_image.' . $this->client_id . '.' . $parcel_id, $result['image'], 60 * 24);

                return $result['image'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the status tracking detail of a given Waybill number.
     * If the collivery is still active, the estimated time of delivery
     * will be provided. If delivered, the time and receivers name (if availble)
     * with returned.
     *
     * @param int     $collivery_id Collivery ID
     * @return array                 Collivery Status Information
     */
    public function getStatus($collivery_id) {
        if (( $this->check_cache == 2 ) && $this->cache->has('collivery.status.' . $this->client_id . '.' . $collivery_id)) {
            return $this->cache->get('collivery.status.' . $this->client_id . '.' . $collivery_id);
        } else {
            try {
                $result = $this->client()->get_collivery_status($collivery_id, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['status_id'])) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                if ($this->check_cache != 0)
                    $this->cache->put('collivery.status.' . $this->client_id . '.' . $collivery_id, $result, 60 * 12);

                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Create a new Address and Contact
     *
     * @param array   $data Address and Contact Information
     * @return array         Address ID and Contact ID
     */
    public function addAddress(array $data) {
        $location_types = $this->getLocationTypes();
        $towns = $this->getTowns();
        $suburbs = $this->getSuburbs($data['town_id']);

        if (!isset($data['location_type']))
            $this->setError('missing_data', 'location_type not set.');
        elseif (!isset($location_types[$data['location_type']]))
            $this->setError('invalid_data', 'Invalid location_type.');

        if (!isset($data['town_id']))
            $this->setError('missing_data', 'town_id not set.');
        elseif (!isset($towns[$data['town_id']]))
            $this->setError('invalid_data', 'Invalid town_id.');

        if (!isset($data['suburb_id']))
            $this->setError('missing_data', 'suburb_id not set.');
        elseif (!isset($suburbs[$data['suburb_id']]))
            $this->setError('invalid_data', 'Invalid suburb_id.');

        if (!isset($data['street']))
            $this->setError('missing_data', 'street not set.');

        if (!isset($data['full_name']))
            $this->setError('missing_data', 'full_name not set.');

        if (!isset($data['phone']) and ! isset($data['cellphone']))
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->add_address($data, $this->token);
                $this->cache->forget('collivery.addresses.' . $this->client_id);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['address_id'])) {
                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Add's a contact person for a given Address ID
     *
     * @param array   $data New Contact Data
     * @return int           New Contact ID
     */
    public function addContact(array $data) {
        if (!isset($data['address_id']))
            $this->setError('missing_data', 'address_id not set.');
        elseif (!is_array($this->getAddress($data['address_id'])))
            $this->setError('invalid_data', 'Invalid address_id.');

        if (!isset($data['street']))
            $this->setError('missing_data', 'street not set.');

        if (!isset($data['full_name']))
            $this->setError('missing_data', 'full_name not set.');

        if (!isset($data['phone']) and ! isset($data['cellphone']))
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->add_address($data, $this->token);
                $this->cache->forget('collivery.addresses.' . $this->client_id);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['address_id'])) {
                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Returns the price based on the data provided.
     *
     * @param array   $data Your Collivery Details
     * @return array         Pricing for details supplied
     */
    public function getPrice(array $data) {
        $towns = $this->getTowns();

        if (!isset($data['collivery_from']) && !isset($data['from_town_id']))
            $this->setError('missing_data', 'collivery_from/from_town_id not set.');
        elseif (isset($data['collivery_from']) && !is_array($this->getAddress($data['collivery_from'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_from.');
        elseif (isset($data['from_town_id']) && !isset($towns[$data['from_town_id']]))
            $this->setError('invalid_data', 'Invalid Town ID for: from_town_id.');

        if (!isset($data['collivery_to']) && !isset($data['to_town_id']))
            $this->setError('missing_data', 'collivery_to/to_town_id not set.');
        elseif (isset($data['collivery_to']) && !is_array($this->getAddress($data['collivery_to'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_to.');
        elseif (isset($data['to_town_id']) && !isset($towns[$data['to_town_id']]))
            $this->setError('invalid_data', 'Invalid Town ID for: to_town_id.');

        if (!isset($data['service']))
            $this->setError('missing_data', 'service not set.');

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->get_price($data, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (is_array($result)) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);

                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Validate Collivery
     *
     * Returns the validated data array of all details pertaining to a collivery.
     * This process validates the information based on services, time frames and parcel information.
     * Dates and times may be altered during this process based on the collection and delivery towns service parameters.
     * Certain towns are only serviced on specific days and between certain times.
     * This function automatically alters the values.
     * The parcels volumetric calculations are also done at this time.
     * It is important that the data is first validated before a collivery can be added.
     *
     * @param array   $data Properties of the new Collivery
     * @return array         The validated data
     */
    public function validate(array $data) {
        $contacts_from = $this->getContacts($data['collivery_from']);
        $contacts_to = $this->getContacts($data['collivery_to']);
        $parcel_types = $this->getParcelTypes();
        $services = $this->getServices();


        if (!isset($data['collivery_from']))
            $this->setError('missing_data', 'collivery_from not set.');
        elseif (!is_array($this->getAddress($data['collivery_from'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_from.');

        if (!isset($data['contact_from']))
            $this->setError('missing_data', 'contact_from not set.');
        elseif (!isset($contacts_from[$data['contact_from']]))
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_from.');

        if (!isset($data['collivery_to']))
            $this->setError('missing_data', 'collivery_to not set.');
        elseif (!is_array($this->getAddress($data['collivery_to'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_to.');

        if (!isset($data['contact_to']))
            $this->setError('missing_data', 'contact_to not set.');
        elseif (!isset($contacts_to[$data['contact_to']]))
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_to.');

        if (!isset($data['collivery_type']))
            $this->setError('missing_data', 'collivery_type not set.');
        elseif (!isset($parcel_types[$data['collivery_type']]))
            $this->setError('invalid_data', 'Invalid collivery_type.');

        if (!isset($data['service']))
            $this->setError('missing_data', 'service not set.');
        elseif (!isset($services[$data['service']]))
            $this->setError('invalid_data', 'Invalid service.');

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->validate_collivery($data, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (is_array($result)) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);

                return $result;
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Creates a new Collivery based on the data array provided.
     * The array should first be validated before passing to this function.
     * The Waybill No is return apon successful creation of the collivery.
     *
     * @param array   $data Properties of the new Collivery
     * @return int           New Collivery ID
     */
    public function addCollivery(array $data) {
        $contacts_from = $this->getContacts($data['collivery_from']);
        $contacts_to = $this->getContacts($data['collivery_to']);
        $parcel_types = $this->getParcelTypes();
        $services = $this->getServices();

        if (!isset($data['collivery_from']))
            $this->setError('missing_data', 'collivery_from not set.');
        elseif (!is_array($this->getAddress($data['collivery_from'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_from.');

        if (!isset($data['contact_from']))
            $this->setError('missing_data', 'contact_from not set.');
        elseif (!isset($contacts_from[$data['contact_from']]))
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_from.');

        if (!isset($data['collivery_to']))
            $this->setError('missing_data', 'collivery_to not set.');
        elseif (!is_array($this->getAddress($data['collivery_to'])))
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_to.');

        if (!isset($data['contact_to']))
            $this->setError('missing_data', 'contact_to not set.');
        elseif (!isset($contacts_to[$data['contact_to']]))
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_to.');

        if (!isset($data['collivery_type']))
            $this->setError('missing_data', 'collivery_type not set.');
        elseif (!isset($parcel_types[$data['collivery_type']]))
            $this->setError('invalid_data', 'Invalid collivery_type.');

        if (!isset($data['service']))
            $this->setError('missing_data', 'service not set.');
        elseif (!isset($services[$data['service']]))
            $this->setError('invalid_data', 'Invalid service.');

        if (!$this->hasErrors()) {
            try {
                $result = $this->client()->add_collivery($data, $this->token);
            } catch (SoapFault $e) {
                $this->catchSoapFault($e);
                return false;
            }

            if (isset($result['collivery_id'])) {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);

                return $result['collivery_id'];
            } else {
                if (isset($result['error_id']))
                    $this->setError($result['error_id'], $result['error']);
                else
                    $this->setError('result_unexpected', 'No address_id returned.');

                return false;
            }
        }
    }

    /**
     * Accepts the newly created Collivery, moving it from Waiting Client Acceptance
     * to Accepted so that it can be processed.
     *
     * @param int     $collivery_id ID of the Collivery you wish to accept
     * @return boolean                 Has the Collivery been accepted
     */
    public function acceptCollivery($collivery_id) {
        try {
            $result = $this->client()->accept_collivery($collivery_id, $this->token);
        } catch (SoapFault $e) {
            $this->catchSoapFault($e);
            return false;
        }

        if (isset($result['result'])) {
            if (isset($result['error_id']))
                $this->setError($result['error_id'], $result['error']);

            return $result['result'] == 'Accepted';
        } else {
            if (isset($result['error_id']))
                $this->setError($result['error_id'], $result['error']);
            else
                $this->setError('result_unexpected', 'No address_id returned.');

            return false;
        }
    }

    /**
     * Handle error messages in SoapFault
     *
     * @param SoapFault $e SoapFault Object
     */
    protected function catchSoapFault($e) {
        $this->setError($e->faultcode, $e->faultstring);
    }

    /**
     * Add a new error
     *
     * @param string  $id   Error ID
     * @param string  $text Error text
     */
    protected function setError($id, $text) {
        $this->errors[$id] = $text;
    }

    /**
     * Retrieve errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Check if this instance has an error
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Clears all the Errors
     */
    public function clearErrors() {
        $this->errors = array();
    }

    /**
     * Disable Cached completely and retrieve data directly from the webservice
     */
    public function disableCache() {
        $this->check_cache = 0;
    }

    /**
     * Ignore Cached data and retrieve data directly from the webservice
     * Save returned data to Cache
     */
    public function ignoreCache() {
        $this->check_cache = 1;
    }

    /**
     * Check if cache exists before querying the webservice
     * If webservice was queried, save returned data to Cache
     */
    public function enableCache() {
        $this->check_cache = 2;
    }

    /**
     * Returns the clients default address
     *
     * @return int Address ID
     */
    public function getDefaultAddressId() {
        return $this->default_address_id;
    }

}
