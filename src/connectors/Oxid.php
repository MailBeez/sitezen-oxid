<?php

namespace SiteZen\Telemetry\Connectors;

class Oxid
{
    static function applicationData($config): array|string
    {

        // Get Oxid version
        $version = \OxidEsales\Eshop\Core\ShopVersion::getVersion();
//        print_r($version); exit();
        // Get configuration
        $oxConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $shopUrl = $oxConfig->getShopUrl();
        $shopId = $oxConfig->getShopId();
        $isProductiveMode = $oxConfig->isProductiveMode();

        // Get some important configuration parameters
        $configParams = [
            'shopUrl' => $shopUrl,
            'shopId' => $shopId,
            'isProductiveMode' => $isProductiveMode,
//            'adminEmail' => $oxConfig->getConfigParam('sAdminEmail'),
//            'shopName' => $oxConfig->getConfigParam('sShopName'),
//            'shopVersion' => $version,
            'edition' => $oxConfig->getFullEdition(),
        ];
        // Get modules information
//        $moduleList = oxNew(\OxidEsales\Eshop\Core\Module\ModuleList::class);
//        $modules = [];
//        // Get active modules
//        $activeModules = $oxConfig->getConfigParam('aModules');
//        if (is_array($activeModules)) {
//            foreach ($activeModules as $class => $moduleExtensions) {
//                $modules[$class] = [
//                    'extensions' => $moduleExtensions
//                ];
//            }
//        }
//
//        // Get disabled module classes
//        $disabledModules = $moduleList->getDisabledModuleClasses();

        $result = [
            'platform' => 'oxid',
            'version' => $version,
            'is_cloud' => false,
            'configuration' => $configParams,
            'plugins' => [],
            ...self::systemInfo($config),

        ];

        return $result;
    }

    static function adminUsers(array $config)
    {
        // Create a UserList instance
        $userList = oxNew(\OxidEsales\Eshop\Application\Model\UserList::class);

        // Select all admin users (users with OXRIGHTS not equal to 'user')
        $query = "SELECT * FROM oxuser WHERE oxrights != 'user'";
        $userList->selectString($query);

        $admin_users = [];

        // Process each admin user
        foreach ($userList as $user) {
            $admin_users[] = [
                'id' => bin2hex($user->getId()),
                'username' => $user->oxuser__oxusername->value,
                'email' => self::obfuscate_email($user->oxuser__oxusername->value),
                'email_hash' => hash('sha256', trim(strtolower($user->oxuser__oxusername->value))),
                'active' => (bool)$user->oxuser__oxactive->value,
                'created_at' => $user->oxuser__oxregister->value,
                'updated_at' => $user->oxuser__oxtimestamp->value
            ];
        }

        return [
            'admin_users' => $admin_users
        ];
    }

    static function obfuscate_email($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email';
        }

        [$user, $domain] = explode('@', $email);

        // Show first and last character, replace middle with asterisks
        $obfuscated_user = substr($user, 0, 2) . str_repeat('*', max(1, strlen($user) - 3)) . substr($user, -1);

        return $obfuscated_user . '@' . $domain;
    }

    static function systemInfo($config): array|string
    {
        // Create a SystemRequirements instance
        $sysReq = oxNew(\OxidEsales\Eshop\Core\SystemRequirements::class);

        // Get system requirements data
        $systemInfo = $sysReq->getSystemInfo();

        // Transform numeric status values (2) to boolean values (true)
        $systemInfo = \OxidEsales\Eshop\Core\SystemRequirements::filter(
            $systemInfo,
            function ($groupId, $moduleId, $moduleState) {
                // Convert status value 2 to boolean true
                return $moduleState === 2 ? true : $moduleState;
            }
        );

        // Function to replace incorrect key names
        $replaceKeys = function($source) {

            $map = [
                'j_son' => 'json',
                'i_conv' => 'iconv',
                'mb_string' => 'mbstring',
                'bc_math' => 'bcmath',
                'open_ssl' => 'openssl',
                'php_xml' => 'dom',
                'mysql_connect' => 'pdo_mysql',
                'gd_info' => 'gd',
            ];

            $renamed = [];
            foreach ($source as $key => $value) {
                $renamed[$map[$key] ?? $key] = $value;   // fall back to old key if no map entry
            }
            return $renamed;

        };

        // Fix key names in php_config array
        if (isset($systemInfo['php_config'])) {
            $systemInfo['php_config'] = $replaceKeys($systemInfo['php_config']);
        }

        // Fix key names in php_extennsions array
        if (isset($systemInfo['php_extennsions'])) {
            $systemInfo['php_extennsions'] = $replaceKeys($systemInfo['php_extennsions']);
        }

        // Get permission issues list to help diagnose server_permissions issues
        $permissionIssues = $sysReq->getPermissionIssuesList();

        return [
            'system_info' => [
                'server_configuration' => $systemInfo['server_config'],
                'php_configuration' => $systemInfo['php_config'],
                'php_extensions' => $systemInfo['php_extennsions'],
                'security' => [
                    'config_not_writable' => !is_writable(getShopBasePath() . "config.inc.php"),
                ],
                'files' => [
                    'missing' => $permissionIssues['missing'],
                    'not_writable' => $permissionIssues['not_writable'],
                ]
            ]
        ];
    }
}
