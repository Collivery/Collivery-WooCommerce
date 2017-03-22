<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;

class MdsSettings extends MdsFields
{
	/**
	 * @var array
	 */
	protected $defaultFields = array();

	/**
	 * CheckoutFields constructor.
	 * @param array $defaultFields
	 */
	public function __construct(array $defaultFields)
	{
		$this->defaultFields = $defaultFields;
		parent::__construct();
	}

	/**
	 * @return array
	 */
	public function getFields()
	{
		$fields = $this->defaultFields;

		try {
			$resources = $this->getResources();

			$fields['include_product_titles'] = array(
				'title' => __('Include product titles'),
				'type' => 'checkbox',
				'description' => __('Includes product titles in the delivery instructions, max 4096 characters'),
				'default' => 'no',
			);

			$fields['risk_cover'] = array(
				'title' => "MDS " . __('Risk Cover'),
				'type' => 'checkbox',
				'description' => __('Risk cover, up to a maximum of R10 000.'),
				'default' => 'no',
			);

			$fields['risk_cover_threshold'] = array(
				'title' => __('Risk cover minimum'),
				'type' => 'decimal',
				'description' => __('The minimum price of cart items to enable') . ' MDS ' . __('risk cover<br><strong>Please read the <a href="https://collivery.net/terms" target="_blank">terms and conditions</a> on our website</strong>'),
				'default' => 0.00
			);

			$fields['round'] = array(
				'title' => "MDS " . __('Round Price'),
				'type' => 'checkbox',
				'description' => __('Rounds price up.'),
				'default' => 'yes',
			);

			foreach ($resources['services'] as $id => $title) {
				$fields['method_' . $id] = array(
					'title' => __($title),
					'label' => __($title . ': Enabled'),
					'type' => 'checkbox',
					'default' => 'yes',
				);
				$fields['markup_' . $id] = array(
					'title' => __($title . ' Markup'),
					'type' => 'number',
					'default' => '10',
					'custom_attributes' => array(
						'step' 	=> 'any',
						'min'	=> '0'
					)
				);
				$fields['wording_' . $id] = array(
					'title' => __($title . ' Wording'),
					'type' => 'text',
					'default' => $title,
					'class' => 'sectionEnd'
				);
			}

			$fields['method_free'] = array(
				'title' => __('Free Delivery mode'),
				'label' => __('Free Delivery: Enabled'),
				'type' => 'select',
				'default' => 'no',
				'options' => array(
					'no' => __('No free deliveries'),
					'yes' => __('Free delivery'),
					'discount' => _('Discount on deliveries')
				),
				'custom_attributes' => array(
					'title' => 'Choose shipping mode'
				)
			);

			$fields['shipping_discount_percentage'] = array(
				'title' => __('Percentage discount for shipping'),
				'type' => 'number',
				'description' => __('The percentage discount that users get when their cart total exceeds <strong>"Free Delivery Min Total"</strong>'),
				'default' => 10,
				'custom_attributes' => array(
					'min' => 2,
					'max' => 100,
					'step' => 0.1
				)
			);

			$fields['wording_free'] = array(
				'title' => __('Free Delivery Wording'),
				'type' => 'text',
				'default' => 'Free Delivery',
				'custom_attributes' => array(
					'data-type' => 'free-delivery-item'
				),
			);


			$fields['free_min_total'] = array(
				'title' => __('Free Delivery Min Total'),
				'type' => 'number',
				'description' => __('Min order total before free delivery is included, amount is including vat.'),
				'default' => '1000.00',
				'custom_attributes' => array(
					'step' 	=> .1,
					'min'	=> 0
				)
			);

			$fields['free_local_only'] = array(
				'title' => __('Free Delivery Local Only'),
				'type' => 'checkbox',
				'description' => __('Only allow free delivery for local deliveries only. '),
				'default' => 'no',
				'custom_attributes' => array(
					'data-type' => 'free-delivery-item'
				),
			);

			$fields['free_default_service'] = array(
				'title' => __('Free Delivery Default Service'),
				'type' => 'select',
				'options' => $resources['services'],
				'default' => 5,
				'description' => __('When free delivery is enabled, which default service do we use.'),
				'custom_attributes' => array(
					'data-type' => 'free-delivery-item'
				),
			);

			$fields['free_local_default_service'] = array(
				'title' => __('Free Delivery Local Only Default Service'),
				'type' => 'select',
				'options' => $resources['services'],
				'default' => 2,
				'description' => __('When free local only delivery is enabled, which default service do we use.'),
				'custom_attributes' => array(
					'data-type' => 'free-delivery-item'
				),
			);

			$fields['toggle_automatic_mds_processing'] = array(
				'title' => __('Automatic MDS Processing'),
				'type' => 'checkbox',
				'description' => __('When enabled deliveries for an order will be automatically processed. Please refer to the manual for detailed information on implications on using this <a target="_blank" href="http://collivery.github.io/Collivery-WooCommerce/">Manual</a>'),
				'default' => 'no',
			);
		} catch (InvalidResourceDataException $e) {
			return $this->defaultFields;
		}
	}
}
