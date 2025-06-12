<?php

namespace SiteZen\Telemetry\Bootstrap;
class Gate
{
    // only let pass valid requests
    static function check($config): void
    {

        $unauthorized = false;

        if (!isset($config['token']) || empty($config['token'])) {
            header('X-SiteZen-Status: 403');
            header('HTTP/1.0 403 Forbidden');
            exit();
        }

        $reason_unauthorized = '';
        if ($config['token'] != ($_SERVER['HTTP_X_SITEZEN_TOKEN'] ?? '')) {
            $unauthorized = true;
            $reason_unauthorized = 'NoToken';
        }
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $unauthorized = true;
            $reason_unauthorized = 'NoPOST';
        }

        if ($unauthorized) {
            header('X-SiteZen-Status: 401');
            header('X-SiteZen-Reason: '. $reason_unauthorized);
            header('HTTP/1.0 401 Unauthorized');
            exit();
        }

        header('X-SiteZen-Status: 200');
        header('HTTP/1.0 200 OK');
    }
}
