<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;

class MdsCheckoutFields
{
    /**
     * @var array
     */
    protected $defaultFields = array();

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

        if ($prefix) {
            $prefix = $prefix.'_';
        }

        try {
            $resources = MdsFields::getResources($service);
            $towns = array('' => 'Select Town') + array_combine($resources['towns'], $resources['towns']);
            $location_types = array('' => 'Select Premises Type') + array_combine($resources['location_types'], $resources['location_types']);
	        $customer = WC()->customer;
	        $cityPrefix = $prefix ? $prefix : 'billing_';
	        $townName = $customer->{"get_{$cityPrefix}city"}();
	        $suburbs = array('' => 'First select town/city');

	        if ($townName) {
		        $townId = array_search($townName, $resources['towns']);
		        $suburbs = array_merge($suburbs, $service->returnColliveryClass()->getSuburbs($townId));
	        }

            return array(
                $prefix.'country' => array(
                    'priority' => 1,
                    'type' => 'country',
                    'label' => 'Country',
                    'required' => true,
                    'autocomplete' => 'country',
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                ),
                $prefix.'state' => array(
                    'priority' => 2,
                    'type' => 'state',
                    'label' => 'Province',
                    'required' => true,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                    'placeholder' => 'Please select',
                    'validate' => array('state'),
                    'autocomplete' => 'address-level1',
                ),
                $prefix.'city' => array(
                    'priority' => 3,
                    'type' => 'select',
                    'label' => 'Town / City',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'options' => $towns,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                ),
                $prefix.'suburb' => array(
                    'priority' => 4,
                    'type' => 'select',
                    'label' => 'Suburb',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'class' => array('form-row-wide', 'address-field'),
                    'options' => $suburbs,
                ),
                $prefix.'location_type' => array(
                    'priority' => 5,
                    'type' => 'select',
                    'label' => 'Location Type',
                    'required' => true,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                    'placeholder' => 'Please select',
                    'options' => $location_types,
                    'default' => 'Private House',
                    'selected' => '',
                ),
                $prefix.'company' => array(
                    'priority' => 6,
                    'label' => 'Company Name',
                    'placeholder' => 'Company (optional)',
                    'autocomplete' => 'organization',
                    'class' => array('form-row-wide'),
                ),
                $prefix.'address_1' => array(
                    'priority' => 7,
                    'label' => 'Street',
                    'placeholder' => 'Street number and name.',
                    'autocomplete' => 'address-line1',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'address_2' => array(
                    'priority' => 8,
                    'label' => 'Building Details',
                    'placeholder' => 'Apartment, suite, unit etc. (optional)',
                    'class' => array('form-row-wide'),
                    'autocomplete' => 'address-line2',
                    'required' => false,
                ),
                $prefix.'postcode' => array(
                    'priority' => 9,
                    'label' => 'Postal Code',
                    'placeholder' => 'Postal Code',
                    'required' => false,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'first_name' => array(
                    'priority' => 10,
                    'label' => 'First Name',
                    'placeholder' => 'First Name',
                    'autocomplete' => 'given-name',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'last_name' => array(
                    'priority' => 11,
                    'label' => 'Last Name',
                    'placeholder' => 'Last Name',
                    'autocomplete' => 'family-name',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'phone' => array(
                    'priority' => 12,
                    'validate' => array('phone'),
                    'label' => 'Cell Phone',
                    'placeholder' => 'Phone number',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'email' => array(
                    'priority' => 13,
                    'validate' => array('email'),
                    'label' => 'Email Address',
                    'placeholder' => 'you@yourdomain.co.za',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'postcode' => array(
                    'priority' => 14,
                    'label' => 'Postal Code',
                    'placeholder' => 'Postal Code',
                    'required' => false,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                ),
            );
        } catch (InvalidResourceDataException $e) {
            return $this->defaultFields;
        }
    }
}
