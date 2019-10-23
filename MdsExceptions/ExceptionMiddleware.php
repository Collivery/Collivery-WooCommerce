<?php

namespace MdsExceptions;

use Exception;
use MdsSupportingClasses\MdsLogger;

class ExceptionMiddleware extends Exception
{
    /**
     * ExceptionMiddleware constructor.
     *
     * @param string     $message
     * @param null       $functionName
     * @param array|null $settings
     * @param array|null $data
     */
    public function __construct($message, $functionName = null, array $settings = null, array $data = null)
    {
        $logger = new MdsLogger();
        $logger->error($functionName, $message, $settings, $data);
        parent::__construct($message);
    }
}
