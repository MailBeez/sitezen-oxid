<?php

namespace SiteZen\Telemetry\Bootstrap;

class helper
{
    static function arr_get($arr, $key, $default = null)
    {
        return $arr[$key] ?? $default;
    }
}