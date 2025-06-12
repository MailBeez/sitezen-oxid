<?php

namespace SiteZen\Telemetry\Connectors;


class Application
{
    public function __construct($parameters = [])
    {
    }

    public static function applicationData($config): array|string
    {
        return Oxid::applicationData($config);
    }


    public static function threatsUserData($config): array|string
    {
        return Oxid::adminUsers($config);
    }
}
