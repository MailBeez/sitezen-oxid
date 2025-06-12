<?php
namespace SiteZen\Telemetry\Bootstrap;

use SiteZen\Telemetry\Connectors\Database;
use SiteZen\Telemetry\Connectors\Server;
use SiteZen\Telemetry\Connectors\Application;

class Process
{
    private $db;
    private $handlers = [];

    public $request;

    public function __construct(array $config)
    {
        $payload = file_get_contents("php://input");
        $request = json_decode($payload, true);

        if (!is_array($request) || !isset($request['metric'])) {
            return ["error" => "Unknown request"];
        }

        $this->request = $request;

        $this->db = new Database($config, $request['parameters'] ?? []);

        // Register built-in handlers
        $this->registerDefaultHandlers($config);

    }

    private function registerDefaultHandlers($config): void
    {
        // Server Metrics
        $this->registerHandler('server:cpu', [Server::class, 'cpu']);
        $this->registerHandler('server:memory', [Server::class, 'memory']);
        $this->registerHandler('server:storage', [Server::class, 'storage']);
        $this->registerHandler('server:diskUsage', function () use ($config) {
            return Server::diskUsage($config);
        });
        $this->registerHandler('server:system', [Server::class, 'system']);
        $this->registerHandler('server:php', [Server::class, 'php']);
        $this->registerHandler('server:all', function () {
            return [
                'cpu' => Server::cpu(),
                'memory' => Server::memory(),
                'storage' => Server::storage(),
                'system' => Server::system(),
                'php' => Server::php(),
            ];
        });

        // Database Metrics
        $this->registerHandler('database:usage', function () {
            return $this->db->usage();
        });
        $this->registerHandler('database:largestTables', function () {
            return $this->db->largestTables();
        });
        $this->registerHandler('database:connections', function () {
            return $this->db->connections();
        });
        $this->registerHandler('database:insight', function () {
            return $this->db->insight();
        });
        $this->registerHandler('database:analyse', function () {
            return $this->db->analyzeProcessList();
        });
        $this->registerHandler('database:processlist', function () {
            return $this->db->processList();
        });
        $this->registerHandler('database:schema', function () {
            return $this->db->schema();
        });
        $this->registerHandler('database:all', function () {
            return [
                'usage' => $this->db->usage(),
                'largestTables' => $this->db->largestTables(),
                'connections' => $this->db->connections(),
                'insight' => $this->db->insight(),
                'analyse' => $this->db->analyzeProcessList(),
                'schema' => $this->db->schema()
            ];
        });
    }

    public function registerHandler(string $metric, callable $callback): void
    {
        $this->handlers[$metric] = $callback;
    }

    public function executeHandler()
    {
        $metric = $this->request['metric']??'unknown';

        if (!isset($this->handlers[$metric])) {
            return ["error" => "Unknown metric"];
        }
        $handler = $this->handlers[$metric];

        // Ensure closure execution
        $data =  is_callable($handler) ? $handler($this->request) : $handler;

        $this->db->close();

        return $data;
    }

}
