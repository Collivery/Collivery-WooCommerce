<?php

namespace MdsExceptions;

class InvalidAddressDataException extends ExceptionMiddleware
{
	/**
	 * @type array
	 */
	private $addressData;

	/**
	 * InvalidAddressDataException constructor.
	 * @param string $message
	 * @param null $functionName
	 * @param array $settings
	 * @param array $addressData
	 */
	public function __construct($message, $functionName, array $settings, array $addressData)
	{
		$this->addressData = $addressData;
		parent::__construct($message, $functionName, $settings, $addressData);
	}

	/**
	 * @return array
	 */
	public function getAddressDataUsed()
	{
		return $this->addressData;
	}
}
