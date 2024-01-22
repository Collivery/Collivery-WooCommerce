<?php

namespace MdsSupportingClasses;

use MdsExceptions\CurlConnectionException;
use MdsLogger;

class Collivery
{
    const BASE_URL = 'https://api.collivery.co.za/v3/';
    const SDX = 1;
    const ONX = 2;
    const FRT = 3;
    const ECO = 5;
    const ONX_10 = 6;
    public static $serviceTexts = [
        self::ONX => 'Over Night',
        self::ECO  => 'Road Freight Express',
        self::FRT  => 'Road Freight'
    ];

    protected $token;
    protected $client;
    protected $config;
    protected $errors = [];
    protected $check_cache = true;

    protected $default_address_id;
    protected $client_id;
    protected $user_id;

    /**
     * Setup class with basic Config.
     *
     * @param array         $config Configuration Array
     * @param MdsCache|null $cache Caching Class with functions has, get, put, forget
     */
    public function __construct(array $config = [],MdsCache $cache = null)
    {
        if (is_null($cache)) {
            $cache_dir = array_key_exists('cache_dir', $config) ? $config['cache_dir'] : null;
            $this->cache = new MdsCache($cache_dir);
        } else {
            $this->cache = $cache;
        }

        $this->config = (object) [
            'app_name' => 'Default App Name', // Application Name
            'app_version' => '0.0.1',            // Application Version
            'app_host' => '', // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / 'Joomla! 2.5.17 VirtueMart 2.0.26d'
            'app_url' => '', // URL your site is hosted on
            'user_email' => 'api@collivery.co.za',
            'user_password' => 'api123',
            'demo' => false,
        ];

        foreach ($config as $key => $value) {
            $this->config->$key = $key === 'user_password' ? $value : trim($value);
        }

        if ($this->config->demo) {
            $this->config->user_email = 'api@collivery.co.za';
            $this->config->user_password = 'api123';
        }
    }

    /**
     * Authenticate and set the token.
     *
     * @return array
     */
    protected function authenticate()
    {
        $authCache = $this->cache->get('collivery.auth');

        if (
            $this->check_cache &&
            $this->cache->has('collivery.auth') &&
            $authCache['email_address'] == $this->config->user_email
        ) {
            $this->default_address_id = $authCache['client']['primary_address']['id'];
            $this->client_id = $authCache['client']['id'];
            $this->user_id = $authCache['id'];
            $this->token = $authCache['api_token'];

            return $authCache;
        } else {
            return $this->makeAuthenticationRequest();
        }
    }

    /**
     * Consumes API
     *
     * @param  string  $url               The URL you're accessing
     * @param  array   $data              The params or query the URL requires.
     * @param  string  $type              ~ Defines how the data is sent (POST / GET)
     * @param  bool    $isAuthenticating  Whether the API requires the api_token
     *
     * @return array $result
     * @throws CurlConnectionException
     */
    private function consumeAPI($url, $data, $type, $isAuthenticating = false) {
        $url = self::BASE_URL . $url;
        if (!$isAuthenticating) {
            $data["api_token"] = $this->token ?: $this->authenticate()['api_token'];
        }

        $client  = curl_init($url);

        if ($type == 'POST') {
            curl_setopt($client, CURLOPT_POST, 1);
            $data = json_encode($data);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        } else if ($type == 'PUT') {
            curl_setopt($client, CURLOPT_CUSTOMREQUEST, 'PUT');
            $data = json_encode($data);
            curl_setopt($client, CURLOPT_POSTFIELDS, $data);
        } else {
            $query = http_build_query($data);
            $client = curl_init($url.'?'.$query);
        }

        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);


        $headerArray = [
            'X-App-Name:'.$this->config->app_name.' mds/collivery/class',
            'X-App-Version:'.$this->config->app_version,
            'X-App-Host:'.$this->config->app_host,
            'X-App-Lang:'.'PHP '.phpversion(),
            'X-App-Url:'.$this->config->app_url,
            'Content-Type: application/json'
        ];

        curl_setopt($client, CURLOPT_HTTPHEADER, $headerArray);

        $result = curl_exec($client);

        if (curl_errno($client)) {
            $errno = curl_errno($client);
            $errmsg = curl_error($client);
            curl_close($client);

            throw new CurlConnectionException('Error executing request', 'ConsumeAPI()', [
                'Code'    => $errno,
                'Message' => $errmsg,
                'URL'     => $url,
                'Result' => $result
            ]);
        }

        if (isset($result['error'])) {
            $error = $result['error'];
            throw new CurlConnectionException('Error executing request', 'ConsumeAPI()', [
                'Code'    => $error['http_code'],
                'Message' => $error['message'],
                'URL'     => $url,
                'Result' => $result
            ]);
        }

        curl_close($client);

        // If $result is already an array.
        if (is_array($result)) {
            return $result;
        }

        return json_decode($result, true);
    }

    /**
     * Make the authentication request.
     *
     * @param null|array $settings
     *
     * @return array
     */
    public function makeAuthenticationRequest($settings = null)
    {
        if ($settings) {
            $user_email = $settings['email'];
            $user_password = $settings['password'];
            $token = null;
        } else {
            $user_email = $this->config->user_email;
            $user_password = $this->config->user_password;
            $token = $this->token;
        }

        try {

            $authenticate = $this->consumeAPI('login', [
                "email" => $user_email,
                "password" => $user_password
            ], 'POST', true);

            $authenticate = $authenticate['data'];

            if (is_array($authenticate) && isset($authenticate['api_token'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.auth', $authenticate, 50);
                }

                if (!$settings && $this->check_cache) {
                    $this->default_address_id = $authenticate['client']['primary_address']['id'];
                    $this->client_id = $authenticate['client']['id'];
                    $this->user_id = $authenticate['id'];
                    $this->token = $authenticate['api_token'];
                }

                return $authenticate;
            } else {
                if (isset($authenticate['error'])) {
                    $this->setError($authenticate['error']['http_code'], $authenticate['error']['message']);
                } else {
                    $this->setError('result_unexpected', 'No result returned.');
                }
            }
        } catch (CurlConnectionException $e) {
            $this->catchException($e);
        }

        return [];
    }

    /**
     * Returns true or false if authenticated by making a new authentication request.
     *
     * @param array $settings
     *
     * @return bool
     */
    public function isNewInstanceAuthenticated(array $settings)
    {
        $check_cache = $this->temporarilyDisableCaching();
        $authenticationRequest = $this->makeAuthenticationRequest($settings);
        $this->check_cache = $check_cache;

        return is_array($authenticationRequest);
    }

    /**
     * Returns the md5 string of the config array.
     *
     * @return string
     */
    public function md5Config()
    {
        return md5(json_encode((array) $this->config));
    }

    /**
     * Returns a list of towns and their ID's for creating new addresses.
     *
     * @param string $country  Filter towns by Country
     *
     * @return array List of towns and their ID's
     * @throws Exception
     */
    public function getTowns( $country = 'ZAF', $province = '')
    {
        $province = trim($province);
        $suffixCleaned = str_replace(' ', '', $province === '' ? $country : $province);
        $cacheName ='collivery.towns.'.$suffixCleaned;
        if (($this->check_cache) && $this->cache->has($cacheName)) {
            return $this->cache->get($cacheName);
        } else {
            try {
                $result = $this->consumeAPI('towns',$province === ''? ["country" => $country, "per_page" => "0"]:["per_page" => "0","province" => $province], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);
                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put($cacheName, $result['data'], 60 * 24); //ToDo set own cache-timeout
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Allows you to search for town and suburb names starting with the given string.
     * The minimum string length to search is two characters.
     * Returns a list of towns, suburbs, and the towns the suburbs belong to with their ID's for creating new addresses.
     * The idea is that this could be used in an auto complete function.
     *
     * @param string $name Start of town/suburb name
     *
     * @return array List of towns and their ID's
     * @throws Exception
     */
    public function searchTowns($name)
    {
        if (strlen($name) < 2) {
            return $this->get_towns();
        } elseif (($this->check_cache) && $this->cache->has('collivery.search_towns.'.$name)) {
            return $this->cache->get('collivery.search_towns.'.$name);
        } else {
            try {
                $result = $this->consumeAPI('towns', ["search" => $name], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return [];
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.search_towns.'.$name, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }


    /**
     * Allows you to search for town and suburb names starting with the given string.
     * The minimum string length to search is three characters.
     * Returns a list of suburbs and the towns the suburbs belong to with their ID's for creating new addresses.
     *
     * @param string $searchText Start of town/suburb name
     *
     * @return array List of suburbs and the towns the suburbs belong to with their ID's for creating new addresses.
     * @throws Exception
     */
    public function searchTownSuburbs($searchText)
    {
        if (strlen($searchText) < 3) {
            $this->setError('invalid_search_text', 'The search text has to have a minimum of 3 characters.');
            return [];
        } elseif (($this->check_cache) && $this->cache->has('collivery.town_suburb_search.'.$searchText)) {
            return $this->cache->get('collivery.town_suburb_search.'.$searchText);
        } else {
            try {
                $result = $this->consumeAPI('town_suburb_search', ["search_text" => $searchText], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return [];
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.`town_suburb_search`.'.$searchText, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns all the suburbs of a town.
     *
     * @param int $townId ID of the Town to return suburbs for
     *
     * @return array
     * @throws Exception
     */
    public function getSuburbs($townId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.suburbs.'.$townId)) {
            return $this->cache->get('collivery.suburbs.'.$townId);
        } else {
            try {
                $result = $this->consumeAPI('suburbs', ["town_id" => $townId], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return [];
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.suburbs.'.$townId, $result['data'], 60 * 24 * 7);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }
    /**
     * Returns a suburb.
     *
     * @param int $suburbId ID of the suburb to return
     *
     * @return array
     * @throws Exception
     */
    public function getSuburb($suburbId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.suburb.'.$suburbId)) {
            return $this->cache->get('collivery.suburb.'.$suburbId);
        } else {
            try {
                $result = $this->consumeAPI('suburbs/'.$suburbId, [], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return [];
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.suburb.'.$suburbId, $result['data'], 60 * 24 * 7);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }
    /**
     * Returns the type of Address Locations.
     * Certain location type incur a surcharge due to time spent during
     * delivery.
     *
     * @return array
     * @throws Exception
     */
    public function getLocationTypes()
    {
        if (($this->check_cache) && $this->cache->has('collivery.location_types')) {
            return $this->cache->get('collivery.location_types');
        } else {
            try {
                $result = $this->consumeAPI('location_types', ["api_token" => ""], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.location_types', $result['data'], 60 * 24 * 7);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the available Collivery services types.
     *
     * @return array
     * @throws Exception
     */
    public function getServices()
    {
        if (($this->check_cache) && $this->cache->has('collivery.services')) {
            $baseServices = $this->cache->get('collivery.services');
        } else {
            try {
                $result = $this->consumeAPI('service_types', ["api_token" => ""], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.services', $result['data'], 60 * 24 * 7);
                }

                $baseServices = $result['data'];
            } else {
                return $this->checkError($result);
            }
        }

        return $this->filterServices($baseServices);
    }

    /**
     * Returns the available Parcel Type ID and value array for use in adding a collivery.
     *
     * @param int $addressId the ID of the address you wish to retrieve
     *
     * @return array Address
     * @throws Exception
     */
    public function getAddress($addressId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.address.'.$this->client_id.'.'.$addressId)) {
            return $this->cache->get('collivery.address.'.$this->client_id.'.'.$addressId);
        } else {
            try {
                $result = $this->consumeAPI('address/'.$addressId, ["api_token" => ""], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.address.'.$this->client_id.'.'.$addressId, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns all the addresses belonging to a client.
     *
     * @param array $filter Filter Addresses
     *
     * @return array
     * @throws Exception
     */
    public function getAddresses($filter = [])
    {
        if (($this->check_cache) && empty($filter) && $this->cache->has('collivery.addresses.'.$this->client_id)) {
            return $this->cache->get('collivery.addresses.'.$this->client_id);
        } else {
            try {
                if (empty($filter)) {
                    $filter= ["per_page" => "0"];
                }
                $result = $this->consumeAPI('address', $filter, 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache && empty($filter)) {
                    $this->cache->put('collivery.addresses.'.$this->client_id, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the Contact people of a given Address ID.
     *
     * @param int $addressId Address ID
     *
     * @return array
     * @throws Exception
     */
    public function getContacts($addressId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.contacts.'.$this->client_id.'.'.$addressId)) {
            return $this->cache->get('collivery.contacts.'.$this->client_id.'.'.$addressId);
        } else {
            try {
                $result = $this->consumeAPI('contacts', ["address_id" => $addressId], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.contacts.'.$this->client_id.'.'.$addressId, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the POD image for a given Waybill Number.
     *
     * @param int $colliveryId Collivery waybill number
     *
     * @return array
     * @throws Exception
     */
    public function getPod($colliveryId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.pod.'.$this->client_id.'.'.$colliveryId)) {
            return $this->cache->get('collivery.pod.'.$this->client_id.'.'.$colliveryId);
        } else {
            try {
                $result = $this->consumeAPI('proofs_of_delivery/', ["waybill_id" => $colliveryId, "per_page" => "0"], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                // This might need to be updated, if the data isn't ordered by Date Ascending.
                $result = array_reverse($result['data']);

                foreach ($result as $document) {
                    if ($document['type'] == "POD") {
                        $result = $this->consumeAPI($document['image_url'], ["api_token" => ""], 'GET');
                        if (isset($result['data'])) {
                            if ($this->check_cache) {
                                $this->cache->put('collivery.pod.'.$this->client_id.'.'.$colliveryId, $result['data'], 60 * 24);
                            }
                            return $result['data'];
                        }
                    }
                }

                return false;
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the Waybill PDF image(base_64 encoded) for a given Waybill Number.
     *
     * @param int $colliveryId Collivery waybill number
     *
     * @return array
     * @throws Exception
     */
    public function getWaybill($colliveryId)
    {
        try {
            $result = $this->consumeAPI('waybill_documents/'.$colliveryId."/waybill", ["api_token" => ""], 'GET');
        } catch (CurlConnectionException $e) {
            $this->catchException($e);

            return false;
        }

        if (isset($result['data'])) {
            return $result['data'];
        } else {
            return $this->checkError($result);
        }
    }

    /**
     * Returns a list of avaibale parcel images for a given Waybill Number.
     *
     * @param int $colliveryId Collivery waybill number
     *
     * @return array
     * @throws Exception
     */
    public function getParcelImageList($colliveryId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.parcel_image_list.'.$this->client_id.'.'.$colliveryId)) {
            return $this->cache->get('collivery.parcel_image_list.'.$this->client_id.'.'.$colliveryId);
        } else {
            try {
                $result = $this->consumeAPI('parcel_images', ["waybill_id" => $colliveryId], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.parcel_image_list.'.$this->client_id.'.'.$colliveryId, $result['data'], 60 * 12);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the image of a given parcel-id of a waybill.
     * If the Waybill number is 54321 and there are 3 parcels, they would
     * be referenced by id's 54321-1, 54321-2 and 54321-3.
     *
     * @param string $parcelId Parcel ID
     *
     * @return array Array containing all the information
     *               about the image including the image
     *               itself in base64
     * @throws Exception
     */
    public function getParcelImage($parcelId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.parcel_image.'.$this->client_id.'.'.$parcelId)) {
            return $this->cache->get('collivery.parcel_image.'.$this->client_id.'.'.$parcelId);
        } else {
            try {
                $result = $this->consumeAPI('parcel_images/'.$parcelId, ["api_token" => ""], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.parcel_image.'.$this->client_id.'.'.$parcelId, $result['data'], 60 * 24);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the status tracking detail of a given Waybill number.
     * If the collivery is still active, the estimated time of delivery
     * will be provided. If delivered, the time and receivers name (if availble)
     * with returned.
     *
     * @param int $colliveryId Collivery ID
     *
     * @return array Collivery Status Information
     * @throws Exception
     */
    public function getStatus($colliveryId)
    {
        try {
            $result = $this->consumeAPI('status_tracking/'.$colliveryId, [], 'GET');
        } catch (CurlConnectionException $e) {
            $this->catchException($e);

            return false;
        }

        if (isset($result['data'])) {
            if ($this->check_cache) {
                $this->cache->put('collivery.status.'.$this->client_id.'.'.$colliveryId, $result['data'], 60 * 12);
            }

            return $result['data'];
        } else {
            return $this->checkError($result);
        }
    }

    /**
     * Create a new Address and Contact.
     *
     * @param array $data Address and Contact Information
     *
     * @return array Address ID and Contact ID
     * @throws Exception
     */
    public function addAddress(array $data)
    {
        $location_types = $this->make_key_value_array($this->getLocationTypes(), 'id', 'name');
        $towns = $this->make_key_value_array($this->getTowns(), 'id', 'name');
        $suburbs = $this->make_key_value_array($this->getSuburbs($data['town_id']), 'id', 'name');

        if (!isset($data['location_type'])) {
            $data['location_type'] = 1;
        } elseif (!isset($location_types[$data['location_type']])) {
            $data['location_type'] = 1;
        }

        if (!isset($data['town_id'])) {
            $this->setError('missing_data', 'town_id not set.');
        } elseif (!isset($towns[$data['town_id']])) {
            $this->setError('invalid_data', 'Invalid town_id.');
        }

        if (!isset($data['suburb_id'])) {
            $this->setError('missing_data', 'suburb_id not set.');
        } elseif (!isset($suburbs[$data['suburb_id']])) {
            $this->setError('invalid_data', 'Invalid suburb_id.');
        }

        if (!isset($data['street'])) {
            $this->setError('missing_data', 'street not set.');
        }

        if (!isset($data['contact']['full_name'])) {
            $this->setError('missing_data', 'full_name not set.');
        }

        if (!isset($data['contact']['work_phone']) and !isset($data['contact']['cellphone'])) {
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');
        }

        if (isset($data["custom_id"]) && ctype_digit($data["custom_id"])) {
            $data["custom_id"] = $data["custom_id"] == 0 || $data["custom_id"] == "0" ? null : $data["custom_id"];
        }

        if (!$this->hasErrors()) {
            try {
                $result = $this->consumeAPI('address', $data, 'POST');
                $this->cache->forget('collivery.addresses.'.$this->client_id);
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data']['id'])) {
                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Add's a contact person for a given Address ID.
     *
     * @param array $data New Contact Data
     *
     * @return int New Contact ID
     * @throws Exception
     */
    public function addContact(array $data)
    {
        if (!isset($data['address_id'])) {
            $this->setError('missing_data', 'address_id not set.');
        } elseif (!is_array($this->getAddress($data['address_id']))) {
            $this->setError('invalid_data', 'Invalid address_id.');
        }

        if (!isset($data['full_name'])) {
            $this->setError('missing_data', 'full_name not set.');
        }

        if (!isset($data['phone']) and !isset($data['cellphone'])) {
            $this->setError('missing_data', 'Please supply ether a phone or cellphone number...');
        }

        if (!isset($data['email'])) {
            $this->setError('missing_data', 'email not set.');
        }

        if (!$this->hasErrors()) {
            try {
                $result = $this->consumeAPI('contacts', $data, 'POST');
                $this->cache->forget('collivery.addresses.'.$this->client_id);
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                return $result;
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Returns the price based on the data provided.
     *
     * @param array $data Your Collivery Details
     *
     * @return array Pricing for details supplied
     * @throws Exception
     */
    public function getPrice(array $data)
    {
        // HERE WE GO
        $towns = $this->make_key_value_array($this->getTowns(), 'id', 'name');

        if (!isset($data['collection_town']) && !isset($data['collection_address'])) {
            $this->setError('missing_data', 'collection_town/collection_address not set.');
        } elseif (isset($data['collection_address']) && !is_array($this->getAddress($data['collection_address']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: collection_address.');
        } elseif (isset($data['collection_town']) && !isset($towns[$data['collection_town']])) {
            $this->setError('invalid_data', 'Invalid Town ID for: collection_town.');
        }

        if (!isset($data['delivery_address']) && !isset($data['delivery_town'])) {
            $this->setError('missing_data', 'delivery_address/delivery_town not set.');
        } elseif (isset($data['delivery_address']) && !is_array($this->getAddress($data['delivery_address']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: delivery_address.');
        } elseif (isset($data['delivery_town']) && !isset($towns[$data['delivery_town']])) {
            $this->setError('invalid_data', 'Invalid Town ID for: delivery_town.');
        }

        if (!isset($data['services'])) {
            $this->setError('missing_data', 'service not set.');
        }

        if ($this->hasErrors()) {
            return false;
        }

        try {
            $result = $this->consumeAPI('quote', $data, 'POST');
        } catch (CurlConnectionException $e) {
            $this->catchException($e);
            return false;
        }

        if (isset($result['data'])) {
            return $result;
        } else {
            return $this->checkError($result);
        }
    }

    /**
     * Creates a new Collivery based on the data array provided.
     * The array should first be validated before passing to this function.
     * The Waybill No is return apon successful creation of the collivery.
     *
     * @param array $data Properties of the new Collivery
     *
     * @return int New Collivery ID
     * @throws Exception
     */
    public function addCollivery(array $data)
    {
        $contacts_from = $this->make_key_value_array($this->getContacts($data['collivery_from']), 'id', '', true);
        $contacts_to = $this->make_key_value_array($this->getContacts($data['collivery_to']), 'id', '', true);
        $services = $this->make_key_value_array($this->getServices(), 'id', 'text');

        if (!isset($data['collivery_from'])) {
            $this->setError('missing_data', 'collivery_from not set.');
        } elseif (!is_array($this->getAddress($data['collivery_from']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_from.');
        }

        if (!isset($data['contact_from'])) {
            $this->setError('missing_data', 'contact_from not set.');
        } elseif (!isset($contacts_from[$data['contact_from']])) {
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_from.');
        }

        if (!isset($data['collivery_to'])) {
            $this->setError('missing_data', 'collivery_to not set.');
        } elseif (!is_array($this->getAddress($data['collivery_to']))) {
            $this->setError('invalid_data', 'Invalid Address ID for: collivery_to.');
        }

        if (!isset($data['contact_to'])) {
            $this->setError('missing_data', 'contact_to not set.');
        } elseif (!isset($contacts_to[$data['contact_to']])) {
            $this->setError('invalid_data', 'Invalid Contact ID for: contact_to.');
        }

        if (!isset($data['service'])) {
            $this->setError('missing_data', 'service not set.');
        } elseif (!isset($services[$data['service']])) {
            $this->setError('invalid_data', 'Invalid service.');
        }

        if (!$this->hasErrors()) {
            $newObject = [
                "service" => $data["service"],
                "parcels" => $data["parcels"],
                "collection_address" => $data["collivery_from"],
                "collection_contact" => $data["contact_from"],
                "delivery_address" => $data["collivery_to"],
                "delivery_contact" => $data["contact_to"],
                "collection_time" => $data["collection_time"],
                "exclude_weekend" => true,
                "risk_cover" => $data["cover"],
                "special_instructions" => $data["instructions"],
                "reference" => $data["cust_ref"]
            ];

            try {
                $result = $this->consumeAPI('waybill', $newObject, 'POST');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data']['id'])) {
                return $result;
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Accepts the newly created Collivery, moving it from Waiting Client Acceptance
     * to Accepted so that it can be processed.
     *
     * @param int $colliveryId ID of the Collivery you wish to accept
     *
     * @return bool Has the Collivery been accepted
     * @throws Exception
     */
    public function acceptCollivery($colliveryId)
    {
        try {
            $result = $this->consumeAPI('status_tracking/'.$colliveryId, ["status_id" => 3], 'PUT');
        } catch (CurlConnectionException $e) {
            $this->catchException($e);

            return false;
        }

        if (isset($result['data'])) {
            if (strpos($result['data']['message'], 'accepted')) {
                return true;
            } else {
                return false;
            }
        } else {
            return $this->checkError($result);
        }
    }

    /**
     * Returns the waybill object
     *
     * @param int $colliveryId Collivery waybill number
     *
     * @return array
     * @throws Exception
     *
     */
    public function getCollivery(int $colliveryId)
    {
        if (($this->check_cache) && $this->cache->has('collivery.waybill.'.$this->client_id.'.'.$colliveryId)) {
            return $this->cache->get('collivery.waybill.'.$this->client_id.'.'.$colliveryId);
        } else {
            try {
                $result = $this->consumeAPI('waybill/'.$colliveryId, ["api_token" => ""], 'GET');
            } catch (CurlConnectionException $e) {
                $this->catchException($e);

                return false;
            }

            if (isset($result['data'])) {
                if ($this->check_cache) {
                    $this->cache->put('collivery.waybill.'.$this->client_id.'.'.$colliveryId, $result['data'], 60 * 12);
                }

                return $result['data'];
            } else {
                return $this->checkError($result);
            }
        }
    }

    /**
     * Handle error messages in Exception.
     *
     * @param Exception $e Exception Object
     */
    protected function catchException($e)
    {
        $this->setError($e->getCode(), $e->getMessage());
    }

    /**
     * The Else in nearly every function found here, will set an error and return false
     *
     * @param Array $data - The Result that was returned by the API
     *
     * @return Boolean false
     */
    private function checkError($data) {
        if (isset($data['error'])) {
            $this->setError($data['error']['http_code'], $data['error']['message']);
        } else {
            $this->setError('result_unexpected', 'No result returned.');
        }

        return false;
    }

    /**
     * Add a new error.
     *
     * @param string $id   Error ID
     * @param string $text Error text
     */
    public function setError($id, $text)
    {
        $this->errors[$id] = $text;
    }

    /**
     * Retrieve errors.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if this instance has an error.
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Clears all the Errors.
     */
    public function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Disable Cached completely and retrieve data directly from the webservice.
     */
    public function disableCache()
    {
        $this->check_cache = false;
    }

    /**
     * Check if cache exists before querying the webservice
     * If webservice was queried, save returned data to Cache.
     */
    public function enableCache()
    {
        $this->check_cache = true;
    }

    /**
     * Temporarily Disables Caching and returns the old cache setting.
     *
     * @return int
     */
    protected function temporarilyDisableCaching()
    {
        $currentCacheSetting = $this->check_cache;
        $this->disableCache();

        return $currentCacheSetting;
    }

    /**
     * Returns the clients default address.
     *
     * @return int Address ID
     */
    public function getDefaultAddressId()
    {
        if (!$this->default_address_id) {
            $this->authenticate();
        }
        // This is the default address id for the test account.
        return $this->default_address_id;
    }

    /**
     * Returns the current users username and password.
     *
     * @return array
     */
    public function getCurrentUsernameAndPassword()
    {
        return [
            'email' => $this->config->user_email,
            'password' => $this->config->user_password,
        ];
    }

    /**
     * @param Array $data - Contains the array you want to modify
     * @param string $key - This is the name of the Id field
     * @param string $value - This is the name of the Value field
     * @param boolean $isContact - The contact array has a lot of text as it's value that isn't inherently known.
     *
     * @return Array $key_value_array - {key:value, key:value} - Used for setting up dropdown lists.
     */
    public function make_key_value_array($data, $key, $value, $isContact = false) {
        $key_value_array = [];

        if (!is_array($data)) {
            return [];
        }

        if ($isContact) {
            foreach ($data as $item) {
                $key_value_array[$item[$key]] = $item['full_name'].", ".$item['cell_no'].", ".$item['work_no'].", ".$item['email'];
            }
        } else {
            foreach ($data as $item) {
                $key_value_array[$item[$key]] = $item[$value];
            }
        }



        return $key_value_array;
    }

    /**
     * Returns the Collivery User Id for the credentials used by the store owner.
     *
     * @return Integer - The User Id;
     */
    public function getColliveryUserId() {
        return $this->authenticate()['id'];
    }

    public function filterServices(array $services)
    {
        // Remove SDX
        $services = array_filter($services, function ($service) {
            return ((array) $service)['id'] !== self::SDX;
        });

        // Each $service could be an array or object.
        // Cast it coming in and going out
        $type = gettype(current($services));

        $before10 = [
            'id' => self::ONX_10,
            'code' => 'ONX_10',
            'text' => 'Next Day Before 10:00',
            'description' => 'Will be delivered next day before 10:00',
            'delivery_days' => 1,
        ];
        // Cast it back
        settype($before10, $type);

        return array_merge([$before10], $services);
    }
}
