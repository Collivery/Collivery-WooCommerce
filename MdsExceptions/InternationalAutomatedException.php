<?php

namespace MdsExceptions;

/**
 * Class InternationalAutomatedException.
 */
class InternationalAutomatedException extends ExceptionMiddleware
{
    /**
     * InternationalAutomatedException constructor.
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
