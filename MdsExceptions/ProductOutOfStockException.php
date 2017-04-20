<?php

namespace MdsExceptions;

/**
 * Class ProductOutOfException.
 */
class ProductOutOfException extends ExceptionMiddleware
{
    /**
     * InvalidResourceDataException constructor.
     *
     * @param string     $message
     * @param null       $functionName
     * @param array|null $settings
     * @param array|null $products
     */
    public function __construct($message, $functionName, $settings, $products)
    {
        parent::__construct($message, $functionName, $settings, $products);
    }
}
