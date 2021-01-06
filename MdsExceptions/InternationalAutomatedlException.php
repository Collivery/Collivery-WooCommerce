<?php

namespace MdsExceptions;

/**
 * Class InvalidResourceDataException.
 */
class InternationalAutomatedlException extends ExceptionMiddleware
{
    /**
     * InternationalAutomatedlException constructor.
     *
     * @param string $message
     * @param array  $settings
     * @param array  $data
     */
    public function __construct($message, array $settings, array $data)
    {
        parent::__construct($message, '', $settings, $data);
    }
}
