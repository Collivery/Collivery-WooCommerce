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
	 *
	 * @param string $message
	 * @param array $data
	 */
	public function __construct($message, array $data)
	{
		parent::__construct($message, null, $data);
	}
}
