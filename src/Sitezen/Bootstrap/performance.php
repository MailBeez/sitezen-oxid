<?php

namespace SiteZen\Telemetry\Bootstrap;


class Performance
{
    public static function start():void
    {
        define('SITEZEN_START_TIME', microtime(true));
        define('SITEZEN_START_MEMORY', memory_get_usage());
    }

    public static function end(): array
    {
        return [
            'time' => round(microtime(true) - constant('SITEZEN_START_TIME'), 3),
            'memory' => round((memory_get_usage() - constant('SITEZEN_START_MEMORY')) / 1024 / 1024, 3),
        ];
    }
}