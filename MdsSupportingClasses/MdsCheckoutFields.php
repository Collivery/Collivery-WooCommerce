<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;

class MdsCheckoutFields extends MdsFields
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
            $resources = self::getResources();
            $towns = array('' => 'Select Town') + array_combine($resources['towns'], $resources['towns']);
            $location_types = array('' => 'Select Premises Type') + array_combine($resources['location_types'], $resources['location_types']);

            return array(
                $prefix.'country' => array(
                    'type' => 'country',
                    'label' => 'Country',
                    'required' => true,
                    'autocomplete' => 'country',
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                ),
                $prefix.'state' => array(
                    'type' => 'state',
                    'label' => 'Province',
                    'required' => true,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                    'placeholder' => 'Please select',
                    'validate' => array('state'),
                    'autocomplete' => 'address-level1',
                ),
                $prefix.'city' => array(
                    'type' => 'select',
                    'label' => 'Town / City',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'options' => $towns,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                ),
                $prefix.'suburb' => array(
                    'type' => 'select',
                    'label' => 'Suburb',
                    'required' => true,
                    'placeholder' => 'Please select',
                    'class' => array('form-row-wide', 'address-field'),
                    'options' => array('First select town/city'),
                ),
                $prefix.'location_type' => array(
                    'type' => 'select',
                    'label' => 'Location Type',
                    'required' => true,
                    'class' => array('form-row-wide', 'address-field', 'update_totals_on_change'),
                    'placeholder' => 'Please select',
                    'options' => $location_types,
                    'selected' => '',
                ),
                $prefix.'company' => array(
                    'label' => 'Company Name',
                    'placeholder' => 'Company (optional)',
                    'autocomplete' => 'organization',
                    'class' => array('form-row-wide'),
                ),
                $prefix.'address_1' => array(
                    'label' => 'Street',
                    'placeholder' => 'Street number and name.',
                    'autocomplete' => 'address-line1',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'address_2' => array(
                    'label' => 'Building Details',
                    'placeholder' => 'Apartment, suite, unit etc. (optional)',
                    'class' => array('form-row-wide'),
                    'autocomplete' => 'address-line2',
                    'required' => false,
                ),
                $prefix.'postcode' => array(
                    'label' => 'Postal Code',
                    'placeholder' => 'Postal Code',
                    'required' => false,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'first_name' => array(
                    'label' => 'First Name',
                    'placeholder' => 'First Name',
                    'autocomplete' => 'given-name',
                    'required' => true,
                    'class' => array('form-row-first'),
                ),
                $prefix.'last_name' => array(
                    'label' => 'Last Name',
                    'placeholder' => 'Last Name',
                    'autocomplete' => 'family-name',
                    'required' => true,
                    'class' => array('form-row-last'),
                ),
                $prefix.'phone' => array(
                    'validate' => array('phone'),
                    'label' => 'Cell Phone',
                    'placeholder' => 'Phone number',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
                $prefix.'email' => array(
                    'validate' => array('email'),
                    'label' => 'Email Address',
                    'placeholder' => 'you@yourdomain.co.za',
                    'required' => true,
                    'class' => array('form-row-wide'),
                ),
            );
        } catch (InvalidResourceDataException $e) {
            return $this->defaultFields;
        }
    }
}
