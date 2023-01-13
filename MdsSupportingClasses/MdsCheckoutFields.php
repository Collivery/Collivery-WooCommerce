<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;

class MdsCheckoutFields
{
    /**
     * @var array
     */
    protected $defaultFields = [];

    /**
     * CheckoutFields constructor.
     *
     * @param array $defaultFields
     */
    public function __construct(array $defaultFields)
    {
        $this->defaultFields = $defaultFields;
    }

    /**
     * @param Array $data - Contains the array you want to modify
     * @param string $key - This is the name of the Id field
     * @param string $value - This is the name of the Value field
     *
     * @return Array $key_value_array - {key:value, key:value} - Used for setting up dropdown lists.
     */
    private function make_key_value_array($data, $key, $value)
    {
        $key_value_array = [];

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $item) {
            $key_value_array[$item[$key]] = $item[$value];
        }

        return $key_value_array;
    }

    /**
     * @param string|null $prefix
     *
     * @return array
     */
    public function getCheckoutFields($prefix = null)
    {
        $service = MdsColliveryService::getInstance();
        $defaultFields = $this->defaultFields[$prefix] ?? $this->defaultFields;

        if (!$service->isEnabled()) {
            return $defaultFields;
        }

        try {
            $resources = MdsFields::getResources($service);

            if ($prefix) {
                $prefix .= '_';
            }

            $towns = [];
            if (!$service->isTownsSuburbsSearchEnabled()) {
                $towns = $this->make_key_value_array($resources['towns'], 'id', 'name');
            }
            $location_types = $this->make_key_value_array($resources['location_types'], 'id', 'name');

            $towns = ['' => 'Select Town'] + $towns;
            $location_types = ['' => 'Select Premises Type'] + $location_types;
            $customer = WC()->customer;
            $cityPrefix = $prefix ?: 'billing_';


            $suburbs = ['' => 'First select town/city'];
            $customerDetails = $savedLocationTypeId = '';
            if(!is_null($customer)) {
                $customerDetails =  $customer->get_meta("{$cityPrefix}suburb");
                $savedLocationTypeId = $customer->get_meta("{$cityPrefix}location_type");
            }
            $savedSuburbId  = $mdsSuburb = $mdsSuburbId = $mdsSuburbName = $mdsTown = $mdsTownId = $mdsTownName = $customerDetails;


            if(is_numeric($savedSuburbId)) {
                $mdsSuburb =  (object) $service->returnColliveryClass()->getSuburb($savedSuburbId);
                $mdsSuburbId = $mdsSuburb->id;
                $mdsSuburbName = $mdsSuburb->name;
                $mdsTown = (object) $mdsSuburb->town;
                $mdsTownId = $mdsTown->id;
                $mdsTownName = $mdsTown->name;
                $suburbs = $service->returnColliveryClass()->getSuburbs($mdsTownId);
                $suburbs = $this->make_key_value_array($suburbs, 'id', 'name');
            }

            $fields = [
                $prefix . 'country' => [
                    'priority' => 1,
                    'type' => 'country',
                    'label' => 'Country',
                    'required' => true,
                    'autocomplete' => 'country',
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                ],
                $prefix . 'location_type' => [
                    'priority' => 9,
                    'type' => 'select',
                    'label' => 'Location Type',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                    'placeholder' => 'Please select',
                    'options' => $location_types,
                    'default' => 'Private House',
                    'selected' => $savedLocationTypeId,
                ],
                $prefix . 'company' => [
                    'priority' => 10,
                    'label' => 'Company Name',
                    'placeholder' => 'Company (optional)',
                    'autocomplete' => 'organization',
                    'maxlength' => 50,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'address_1' => [
                    'priority' => 11,
                    'label' => 'Street',
                    'placeholder' => 'Street number and name.',
                    'autocomplete' => 'address-line1',
                    'maxlength' => 50,
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'address_2' => [
                    'priority' => 12,
                    'label' => 'Building Details',
                    'placeholder' => 'Apartment, suite, unit etc. (optional)',
                    'class' => ['form-row-wide'],
                    'autocomplete' => 'address-line2',
                    'maxlength' => 50,
                    'required' => false,
                ],
                $prefix . 'first_name' => [
                    'priority' => 13,
                    'label' => 'First Name',
                    'placeholder' => 'First Name',
                    'autocomplete' => 'given-name',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'last_name' => [
                    'priority' => 14,
                    'label' => 'Last Name',
                    'placeholder' => 'Last Name',
                    'autocomplete' => 'family-name',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'phone' => [
                    'priority' => 15,
                    'validate' => ['phone'],
                    'label' => 'Cell Phone',
                    'placeholder' => 'Phone number',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'email' => [
                    'priority' => 16,
                    'validate' => ['email'],
                    'label' => 'Email Address',
                    'placeholder' => 'you@yourdomain.co.za',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix . 'postcode' => [
                    'priority' => 17,
                    'label' => 'Postal Code',
                    'placeholder' => 'Postal Code',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                    'validate' => ['postcode'],
                    'autocomplete' => 'postal-code',
                ],
                $prefix . 'state' => [
                    'priority' => 2,
                    'type' => 'state',
                    'label' => 'Province',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change', 'active'],
                    'placeholder' => 'Please select',
                    'validate' => ['state'],
                    'autocomplete' => 'address-level1',
                ]
            ];
            if ($service->isTownsSuburbsSearchEnabled()) {
                $key_value_array = [
                    $prefix . 'town_city_search' => [
                        'priority' => 4,
                        'type' => 'select',
                        'label' => 'Town / City Search',
                        'required' => true,
                        'placeholder' => 'Please select',
                        'options' => $towns,
                        'class' => ['form-row-wide', 'address-field', 'update_totals_on_change', 'active'],
                        'selected' => $mdsTownId,
                    ],
                    $prefix . 'city' => [
                        'priority' => 6,
                        'type' => 'hidden',
                        'class' => ['update_totals_on_change'],
                        'value' => $mdsTownId,
                    ],
                    $prefix . "city_int" => [
                        "label" => "Town / City",
                        "required" => true,
                        "class" => ["form-row-wide", "address-field", 'update_totals_on_change', "international", "inactive"],
                        "autocomplete" => "address-level2",
                        "priority" => 7,
                        'value' => $mdsTownId,
                    ],
                    $prefix . 'suburb' => [
                        'priority' => 8,
                        'type' => 'hidden',
                        'value' => $mdsSuburbId,
                    ]
                ];
                $fields = array_merge($fields, $key_value_array);
            } else {
                $other_fields = [
                    $prefix . 'city' => [
                        'priority' => 6,
                        'type' => 'select',
                        'label' => 'Town / City',
                        'required' => true,
                        'placeholder' => 'Please select',
                        'options' => $towns,
                        'class' => ['form-row-wide', 'address-field', 'update_totals_on_change', 'active'],
                        'selected' => $mdsTownId,
                    ],
                    $prefix . "city_int" => [
                        "label" => "Town / City",
                        "required" => true,
                        "class" => ["form-row-wide", "address-field", 'update_totals_on_change', "international", "inactive"],
                        "autocomplete" => "address-level2",
                        "priority" => 7,
                        'value' => $mdsTownId,
                    ],
                    $prefix . 'suburb' => [
                        'priority' => 8,
                        'type' => 'select',
                        'label' => 'Suburb',
                        'required' => true,
                        'placeholder' => 'Please select',
                        'class' => ['form-row-wide', 'address-field', 'active'],
                        'options' => $suburbs,
                        'selected' => $mdsSuburbId,
                    ]];
                $fields = array_merge($fields, $other_fields);
            }
            // Ensure we don't steamroll fields added by other plugins
            $customFields = array_diff_key($defaultFields, $fields);

            return array_merge($customFields, $fields);
        } catch (InvalidResourceDataException $e) {
            return $prefix ? $this->defaultFields[$prefix] : $this->defaultFields;
        }
    }
}
