<?php

namespace SiteZen\Telemetry\Connectors;

use SiteZen\Telemetry\Bootstrap\helper;

class Database
{

    var $host;
    var $username;
    var $password;
    var $database;
    var $port;
    var $socket;

    private $th_time_long_running;
    private $th_time_sleeping;
    private $th_high_resource_query_length;
    private $th_join_count;
    private $mysqli;
    /**
     * @var array|mixed
     */
    private $parameters = [];

    public function __construct($db_config, $parameters = [])
    {
        $this->host = $db_config['host'];
        $this->username = $db_config['username'];
        $this->password = $db_config['password'];
        $this->database = $db_config['database'];
        $this->port = (int)$db_config['port'];
        $this->socket = $db_config['socket'];


        $this->parameters = $parameters;


        // Thresholds
        $this->th_time_long_running = $parameters['long_running'] ?? 6; // Long running query threshold (in seconds)
        $this->th_time_sleeping = $parameters['sleeping'] ?? 75; // Threshold for sleeping connections
        $this->th_high_resource_query_length = $parameters['query_length'] ?? 1000; // Threshold for high resource query length
        $this->th_join_count = $parameters['join_count'] ?? 3; // Max number of joins in a query

    }

    private function connect()
    {
        if (!$this->mysqli) {
            $this->mysqli = new \mysqli($this->host, $this->username, $this->password, $this->database, $this->port, $this->socket);

            if ($this->mysqli->connect_errno) {
                throw new \Exception("Failed to connect to MySQL: " . $this->mysqli->connect_error);
            }
        }
    }

    public function db(): \mysqli {
        return $this->mysqli;
    }

    public function query($query): \mysqli_result {
        $this->connect();
        return $this->db()->query($query);
    }



    public function connections(): array
    {
        $variables = $this->variables(['max_connections']);
        $status = $this->status(['Max_used_connections', 'Max_used_connections_time', 'Threads_connected', 'Locked_connects', 'Connections']);

        return [
            'available' => helper::arr_get($variables, 'max_connections'),
            'used' => helper::arr_get($status, 'Threads_connected'),
            'max_used' => helper::arr_get($status, 'Max_used_connections'),
            'max_used_time' => helper::arr_get($status, 'Max_used_connections_time'),
            'total_count' => helper::arr_get($status, 'Connections')
        ];
    }

    private function _show($type = 'VARIABLES', $constrains = []): array
    {
        $this->connect();

        $variableList = null;
        if (!empty($constrains)) {
            $variableList = implode(', ', array_map(function ($name) {
                return "'" . $name . "'";
            }, $constrains));
        }

        $results = [];

        if ($statusResult = $this->mysqli->query("SHOW $type" . (!empty($constrains) ? " WHERE Variable_name IN ({$variableList});" : ''))) {
            while ($row = $statusResult->fetch_assoc()) {
                $results[$row['Variable_name']] = $row['Value'];
            }
            $statusResult->free();
        } else {
            throw new \Exception("Error executing SHOW $type: " . $this->mysqli->error);
        }
        return $results;
    }

    public function variables($variables = []): array
    {
        return $this->_show('VARIABLES', $variables);
    }

    public function status($variables = []): array
    {
        return $this->_show('STATUS', $variables);
    }

    public function insight(): array
    {
        $variables = $this->variables();

        return [
            'server' => [
                'database' => $this->database,
                'storage' => $this->usage(),
                'version' => helper::arr_get($variables, 'version'),
                'version_comment' => helper::arr_get($variables, 'version_comment'),
                'version_compile_machine' => helper::arr_get($variables, 'version_compile_machine'),
                'version_compile_os' => helper::arr_get($variables, 'version_compile_os')
            ],
            'variables' => $this->variables(),
            'status' => $this->status()
        ];
    }

    public function usage(): array
    {
        $this->connect();

        if ($dbSizeResult = $this->mysqli->query("SELECT SUM(data_length + index_length) AS 'size' FROM information_schema.TABLES WHERE table_schema = '{$this->database}'")) {
            $dbSizeRow = $dbSizeResult->fetch_assoc();
            $dbSizeResult->free();
            $result = round($dbSizeRow['size'] / 1024 / 1024, 2);
        } else {
            throw new \Exception("Error executing usage: " . $this->mysqli->error);
        }
        return ['used' => $result];
    }

    public function largestTables($limit = 10): array
    {
        $this->connect();

        if ($largestTablesResult = $this->mysqli->query("SELECT TABLE_NAME, TABLE_ROWS AS 'number_of_rows', ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'size_mb', ROUND((data_length) / 1024 / 1024, 2) AS 'data_size_mb', ROUND((index_length) / 1024 / 1024, 2) AS 'index_size_mb' FROM information_schema.TABLES WHERE table_schema = '{$this->database}' ORDER BY size_mb DESC LIMIT {$limit}")) {
            $result = [];
            while ($row = $largestTablesResult->fetch_assoc()) {
                $result[$row['TABLE_NAME']] = [
                    'rows' => helper::arr_get($row, 'number_of_rows'),
                    'total' => helper::arr_get($row, 'size_mb'),
                    'data' => helper::arr_get($row, 'data_size_mb'),
                    'index' => helper::arr_get($row, 'index_size_mb')
                ];
            }
            $largestTablesResult->free();
        } else {
            throw new \Exception("Error executing largestTables: " . $this->mysqli->error);
        }
        return $result;
    }

    public function analyzeProcessList(): array
    {
        $this->connect();
        $issues = [
            'long_running_queries' => [],
            'sleeping_connections' => [],
            'locked_tables' => [],
            'high_resource_usage' => []
        ];

        if ($result = $this->mysqli->query("SHOW PROCESSLIST")) {
            while ($process = $result->fetch_assoc()) {
                // Detect long-running queries
                if ($process['Command'] === 'Query' && $process['Time'] > $this->th_time_long_running) {
                    $issues['long_running_queries'][] = $process;
                }

                // Detect locked tables
                if ($process['State'] === 'Locked') {
                    $issues['locked_tables'][] = $process;
                }

                // Detect sleeping connections
                if ($process['Command'] === 'Sleep' && $process['Time'] > $this->th_time_sleeping) {
                    $issues['sleeping_connections'][] = $process;
                }

                // Detect high resource usage (e.g., long or complex queries)
                if ($process['Command'] === 'Query' && !empty($process['Info'])) {
                    // Long queries
                    if (strlen($process['Info']) > $this->th_high_resource_query_length) {
                        $issues['high_resource_usage'][] = $process;
                    }
                    // Queries with many joins
                    if (substr_count($process['Info'], 'JOIN') > $this->th_join_count) {
                        $issues['high_resource_usage'][] = $process;
                    }
                }
            }
            $result->free();
        } else {
            throw new \Exception("Error executing SHOW PROCESSLIST: " . $this->mysqli->error);
        }

        return $issues;
    }

    public function processList(): array
    {
        $this->connect();
        $processlist = [];

        if ($result = $this->mysqli->query("SHOW PROCESSLIST")) {
            while ($process = $result->fetch_assoc()) {
                $processlist[] = $process;
            }
            $result->free();
        } else {
            throw new \Exception("Error executing SHOW PROCESSLIST: " . $this->mysqli->error);
        }

        return $processlist;

    }

    public function schema()
    {
        $this->connect();
        $schema = [];

        $query = "
    SELECT 
        c.TABLE_NAME, 
        t.TABLE_COLLATION,  -- Get the collation of the table
        c.COLUMN_NAME, 
        c.DATA_TYPE, 
        c.COLUMN_TYPE, 
        c.COLLATION_NAME, 
        c.IS_NULLABLE, 
        c.COLUMN_DEFAULT, 
        c.COLUMN_KEY, 
        c.EXTRA 
    FROM information_schema.COLUMNS c
    JOIN information_schema.TABLES t 
        ON c.TABLE_SCHEMA = t.TABLE_SCHEMA 
        AND c.TABLE_NAME = t.TABLE_NAME
    WHERE c.TABLE_SCHEMA = '{$this->database}'
";

        if ($result = $this->mysqli->query($query)) {
            while ($column = $result->fetch_assoc()) {
                $table = $column['TABLE_NAME'];
                unset($column['TABLE_NAME']); // Remove redundant table name from each column entry
                $column_name = $column['COLUMN_NAME'];
                unset($column['COLUMN_NAME']); // Remove redundant table name from each column entry
                $schema[$table][$column_name] = $column;
            }
            $result->free();
        } else {
            throw new \Exception("Error executing schema: " . $this->mysqli->error);
        }

        return $schema;
    }

    public function close(): void
    {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
}