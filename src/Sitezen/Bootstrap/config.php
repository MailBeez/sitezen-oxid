<?php
namespace SiteZen\Telemetry\Bootstrap;

class config
{
    static function config($envPath = '.sitezen.env.php'): array
    {
        $env = new Env($envPath);

        $token = $env->get('SITEZEN_TOKEN')??'';
        $platform = $env->get('PLATFORM')??'';
        $rootPath = $env->get('ROOT_PATH')??'';
        $host = $env->get('DB_HOST')??'';
        $username = $env->get('DB_USER')??'';
        $password = $env->get('DB_PASS')??'';
        $database = $env->get('DB_NAME')??'';
        $port = $env->get('DB_PORT')??'';
        $socket = $env->get('DB_SOCKET')??'';

        return [
            'token' => $token,
            'platform' => $platform,
            'rootPath' => $rootPath,
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'port' => $port,
            'socket' => $socket
        ];
    }
}
