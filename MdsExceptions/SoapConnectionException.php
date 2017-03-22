<?php

namespace MdsExceptions;

class SoapConnectionException extends ExceptionMiddleware
{
	/**
	 * InvalidResourceDataException constructor.
	 * @param string $message
	 */
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
