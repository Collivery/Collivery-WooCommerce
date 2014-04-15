<?php

namespace Mds;

class Cache {

    private $cache_dir = '/cache/mds_collivery/';
    private $cache;

    protected function load($name) {
        if (!isset($this->cache[$name])) {
            if (file_exists(__DIR__ . $this->cache_dir . $name) && $content = file_get_contents(__DIR__ . $this->cache_dir . $name)) {
                $this->cache[$name] = json_decode($content, true);
                return $this->cache[$name];
            } else {
                if (!is_dir(__DIR__ . $this->cache_dir)) {
                    mkdir(__DIR__ . $this->cache_dir);
                }
            }
        } else {
            return $this->cache[$name];
        }
    }

    public function has($name) {
        $cache = $this->load($name);
        if (is_array($cache) && ( $cache['valid'] - 30 ) > time()) {
            return true;
        } else {
            return false;
        }
    }

    public function get($name) {
        $cache = $this->load($name);
        if (is_array($cache) && $cache['valid'] > time()) {
            return $cache['value'];
        } else {
            return null;
        }
    }

    public function put($name, $value, $time = 1440) {
        $cache = array('value' => $value, 'valid' => time() + ( $time * 60 ));
        if (file_put_contents(__DIR__ . $this->cache_dir . $name, json_encode($cache))) {
            $this->cache[$name] = $cache;
            return true;
        } else {
            return false;
        }
    }

    public function forget($name) {
        $cache = array('value' => '', 'valid' => 0);
        if (file_put_contents(__DIR__ . $this->cache_dir . $name, json_encode($cache))) {
            $this->cache[$name] = $cache;
            return true;
        } else {
            return false;
        }
    }

}
