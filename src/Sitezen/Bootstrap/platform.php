<?php

namespace SiteZen\Telemetry\Bootstrap;

// experimental
class platform
{

    static function envPath($platform): string
    {
        $paths = [
            'laravel' => realpath($_SERVER["DOCUMENT_ROOT"]) . '/../.env'
        ];

        return $paths[$platform] ?? 'unknown';
    }


    static function detect(): bool
    {
        if (file_exists(static::envPath('laravel'))) {
            return define('SITEZEN_PLATFORM', 'LARAVEL');
        }

        return define('SITEZEN_PLATFORM', 'unknown');
    }

    static function db_config(): array
    {

        static::detect();

        switch (constant('SITEZEN_PLATFORM')) {
            case 'LARAVEL':
                $env = new Env(static::envPath('laravel'));
                $host = $env->get('DB_HOST');
                $username = $env->get('DB_USERNAME');
                $password = $env->get('DB_PASSWORD');
                $database = $env->get('DB_DATABASE');
                $port = $env->get('DB_PORT');
                $socket = null;
                break;

            case 'GAMBIO':

                // todo
                // include('includes/configure.php)

                $host = constant('DB_SERVER');
                $username = constant('DB_SERVER_USERNAME');
                $password = constant('DB_SERVER_PASSWORD');
                $database = constant('DB_DATABASE');
                $port = null;
                $socket = null;
                // todo
                break;
        }


        return [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'port' => $port,
            'socket' => $socket
        ];
    }

}
