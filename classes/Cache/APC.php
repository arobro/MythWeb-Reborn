<?php

class Cache_APC implements Cache_Engine {

    public function get($key = null) {
        if (is_array($key)) {
            $ret = array();
            foreach ($key as $k => $v) {
                $ret[$k] = self::get($v);
            }
            return $ret;
        }

        return apcu_fetch($key);
    }

    public function set($key, $data, $lifeLength) {
        return apcu_store($key, $data, $lifeLength);
    }

    public function delete($key) {
        return apcu_delete($key);
    }

    public static function isEnabled() {
        return function_exists('apcu_fetch');
    }

    public function clear() {
        return apcu_clear_cache();
    }
}