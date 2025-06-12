<?php
// Bootstrap/bootstrap.php
// Includes
include __DIR__ . '/helper.php';
include __DIR__ . '/gate.php';
include __DIR__ . '/config.php';
include __DIR__ . '/performance.php';
include __DIR__ . '/env.php';
include __DIR__ . '/process.php';
include __DIR__ . '/data.php';

// default connectors
include __DIR__ . '/../Connectors/Database.php';
include __DIR__ . '/../Connectors/Server.php';

if (!defined('SITEZEN_CONNECTOR_VERSION')) {
    define('SITEZEN_CONNECTOR_VERSION', '1.3.2');
}
if (!defined('SITEZEN_CONNECTOR_TYPE')) {
    define('SITEZEN_CONNECTOR_TYPE', 'generic');
}

header('X-SiteZen-Version: '. SITEZEN_CONNECTOR_VERSION);
header('X-SiteZen-Type: '. SITEZEN_CONNECTOR_TYPE);