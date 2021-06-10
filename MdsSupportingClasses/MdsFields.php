<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;
use MdsExceptions\CurlConnectionException;

class MdsFields
{
    public static function getFields()
    {
        return [
            'downloadLogs' => [
                'title' => __('Clear Cache/Download Error Logs?'),
                'type' => 'text',
                'description' => __(
                    'If you have any errors with the MDS plugin, you can download log files and email them to integration@collivery.co.za for support, clearing cache can be useful if you have empty list of towns etc'
                ),
                'placeholder' => admin_url().'admin.php?page=mds_download_log_files',
                'default' => null,
            ],
            'enabled' => [
                'title' => __('Enabled?'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'mds_user' => [
                'title' => 'MDS '.__('Username'),
                'type' => 'text',
                'description' => __('Email address associated with your MDS account.'),
                'default' => 'api@collivery.co.za',
            ],
            'mds_pass' => [
                'title' => 'MDS '.__('Password'),
                'type' => 'text',
                'description' => __('The password used when logging in to MDS.'),
                'default' => 'api123',
            ],
            'include_product_titles' => [
                'title' => __('Include product titles'),
                'type' => 'checkbox',
                'description' => __('Includes product titles which appended to the delivery instructions which max characters is 4096'),
                'default' => 'no',
            ],
            'include_order_number' => [
                'title' => __('Include order number'),
                'type' => 'checkbox',
                'description' => __('Includes order number which appended to the delivery instructions which max characters is 4096'),
                'default' => 'yes',
            ],
            'order_number_prefix' => [
                'title' => __('Prefix for order number'),
                'type' => 'radio',
                'description' => __('Text before the order number'),
                'default' => 'Order Number',
                'options' => [
                    '' => __('None'),
                    '#' => __('#'),
                    'Ord.'  => __('Ord.'),
                    'Order Number' => __('Order Number'),
                ],
            ],
            'include_customer_note' => [
                'title' => __('Include customer note'),
                'type' => 'checkbox',
                'description' => __('Includes customer note which appended to the delivery instructions which max characters is 4096'),
                'default' => 'no',
            ],
            'round' => [
                'title' => 'MDS '.__('Round Price'),
                'type' => 'checkbox',
                'description' => __('Rounds price up.'),
                'default' => 'yes',
            ],
            'include_vat' => [
                'title' => 'MDS '.__('Use Inclusive Amount'),
                'type' => 'checkbox',
                'description' => __('If Woocommerce is setup to add VAT onto the shipping cost then you should uncheck this box to use the exclusive amount, this way VAT will only be applied once. If your not adding VAT onto the shipping cost using Woocommerce then always use the inclusive amount. This option only affects the price displayed on your checkout page, MDS Collivery will always bill you the inclusive amount.'),
                'default' => 'yes',
            ],
            'risk_cover' => [
                'title' => 'MDS '.__('Risk Cover'),
                'type' => 'checkbox',
                'description' => __('Risk cover, up to a maximum of R10 000.'),
                'default' => 'no',
            ],
            'risk_cover_threshold' => [
                'title' => __('Risk cover minimum'),
                'type' => 'decimal',
                'description' => __('The minimum price of cart items to enable').' MDS '.__(
                        'risk cover, only active when risk cover above is checked<br><strong>Please read the <a href="https://collivery.net/terms" target="_blank">terms and conditions</a> on our website</strong>'
                    ),
                'default' => 1000.00,
            ],
            'fee_exclude_virtual' => [
                'title' => __('Exclude "Virtual" From Calculations'),
                'type' => 'checkbox',
                'description' => __('Do not include products marked as "virtual" in the calculation for free/discounted deliveries or towards the "Risk cover minimum".'),
                'default' => 'no',
            ],
            'fee_exclude_downloadable' => [
                'title' => __('Exclude "Downloadable" From Calculations'),
                'type' => 'checkbox',
                'description' => __('Do not include products marked as "downloadable" in the calculation for free/discounted deliveries or towards the "Risk cover minimum".'),
                'default' => 'no',
            ],
            'method_free' => [
                'title' => __('Free/Discount Delivery mode'),
                'type' => 'select',
                'description' => __('Whether to offer free or discounted deliveries if the cart total exceeds the value of "Free/Discount Delivery Min Total".'),
                'default' => 'no',
                'options' => [
                    'no' => __('No free deliveries'),
                    'yes' => __('Free delivery'),
                    'discount' => __('Discount on deliveries'),
                ],
            ],
            'shipping_discount_percentage' => [
                'title' => __('Percentage discount for shipping'),
                'type' => 'number',
                'description' => __(
                    'The percentage discount that users get when their cart total exceeds <strong>"Free/Discount Delivery Min Total"</strong>'
                ),
                'default' => 10,
                'custom_attributes' => [
                    'min' => 2,
                    'max' => 100,
                    'step' => 0.1,
                ],
            ],
            'wording_free' => [
                'title' => __('Free Delivery Wording'),
                'type' => 'text',
                'default' => 'Free Delivery',
                'custom_attributes' => [
                    'data-type' => 'free-delivery-item',
                ],
            ],
            'free_min_total' => [
                'title' => __('Free/Discount Delivery Min Total'),
                'type' => 'number',
                'description' => __('Minimum order total before free delivery is included, amount is including vat.'),
                'default' => '1000.00',
                'custom_attributes' => [
                    'step' => .1,
                    'min' => 0,
                ],
            ],
            'free_delivery_blacklist' => [
                'title' => __('Exclude these roles from free delivery'),
                'type' => 'text',
                'description' => __('Comma separated list of roles that must be excluded from free shipping'),
                'default' => null,
            ],
            'free_local_only' => [
                'title' => __('Free Delivery Local Only'),
                'type' => 'checkbox',
                'description' => __('Only allow free delivery for local deliveries only. '),
                'default' => 'no',
                'custom_attributes' => [
                    'data-type' => 'free-delivery-item',
                ],
            ],
            'toggle_automatic_mds_processing' => [
                'title' => __('Automatic MDS Processing'),
                'type' => 'checkbox',
                'description' => __(
                    'When enabled deliveries for an order will be automatically processed. Please refer to the manual for detailed information on implications on using this <a target="_blank" href="https://github.com/Collivery/Collivery-WooCommerce/">Manual</a>'
                ),
                'default' => 'no',
            ],
            'auto_accept' => [
                'title' => __('Auto accept'),
                'type' => 'checkbox',
                'description' => __(
                    'After automatic mds processing has sent the request through to MDS, should the request be auto accepted or will you manually accept the delivery on the MDS website'
                ),
                'default' => 'yes',
            ],
        ];
    }

    /**
     * @param MdsColliveryService $service
     *
     * @return array
     */
    public static function instanceFields(MdsColliveryService $service)
    {
        $fields = [];

        try {
            $resources = self::getResources($service);
            foreach ($resources['services'] as $item) {
                $fields['method_'.$item['id']] = [
                    'title' => __($item['text']),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ];
                $fields['fixed_price_'.$item['id']] = [
                    'title' => __($item['text'].' Fixed Price Amount'),
                    'type' => 'number',
                    'description' => 'Amount greater than 0 enables it, markup is then ignored. This will override any free or discounted shipping',
                    'default' => '0',
                    'custom_attributes' => [
                        'step' => 'any',
                        'min' => '0',
                    ],
                ];
                $fields['markup_'.$item['id']] = [
                    'title' => __($item['text'].' Markup'),
                    'type' => 'number',
                    'default' => '10',
                    'description' => 'Percentage markup you would like to apply to MDS\'s Price',
                    'custom_attributes' => [
                        'step' => 'any',
                        'min' => '0',
                    ],
                ];
                $fields['wording_'.$item['id']] = [
                    'title' => __($item['text'].' Wording'),
                    'type' => 'text',
                    'default' => $item['text'],
                    'description' => 'The wording you would like on the checkout page for this service',
                ];
                $fields['lead_time_'.$item['id']] = [
                    'title' => __($item['text'].' Lead time'),
                    'type' => 'number',
                    'default' => '24',
                    'description' => 'The lead time in hours you need to collection time to be when waybill is auto generated',
                    'class' => 'sectionEnd',
                ];
            }

            $ddl_services = [];
    
            foreach($resources['services'] as $arr) {
                $ddl_services[$arr['id']] = $arr['text'];
            }

            $fields['free_default_service'] = [
                'title' => __('Free Delivery Default Service'),
                'type' => 'select',
                'options' => $ddl_services,
                'default' => 5,
                'description' => __('When free delivery is enabled, which default service do we use.'),
                'custom_attributes' => [
                    'data-type' => 'free-delivery-item',
                ],
            ];
            $fields['free_local_default_service'] = [
                'title' => __('Free Delivery Local Only Default Service'),
                'type' => 'select',
                'options' => $ddl_services,
                'default' => 2,
                'description' => __('When free local only delivery is enabled, which default service do we use.'),
                'custom_attributes' => [
                    'data-type' => 'free-delivery-item',
                ],
            ];

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
            $resources = $cache->get('resources');
            $resources['services'] = $service->returnColliveryClass()->filterServices($resources['services']);

            return $resources;
        }

        $collivery = $service->returnColliveryClass();

        try {
            $resources = [];
            foreach (['towns', 'location_types', 'services'] as $resource) {
                $result = $collivery->{'get'.str_replace('_', '', ucwords($resource))}();
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
        } catch (CurlConnectionException $e) {
            throw new InvalidResourceDataException($e->getMessage(), $service->loggerSettingsArray());
        }
    }
}
