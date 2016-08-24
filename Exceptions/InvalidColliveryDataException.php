<?php

class InvalidColliveryDataException extends Exception
{
	/**
	 * @type array
	 */
	private $colliveryData;

	public function __construct(array $colliveryData, $message)
	{
		$this->colliveryData = $colliveryData;
		parent::__construct($message);
	}

	/**
	 * @return array
	 */
	public function getColliveryDataUsed()
	{
		return $this->colliveryData;
	}
}
