<?php

namespace MdsExceptions;

/**
 * Class InvalidResourceDataException
 * @package MdsSupportingClasses\Exceptions
 */
class InvalidResourceDataException extends ExceptionMiddleware
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
