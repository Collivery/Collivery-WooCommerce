<?php

namespace MdsSupportingClasses;

use MdsExceptions\InvalidResourceDataException;

class MdsFields
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
	 * @var MdsCache
	 */
	protected $cache;

	/**
	 * CheckoutFields constructor.
	 */
	public function __construct()
	{
		$this->service = MdsColliveryService::getInstance();
		$this->collivery = $this->service->returnColliveryClass();
		$this->cache = $this->service->returnCacheClass();
	}

	/**
	 * @param int $attempt
	 * @return array
	 * @throws InvalidResourceDataException
	 */
	public function getResources($attempt = 0)
	{
		if($attempt > 1) {
			throw new InvalidResourceDataException("Unable to retrieve fields from the API", $this->service->loggerSettingsArray());
		}

		$towns = $this->collivery->getTowns();
		$location_types = $this->collivery->getLocationTypes();
		$services = $this->collivery->getServices();

		if(!is_array($towns) || !is_array($location_types) || !is_array($services)) {
			if(!is_array($towns)) {
				$this->cache->clear('town');
			}
			if(!is_array($location_types)) {
				$this->cache->clear('location_types');
			}
			if(!is_array($services)) {
				$this->cache->clear('service');
			}

			$this->getResources($attempt + 1);
		} else {
			return compact('towns', 'location_types', 'services');
		}
	}
}
