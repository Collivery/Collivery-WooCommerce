<?php

namespace MdsSupportingClasses;

class MdsSettings
{
    /**
     * @var array
     */
    public $settings;

    /**
     * @var array
     */
    public $instanceSettings;

    public function __construct($settings = [], $instanceSettings = [])
    {
        $this->settings = $settings;
        $this->instanceSettings = $instanceSettings;
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed|null|string
     */
    public function getValue($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed|null|string
     */
    public function getInstanceValue($key, $default = null)
    {
        return isset($this->instanceSettings[$key]) ? $this->instanceSettings[$key] : $default;
    }
}
