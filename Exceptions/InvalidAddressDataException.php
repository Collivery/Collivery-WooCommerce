<?php

class InvalidAddressDataException extends Exception
{
	/**
	 * @type array
	 */
	private $addressData;

	public function __construct(array $addressData, $message)
	{
		$this->addressData = $addressData;
		parent::__construct($message);
	}

	/**
	 * @return array
	 */
	public function getAddressDataUsed()
	{
		return $this->addressData;
	}
}
