<?php

namespace MdsExceptions;

/**
 * Class InvalidResourceDataException.
 */
class InvalidResourceDataException extends ExceptionMiddleware
{
    /**
     * InvalidResourceDataException constructor.
     *
     * @param string $message
     * @param array  $data
     */
    public function __construct($message, array $data)
    {
        parent::__construct($message, null, $data);
    }
}
