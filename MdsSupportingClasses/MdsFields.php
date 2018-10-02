<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;
use MdsExceptions\SoapConnectionException;

class MdsFields
{
    public static function getFields()
    {
        return array(
            'downloadLogs' => array(
                'title' => __('Clear Cache/Download Error Logs?'),
                'type' => 'text',
                'description' => __(
                    'If you have any errors with the MDS plugin, you can download log files and email them to integration@collivery.co.za for support, clearing cache can be useful if you have empty list of towns etc'
                ),
                'placeholder' => admin_url().'admin.php?page=mds_download_log_files',
            ),
            'enabled' => array(
                'title' => __('Enabled?'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method'),
                'default' => 'yes',
            ),
            'mds_user' => array(
                'title' => 'MDS '.__('Username'),
                'type' => 'text',
                'description' => __('Email address associated with your MDS account.'),
                'default' => 'api@collivery.co.za',
            ),
            'mds_pass' => array(
                'title' => 'MDS '.__('Password'),
                'type' => 'text',
                'description' => __('The password used when logging in to MDS.'),
                'default' => 'api123',
            ),
            'include_product_titles' => array(
                'title' => __('Include product titles'),
                'type' => 'checkbox',
                'description' => __('Includes product titles in the delivery instructions, max 4096 characters'),
                'default' => 'no',
            ),
            'risk_cover' => array(
                'title' => 'MDS '.__('Risk Cover'),
                'type' => 'checkbox',
                'description' => __('Risk cover, up to a maximum of R10 000.'),
                'default' => 'no',
            ),
            'risk_cover_threshold' => array(
                'title' => __('Risk cover minimum'),
                'type' => 'decimal',
                'description' => __('The minimum price of cart items to enable').' MDS '.__(
                        'risk cover, only active when risk cover above is checked<br><strong>Please read the <a href="https://collivery.net/terms" target="_blank">terms and conditions</a> on our website</strong>'
                    ),
                'default' => 1000.00,
            ),
            'round' => array(
                'title' => 'MDS '.__('Round Price'),
                'type' => 'checkbox',
                'description' => __('Rounds price up.'),
                'default' => 'yes',
            ),
            'include_vat' => array(
                'title' => 'MDS '.__('Use Inclusive Amount'),
                'type' => 'checkbox',
                'description' => __('If Woocommerce is setup to add VAT onto the shipping cost then you should uncheck this box to use the exclusive amount, this way VAT will only be applied once. If your not adding VAT onto the shipping cost using Woocommerce then always use the inclusive amount. This option only affects the price displayed on your checkout page, MDS Collivery will always bill you the inclusive amount.'),
                'default' => 'yes',
            ),
            'method_free' => array(
                'title' => __('Free Delivery mode'),
                'label' => __('Free Delivery: Enabled'),
                'type' => 'select',
                'default' => 'no',
                'options' => array(
                    'no' => __('No free deliveries'),
                    'yes' => __('Free delivery'),
                    'discount' => _('Discount on deliveries'),
                ),
                'custom_attributes' => array(
                    'title' => 'Choose shipping mode',
                ),
            ),
            'shipping_discount_percentage' => array(
                'title' => __('Percentage discount for shipping'),
                'type' => 'number',
                'description' => __(
                    'The percentage discount that users get when their cart total exceeds <strong>"Free Delivery Min Total"</strong>'
                ),
                'default' => 10,
                'custom_attributes' => array(
                    'min' => 2,
                    'max' => 100,
                    'step' => 0.1,
                ),
            ),
            'toggle_automatic_mds_processing' => array(
                'title' => __('Automatic MDS Processing'),
                'type' => 'checkbox',
                'description' => __(
                    'When enabled deliveries for an order will be automatically processed. Please refer to the manual for detailed information on implications on using this <a target="_blank" href="http://collivery.github.io/Collivery-WooCommerce/">Manual</a>'
                ),
                'default' => 'no',
            ),
            'auto_accept' => array(
                'title' => __('Auto accept'),
                'type' => 'checkbox',
                'description' => __(
                    'After automatic mds processing has sent the request through to MDS, should the request be auto accepted or will you manually accept the delivery on the MDS website'
                ),
                'default' => 'yes',
            ),
            'wording_free' => array(
                'title' => __('Free Delivery Wording'),
                'type' => 'text',
                'default' => 'Free Delivery',
                'custom_attributes' => array(
                    'data-type' => 'free-delivery-item',
                ),
            ),
            'free_min_total' => array(
                'title' => __('Free Delivery Min Total'),
                'type' => 'number',
                'description' => __('Min order total before free delivery is included, amount is including vat.'),
                'default' => '1000.00',
                'custom_attributes' => array(
                    'step' => .1,
                    'min' => 0,
                ),
            ),
            'free_delivery_blacklist' => array(
                'title' => __('Exclude these roles from free delivery'),
                'type' => 'text',
                'description' => __('Comma separated list of roles that must be excluded from free shipping'),
            ),
            'free_local_only' => array(
                'title' => __('Free Delivery Local Only'),
                'type' => 'checkbox',
                'description' => __('Only allow free delivery for local deliveries only. '),
                'default' => 'no',
                'custom_attributes' => array(
                    'data-type' => 'free-delivery-item',
                ),
            ),
        );
    }

    /**
     * @param MdsColliveryService $service
     *
     * @return array
     */
    public static function instanceFields(MdsColliveryService $service)
    {
        $fields = array();

        try {
            $resources = self::getResources($service);
            foreach ($resources['services'] as $id => $title) {
                $fields['method_'.$id] = array(
                    'title' => __($title),
                    'label' => __($title.': Enabled'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                );
                $fields['fixed_price_'.$id] = array(
                    'title' => __($title.' Fixed Price Amount'),
                    'type' => 'number',
                    'description' => 'Amount greater than 0 enables it, markup is then ignored. This will override any free or discounted shipping',
                    'default' => '0',
                    'custom_attributes' => array(
                        'step' => 'any',
                        'min' => '0',
                    ),
                );
                $fields['markup_'.$id] = array(
                    'title' => __($title.' Markup'),
                    'type' => 'number',
                    'default' => '10',
                    'description' => 'Percentage markup you would like to apply to MDS\'s Price',
                    'custom_attributes' => array(
                        'step' => 'any',
                        'min' => '0',
                    ),
                );
                $fields['wording_'.$id] = array(
                    'title' => __($title.' Wording'),
                    'type' => 'text',
                    'default' => $title,
                    'description' => 'The wording you would like on the checkout page for this service',
                    'class' => 'sectionEnd',
                );
            }

            $fields['free_default_service'] = array(
                'title' => __('Free Delivery Default Service'),
                'type' => 'select',
                'options' => $resources['services'],
                'default' => 5,
                'description' => __('When free delivery is enabled, which default service do we use.'),
                'custom_attributes' => array(
                    'data-type' => 'free-delivery-item',
                ),
            );
            $fields['free_local_default_service'] = array(
                'title' => __('Free Delivery Local Only Default Service'),
                'type' => 'select',
                'options' => $resources['services'],
                'default' => 2,
                'description' => __('When free local only delivery is enabled, which default service do we use.'),
                'custom_attributes' => array(
                    'data-type' => 'free-delivery-item',
                ),
            );

            return $fields;
        } catch (InvalidResourceDataException $e) {
            return $fields;
        }
    }

    /**
     * @param MdsColliveryService $service
     *
     * @return array
     * @throws InvalidResourceDataException
     */
    public static function getResources(MdsColliveryService $service)
    {
        $cache = $service->returnCacheClass();
        if ($cache->has('resources')) {
            return $cache->get('resources');
        }

        $collivery = $service->returnColliveryClass();

        try {
            $resources = array();
            foreach (['towns', 'location_types', 'services'] as $resource) {
                $result = $collivery->{'get' . str_replace('_', '', ucwords($resource))}();
                if (!is_array($result)) {
                    throw new InvalidResourceDataException(
                        'Unable to retrieve fields from the API',
                        $service->loggerSettingsArray()
                    );
                }

                $resources[$resource] = $result;
            }

            $cache->put('resources', $resources);

            return $resources;
        } catch (SoapConnectionException $e) {
            throw new InvalidResourceDataException($e->getMessage(), $service->loggerSettingsArray());
        }
    }
}
