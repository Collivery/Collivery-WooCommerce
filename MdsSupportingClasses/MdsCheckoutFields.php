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
    private function make_key_value_array($data, $key, $value) {
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

        if (!$service->isEnabled()) {
            if (isset($this->defaultFields[$prefix])) {
                return $this->defaultFields[$prefix];
            } else {
                return $this->defaultFields;
            }
        }

        try {
            $resources = MdsFields::getResources($service);

            if ($prefix) {
                $prefix = $prefix.'_';
            }

            $towns = $this->make_key_value_array($resources['towns'], 'name', 'name');
            $location_types = $this->make_key_value_array($resources['location_types'], 'id', 'name');

            $towns = ['' => 'Select Town'] + $towns;
            $location_types = ['' => 'Select Premises Type'] + $location_types;
	        $customer = WC()->customer;
	        $cityPrefix = $prefix ? $prefix : 'billing_';
            
            $townName = '';

            if ($customer) {
	            $townName = $customer->{"get_{$cityPrefix}city"}();
	        }

	        $suburbs = ['' => 'First select town/city'];

	        if ($townName) {
                $array_search_towns = $this->make_key_value_array($resources['towns'], 'id', 'name');
		        $townId = array_search($townName, $array_search_towns);
                $suburbs = $service->returnColliveryClass()->getSuburbs($townId);
                $suburbs = $this->make_key_value_array($suburbs, 'id', 'name');
                $suburbs = ['' => 'First select town/city'] + $suburbs;
            }

            return [
                $prefix.'country' => [
                    'priority' => 1,
                    'type' => 'country',
                    'label' => 'Country',
                    'required' => true,
                    'autocomplete' => 'country',
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                ],
                $prefix.'state' => [
                    'priority' => 2,
                    'type' => 'state',
                    'label' => 'Province',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change', 'active'],
                    'placeholder' => 'Please select',
                    'validate' => ['state'],
                    'autocomplete' => 'address-level1',
                ],
                $prefix.'city' => [
                    'priority' => 4,
                    'type' => 'select',
                    'label' => 'Town / City',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'options' => $towns,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change', 'active'],
                ],
                $prefix."city_int"=> [
                    "label"=> "Town / City",
                    "required"=> true,
                    "class"=> ["form-row-wide", "address-field", 'update_totals_on_change', "international", "inactive"],
                    "autocomplete"=> "address-level2",
                    "priority"=> 5
                ],
                $prefix.'suburb' => [
                    'priority' => 6,
                    'type' => 'select',
                    'label' => 'Suburb',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'class' => ['form-row-wide', 'address-field', 'active'],
                    'options' => $suburbs,
                ],
                $prefix.'location_type' => [
                    'priority' => 7,
                    'type' => 'select',
                    'label' => 'Location Type',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                    'placeholder' => 'Please select',
                    'options' => $location_types,
                    'default' => 'Private House',
                    'selected' => '',
                ],
                $prefix.'company' => [
                    'priority' => 8,
                    'label' => 'Company Name',
                    'placeholder' => 'Company (optional)',
                    'autocomplete' => 'organization',
                    'class' => ['form-row-wide'],
                ],
                $prefix.'address_1' => [
                    'priority' => 9,
                    'label' => 'Street',
                    'placeholder' => 'Street number and name.',
                    'autocomplete' => 'address-line1',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix.'address_2' => [
                    'priority' => 10,
                    'label' => 'Building Details',
                    'placeholder' => 'Apartment, suite, unit etc. (optional)',
                    'class' => ['form-row-wide'],
                    'autocomplete' => 'address-line2',
                    'required' => false,
                ],
                $prefix.'first_name' => [
                    'priority' => 11,
                    'label' => 'First Name',
                    'placeholder' => 'First Name',
                    'autocomplete' => 'given-name',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix.'last_name' => [
                    'priority' => 12,
                    'label' => 'Last Name',
                    'placeholder' => 'Last Name',
                    'autocomplete' => 'family-name',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix.'phone' => [
                    'priority' => 13,
                    'validate' => ['phone'],
                    'label' => 'Cell Phone',
                    'placeholder' => 'Phone number',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix.'email' => [
                    'priority' => 14,
                    'validate' => ['email'],
                    'label' => 'Email Address',
                    'placeholder' => 'you@yourdomain.co.za',
                    'required' => true,
                    'class' => ['form-row-wide'],
                ],
                $prefix.'postcode' => [
                    'priority' => 15,
                    'label' => 'Postal Code',
                    'placeholder' => 'Postal Code',
                    'required' => false,
                    'class' => ['form-row-wide', 'address-field', 'update_totals_on_change'],
                ],
            ];
        } catch (InvalidResourceDataException $e) {
            return $prefix ? $this->defaultFields[$prefix] : $this->defaultFields;
        }
    }
}
