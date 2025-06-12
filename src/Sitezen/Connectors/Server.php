<?php

namespace SiteZen\Telemetry\Connectors;

require_once __DIR__ . '/vendor/autoload.php';

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Fidry\CpuCoreCounter\NumberOfCpuCoreNotFound;
use Fidry\CpuCoreCounter\Finder\DummyCpuCoreFinder;

class Server
{
    public static function cpu(): array
    {
        $commandType = 'shell_exec';
        switch (PHP_OS_FAMILY) {
            case 'Darwin':
                $command = "top -l 1 | grep -E '^CPU' | tail -1 | awk '{ print \$3 + \$5 }'";
                break;
            case 'Linux':
                $command = "top -bn1 | grep -E '^(%Cpu|CPU)' | awk '{ print \$2 + \$4 }'";
                break;
            case 'Windows':
                $command = "trim(`wmic cpu get loadpercentage | more +1`)";
                break;
            case 'BSD':
                $command = "top -b -d 2 | grep 'CPU: ' | tail -1 | awk '{print\$10}' | grep -Eo '[0-9]+\.[0-9]+' | awk '{ print 100 - \$1 }'";
                break;
            default:
                throw new \RuntimeException('The Server:CPU sensor does not currently support ' . PHP_OS_FAMILY);
                break;
        };

        /*
         * System Load: Use sys_getloadavg() to get an array with the system's load average over the last 1, 5, and 15 minutes.
         * This function is not available on Windows platforms.
         * For more detailed CPU usage, you might need to execute system commands using shell_exec() and parse the output.
         *
         */

        // The value is a fraction of the total number of system cores available, so a load average of 1.00 means a single-core system is fully loaded, while on a 4-core system, it would mean it's under 25% load.
        // -> would require to know the number of CPU-cores
        // 'sys_getloadavg' => function_exists('sys_getloadavg') ? sys_getloadavg() : false // Note: This function is not implemented on Windows platforms.

        $current = shell_exec($command);

        // fallback to sys_getloadavg() if shell_exec() fails
        if (is_null($current)) {
            $current = false;
            $commandType = 'cpuload_blocked';
            if (function_exists('sys_getloadavg')) {
                $commandType = 'sys_getloadavg';
                $sysLoad = sys_getloadavg();
                if (is_array($sysLoad)) {
                    $current = $sysLoad[0];
                }
            }

        }

        return [
            'current' => intval($current),
            'cmd' => $commandType,
        ];
    }

    public static function memory(): array
    {
        $memoryTotal = 0;
        $memoryUsed = 0;

        switch (PHP_OS_FAMILY) {
            case 'Darwin':
                $memoryTotal = intval(shell_exec("/usr/sbin/sysctl hw.memsize | grep -Eo '[0-9]+'")) / 1024 / 1024;
                $memoryUsed = $memoryTotal - (intval(shell_exec("vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'")) * intval(shell_exec("pagesize")) / 1024 / 1024);
                break;
            case 'Linux':
                $memoryTotal = intval(shell_exec("cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'")) / 1024;
                $memoryUsed = $memoryTotal - (intval(shell_exec("cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'")) / 1024);
                break;
            case 'Windows':
                $memoryTotal = intval(shell_exec("wmic ComputerSystem get TotalPhysicalMemory | more +1")) / 1024 / 1024;
                $memoryUsed = $memoryTotal - (intval(shell_exec("wmic OS get FreePhysicalMemory | more +1")) / 1024);
                break;
            case 'BSD':
                $memoryTotal = intval(shell_exec("sysctl hw.physmem | grep -Eo '[0-9]+'")) / 1024 / 1024;
                $totalPages = shell_exec("( sysctl vm.stats.vm.v_cache_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_inactive_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_active_count | grep -Eo '[0-9]+' ) | awk '{s+=$1} END {print s}'");
                $memoryUsed = intval($totalPages * intval(shell_exec("pagesize")) / 1024 / 1024);
                break;
            default:
                throw new \RuntimeException('The sitezen telemetry connector does not currently support ' . PHP_OS_FAMILY);
        }


        return [
            'total' => $memoryTotal,
            'used' => $memoryUsed,
        ];
    }

    public static function storage(): array
    {
        $directory = $_SERVER['DOCUMENT_ROOT'] . '/';

        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = intval(round(disk_total_space($directory) / 1024 / 1024)); // MB
            $used = intval(round($total - (disk_free_space($directory) / 1024 / 1024))); // MB
            $result = [
                'total' => $total,
                'used' => $used,
                'cmd' => 'disk_total_space',

            ];
        } else {
            $result = self::getDiskSpaceDetails($directory);
        }

        return $result;

    }


    public static function getDiskSpaceDetails($directory = '/')
    {
        $output = [];
        exec("df -h '$directory'", $output); // Run the df command in human-readable format
        if (!empty($output) && count($output) > 1) {
            // skip first line
            array_shift($output);

            foreach ($output as $line) {
                // Split the line into columns by whitespace
                $columns = preg_split('/\s+/', $line);

                if (isset($columns[1], $columns[2])) {
                    $total = self::convertToMB($columns[1]); // Total size
                    $used = self::convertToMB($columns[2]); // Used size

                    return [
                        'total' => $total,
                        'used' => $used,
                        'cmd' => 'df -h',
                    ];
                }
            }
        }
        return [
            'total' => 0,
            'used' => 0,
            'message' => 'no disk space data retrievable'
        ];
    }

    public static function convertToMB($size)
    {
        $unit = strtoupper(substr($size, -2)); // Get last two characters for the unit (e.g., Gi, Mi, G, M)
        $value = (float)substr($size, 0, -2); // Get the numeric part

        // Handle cases where the unit is a single letter (e.g., G, M)
        if (!in_array($unit, ['GI', 'MI', 'TI', 'KI'], true)) {
            $unit = strtoupper(substr($size, -1)); // Adjust to single character
            $value = (float)substr($size, 0, -1); // Adjust the numeric part
        }

        // Convert units to MB
        switch ($unit) {
            case 'GI': // Gibibytes to Megabytes
            case 'G':  // Gigabytes to Megabytes
                return $value * 1024;
            case 'MI': // Mebibytes to Megabytes
            case 'M':  // Megabytes (already in MB)
                return $value;
            case 'TI': // Tebibytes to Megabytes
            case 'T':  // Terabytes to Megabytes
                return $value * 1024 * 1024;
            case 'KI': // Kibibytes to Megabytes
            case 'K':  // Kilobytes to Megabytes
                return $value / 1024;
            default: // If no unit or unknown unit, assume the value is in MB
                return $value;
        }
    }


    public static function getSystemUptime()
    {
        $bootTime = 0;


        switch (PHP_OS_FAMILY) {
            case 'Darwin':
            case 'BSD':
                $bootTime = shell_exec("sysctl -n kern.boottime | awk '{print $4}' | tr -d ','");
                if ($bootTime === null) {
                    throw new \RuntimeException('Failed to retrieve boot time on ' . PHP_OS_FAMILY);
                }
                break;
            case 'Linux':
                // Attempt to use uptime -s first
                $uptimeOutput = shell_exec("uptime -s");
                if ($uptimeOutput !== null) {
                    $bootTime = strtotime(trim($uptimeOutput));
                    if ($bootTime === false) {
                        throw new \RuntimeException('Failed to parse boot time from uptime command.');
                    }
                } else {
                    // Fallback to /proc/uptime if uptime -s is unavailable
                    if (is_readable('/proc/uptime')) {
                        $uptimeContents = file_get_contents('/proc/uptime');
                        if ($uptimeContents !== false) {
                            $uptimeSeconds = trim($uptimeContents);
                            $bootTime = time() - intval(explode(' ', $uptimeSeconds)[0]);
                        } else {
                            throw new \RuntimeException('Failed to read /proc/uptime on Linux.');
                        }
                    } else {
                        // Fallback to systemd-analyze
                        $systemdOutput = shell_exec("systemd-analyze");
                        if ($systemdOutput !== null) {
                            preg_match('/Bootup is ([\d.]+)s/', $systemdOutput, $matches);
                            if (!empty($matches)) {
                                $bootSeconds = floatval($matches[1]);
                                $bootTime = time() - $bootSeconds;
                            } else {
                                throw new \RuntimeException('Failed to parse boot time from systemd-analyze output.');
                            }
                        } else {
                            throw new \RuntimeException('Failed to retrieve boot time using all available methods.');
                        }
                    }
                }
                break;
            case 'Windows':
                $bootTimeRaw = shell_exec("wmic os get lastbootuptime | findstr /r /v \"^$\"");
                if ($bootTimeRaw !== null) {
                    $bootTimeRaw = trim($bootTimeRaw);
                    if (strlen($bootTimeRaw) >= 14) {
                        $year = substr($bootTimeRaw, 0, 4);
                        $month = substr($bootTimeRaw, 4, 2);
                        $day = substr($bootTimeRaw, 6, 2);
                        $hour = substr($bootTimeRaw, 8, 2);
                        $minute = substr($bootTimeRaw, 10, 2);
                        $second = substr($bootTimeRaw, 12, 2);
                        $bootTime = mktime($hour, $minute, $second, $month, $day, $year);
                    } else {
                        throw new \RuntimeException('Failed to parse boot time from WMIC output.');
                    }
                } else {
                    throw new \RuntimeException('Failed to retrieve boot time on Windows.');
                }
                break;
            default:
                throw new \RuntimeException('The sitezen telemetry connector does not currently support ' . PHP_OS_FAMILY);
        }

        // If $bootTime is successfully set, format it to a readable date
        // if (isset($bootTime)) {
        //    echo 'Boot Time: ' . date('Y-m-d H:i:s', $bootTime);
        //}


        $uptimeSeconds = ($bootTime === false) ? -1 : time() - intval($bootTime);
        return $uptimeSeconds;
    }

    /*
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public static function printTree(array $tree, string $indent = ''): void
    {
        echo $indent . self::formatSize($tree['size']) . " - " . $tree['name'] . PHP_EOL;
        if (!empty($tree['children'])) {
            foreach ($tree['children'] as $child) {
                self::printTree($child, $indent . '  ');
            }
        }
    }
    */

    public static function diskUsage($config): array
    {
        $rootPath = $config['rootPath'] ?? '';
        $rootPath = ($rootPath === '') ? $_SERVER['DOCUMENT_ROOT'] : $rootPath;
        $tree = self::diskAnalyzer($rootPath, $rootPath);
        return $tree;
    }

    private static function diskAnalyzer(string $path, string $rootPath)
    {
        if (!is_readable($path)) {
            return null;
        }

        $size = 0;
        $name = basename($path);

        if (is_file($path)) {
            $fileSize = filesize($path) ?: 0;
            $fileCtime = filectime($path) ?: null;
            return ['n' => $name, 's' => round($fileSize / 1024, 2), 'd' => $fileCtime];
        }

        if (is_dir($path)) {
            $folderSize = 0;
            $children = [];

            foreach (scandir($path) as $item) {
                if ($item !== '.' && $item !== '..') {
                    $child = self::diskAnalyzer($path . DIRECTORY_SEPARATOR . $item, $rootPath);
                    if ($child) {
                        $folderSize += $child['s'];
                        $children[] = $child;
                    }
                }
            }

            $result = ['n' => $name, 's' => $folderSize];

            if (!empty($children)) {
                $result['c'] = $children; // Add children only if not empty
            }

            return $result;
        }

        return null;
    }


    public static function getSystemInformation()
    {
        switch (PHP_OS_FAMILY) {
            case 'Linux':
                return [
                    'os' => 'Linux',
                    'linux_version' => shell_exec("cat /etc/*release"),
                    'open_ports' => shell_exec("netstat -tuln | grep LISTEN"),
                    'cpu_info' => shell_exec("lscpu"),
//                    'memory_info' => shell_exec("free -h"),
//                    'disk_usage' => shell_exec("df -h"),
//                    'processes' => shell_exec("ps aux")
                ];
            case 'Darwin':
                return [
                    'os' => 'Darwin (macOS)',
                    'macos_version' => shell_exec("sw_vers"),
                    'open_ports' => shell_exec("netstat -an | grep LISTEN"),
                    'cpu_info' => shell_exec("sysctl -n machdep.cpu.brand_string"),
//                    'memory_info' => shell_exec("vm_stat"),
//                    'disk_usage' => shell_exec("df -h"),
//                    'processes' => shell_exec("ps aux")
                ];
            case 'Windows':
                return [
                    'os' => 'Windows',
                    'windows_version' => shell_exec("systeminfo | findstr /B /C:\"OS Name\" /C:\"OS Version\""),
                    'open_ports' => shell_exec("netstat -an | findstr LISTENING"),
                    'cpu_info' => shell_exec("wmic cpu get caption"),
//                    'memory_info' => shell_exec("systeminfo | findstr /C:\"Total Physical Memory\" /C:\"Available Physical Memory\""),
//                    'disk_usage' => shell_exec("wmic logicaldisk get size,freespace,caption"),
//                    'processes' => shell_exec("tasklist")
                ];
            case 'BSD':
                return [
                    'os' => 'BSD',
                    'bsd_version' => shell_exec("uname -a"),
                    'open_ports' => shell_exec("netstat -an | grep LISTEN"),
                    'cpu_info' => shell_exec("sysctl -n hw.model"),
//                    'memory_info' => shell_exec("sysctl -n hw.physmem"),
//                    'disk_usage' => shell_exec("df -h"),
//                    'processes' => shell_exec("ps aux")
                ];
            default:
                throw new \RuntimeException('The sitezen telemetry connector does not currently support ' . PHP_OS_FAMILY);
        }
    }

    private static function getCpuCoreCount(): int
    {
        $counter = new CpuCoreCounter();

        $counter->getAvailableForParallelisation()->availableCpus;

        try {
            $counter->getCount();   // e.g. 8
        } catch (NumberOfCpuCoreNotFound) {
            return 1;   // Fallback value
        }

        return $counter->getCount();
    }

    public static function system(): array
    {
        $message_uptime = '';
        try {
            $uptimeSeconds = self::getSystemUptime();
        } catch (\RuntimeException $e) {
            $message_uptime = $e->getMessage();
            $uptimeSeconds = -1;
        }

        $message_info = '';
        try {
            $systemInformation = self::getSystemInformation();
        } catch (\RuntimeException $e) {
            $message_info = $e->getMessage();
            $systemInformation = [];
        }

        $cpuCoreCount = self::getCpuCoreCount();


        return [
            'uptime' => $uptimeSeconds,
            'name' => $_SERVER['SERVER_NAME'],
            'host' => gethostname(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'os_family' => PHP_OS_FAMILY,
            'software' => $_SERVER['SERVER_SOFTWARE'],
            'system_info' => $systemInformation,
            'cpu_core_count' => $cpuCoreCount,
            'message' => [
                'systemUptime' => $message_uptime,
                'systemInformation' => $message_info,
            ],
        ];
    }

    public static function php(): array
    {
        $phpOptions = ['php_version', 'memory_limit', 'max_execution_time', 'default_socket_timeout', 'opcache.enable', 'post_max_size'];

        $iniOptions = ini_get_all(null, false);
        $iniOptions['php_version'] = phpversion();
        $iniOptions['loaded_extensions'] = get_loaded_extensions();

        return array_intersect_key($iniOptions, array_flip($phpOptions));
    }

    /*
                 'phpVersion'          => PHP_VERSION,
            'mysqlVersion'        => $context->getDb()->db_version(),
            'extensionPdoMysql'   => extension_loaded('pdo_mysql'),
            'extensionOpenSsl'    => extension_loaded('openssl'),
            'extensionFtp'        => extension_loaded('ftp'),
            'extensionZlib'       => extension_loaded('zlib'),
            'extensionBz2'        => extension_loaded('bz2'),
            'extensionZip'        => extension_loaded('zip'),
            'extensionCurl'       => extension_loaded('curl'),
            'extensionGd'         => extension_loaded('gd'),
            'extensionImagick'    => extension_loaded('imagick'),
            'extensionSockets'    => extension_loaded('sockets'),
            'extensionSsh2'       => extension_loaded('ssh2'),
            'shellAvailable'      => mwp_is_shell_available(),
            'safeMode'            => mwp_is_safe_mode(),
            'memoryLimit'         => mwp_format_memory_limit(ini_get('memory_limit')),
            'disabledFunctions'   => mwp_get_disabled_functions(),
            'processArchitecture' => strlen(decbin(~0)), // Results in 32 or 62.
            'internalIp'          => $this->container->getRequestStack()->getMasterRequest()->server['SERVER_ADDR'],
            'uname'               => php_uname('a'),
            'hostname'            => php_uname('n'),
            'os'                  => (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'windows' : 'unix',
     */

    function get_disabled_functions()
    {
        $list = array_merge(explode(',', ini_get('disable_functions')), explode(',', ini_get('suhosin.executor.func.blacklist')));
        $list = array_map('trim', $list);
        $list = array_map('strtolower', $list);
        $list = array_filter($list);

        return $list;
    }

    function get_is_safe_mode()
    {
        $value = ini_get("safe_mode");
        if ((int)$value === 0 || strtolower($value) === "off") {
            return false;
        }

        return true;
    }

}