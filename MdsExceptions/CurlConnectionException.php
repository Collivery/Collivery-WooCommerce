<?php

namespace MdsExceptions;

class CurlConnectionException extends ExceptionMiddleware
{
    /**
     * InvalidResourceDataException constructor.
     *
     * @param string $message
     */
    public function __construct($message, $functionName, array $data)
    {
        parent::__construct($message, $functionName, [], $data);
    }
}
