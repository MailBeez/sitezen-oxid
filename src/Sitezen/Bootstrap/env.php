<?php
namespace SiteZen\Telemetry\Bootstrap;

class Env
{
    var $envFilePath;
    var $env;

    public function __construct($envFilePath = '.sitezen.env.php')
    {

        $this->envFilePath = $envFilePath;


        if (!file_exists($this->envFilePath??'')) {
            header('X-SiteZen-Status: 400');
            header('HTTP/1.0 400 Bad Request');
            die("Can not load env file: " . $this->envFilePath);
        }

        $envFile = fopen($this->envFilePath, "r");
        if ($envFile) {
            while (($line = fgets($envFile)) !== false) {
                if (!strpos($line, '=') || strpos($line, '#') === 0) {
                    continue;
                }
                list($key, $value) = explode('=', $line, 2);
                // Trim spaces and remove surrounding double quotes if present
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"");

                $this->env[$key] = $value;
            }
            fclose($envFile);
        } else {
            header('X-SiteZen-Status: 400');
            header('HTTP/1.0 400 Bad Request');
            die("Unable to open the file: " . $this->envFilePath);
        }
    }

    public function get($variable): ?string
    {
        return getenv($variable) !== false ? getenv($variable) : $this->env[$variable] ?? null;
    }

    static function saveEnvFile($envFilePath = '.sitezen.env.php', $data = []) {

        $env_code = '
        SITEZEN_TOKEN=' . ($data['SITEZEN_TOKEN']??'') . '
        PLATFORM=' . ($data['PLATFORM']??'') . '
        ROOT_PATH=' . ($data['ROOT_PATH']??'') . '
        DB_HOST=' . ($data['DB_HOST']??'') . '
        DB_USER=' . ($data['DB_USER']??'') . '
        DB_PASS=' . ($data['DB_PASS']??'') . '
        DB_NAME=' . ($data['DB_NAME']??'') . '
        DB_PORT=' . ($data['DB_PORT']??'') . '
        DB_SOCKET=' . ($data['DB_SOCKET']??'') . '
        ';

        // remove trailing whitespace
        $env_code = preg_replace('/^\s+|\s+$/m', '', $env_code);

        $env_content ='<?php
# This file is generated automatically. It allows the SiteZen.io telemetry
# to bypass the store system and connect directly to the database.
# This reduces load on the server and allows for more efficient use of resources.'
            . PHP_EOL. '/*' . PHP_EOL . $env_code . PHP_EOL. '*/';

        file_put_contents($envFilePath, $env_content);
    }
}



