<?php

namespace MdsExceptions;

class InvalidColliveryDataException extends ExceptionMiddleware
{
    /**
     * @var array
     */
    private $colliveryData;

    /**
     * InvalidColliveryDataException constructor.
     *
     * @param string $message
     * @param null   $functionName
     * @param array  $settings
     * @param array  $colliveryData
     */
    public function __construct($message, $functionName, array $settings, array $colliveryData)
    {
        $this->colliveryData = $colliveryData;
        parent::__construct($message, $functionName, $settings, $colliveryData);
    }

    /**
     * @return array
     */
    public function getColliveryDataUsed()
    {
        return $this->colliveryData;
    }
}
