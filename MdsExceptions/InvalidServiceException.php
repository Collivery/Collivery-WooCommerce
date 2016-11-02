<?php
namespace MdsExceptions;

/**
 * Class InvalidResourceDataException
 * @package MdsSupportingClasses\Exceptions
 */
class InvalidServiceException extends ExceptionMiddleware
{
	/**
	 * InvalidResourceDataException constructor.
	 * @param string $message
	 * @param null $functionName
	 * @param array|null $settings
	 * @param array|null $shippingMethods
	 */
	public function __construct($message, $functionName, $settings, $shippingMethods)
	{
		parent::__construct($message, $functionName, $settings, $shippingMethods);
	}
}
