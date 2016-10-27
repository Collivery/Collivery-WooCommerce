<?php

namespace MdsExceptions;

class InvalidCartPackageException extends ExceptionMiddleware
{
	/**
	 * InvalidAddressDataException constructor.
	 *
	 * @param string $message
	 * @param null $functionName
	 * @param array $settings
	 * @param array $package
	 */
	public function __construct($message, $functionName, array $settings, array $package)
	{
		parent::__construct($message, $functionName, $settings, $package);
	}
}
