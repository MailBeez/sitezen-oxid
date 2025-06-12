<?php
/**
 * This file is part of SiteZen Module.
 *
 * SiteZen Module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SiteZen Module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SiteZen Module.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      https://www.oxid-sales.com
 * @copyright (C) OXID-Sales 2023
 */

namespace SiteZen\SiteZenOxid\Controller;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use SiteZen\SiteZenOxid\Service\TokenServiceInterface;
use SiteZen\Telemetry\Bootstrap;
use SiteZen\Telemetry\Connectors\Application;


/**
 * Controller for handling webhook requests
 */
class WebhookController extends \OxidEsales\Eshop\Core\Controller\BaseController
{
    /**
     * @var TokenServiceInterface
     */
    private $tokenService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Initialize the controller
     * This method is called by OXID framework after the controller is instantiated
     */
    public function init()
    {
        parent::init();
        $this->tokenService = $this->getContainer()->get(TokenServiceInterface::class);
    }

    /**
     * Get the DI container
     *
     * @return \Psr\Container\ContainerInterface
     */
    protected function getContainer()
    {
        return ContainerFactory::getInstance()->getContainer();
    }

    /**
     * Main entry point for webhook requests
     *
     * @return string
     */
    public function render()
    {
        // Disable template rendering
        $this->_sThisTemplate = null;

        // Prevent any further template processing
        $this->_blIsComponent = false;


        $this->handleRequest();


        // This return is just to satisfy the method signature
        return '';
    }

    /**
     * Get request data from the input stream
     *
     * @return array
     */
    protected function getRequestData(): array
    {
        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $data ?? [];
    }

    /**
     * Send a success response
     *
     * @param array $data Response data
     * @return string JSON response
     */
    protected function sendSuccessResponse(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Send an error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return string JSON response
     */
    protected function sendErrorResponse(string $message, int $statusCode = 400): string
    {
        // Set HTTP status code
        Registry::getUtils()->setHeader("HTTP/1.1 " . $statusCode);

        return json_encode([
            'status' => 'error',
            'message' => $message
        ]);
    }

    /**
     * Output JSON response and exit
     *
     * @param string $jsonResponse The JSON response string
     * @return void
     */
    protected function outputJsonAndExit(string $jsonResponse): void
    {
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Output the JSON response
        echo $jsonResponse;

        // Exit to prevent any further processing
        exit();
    }


    protected function handleRequest() {

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        define('SITEZEN_CONNECTOR_VERSION', '1.4.0');
        define('SITEZEN_CONNECTOR_TYPE', 'oxid');


        include __DIR__ . '/../Sitezen/Bootstrap/bootstrap.php';
        include __DIR__ . '/../connectors/Application.php';
        include __DIR__ . '/../connectors/Oxid.php';


// locate env file and map env settings
        $envFilePath = __DIR__  . '/data/.sitezen.env.php';



        $config = [
            'token' => $this->tokenService->getToken(),
            'platform' => 'oxid',
            'rootPath' => Registry::getConfig()->getConfigParam('sShopDir'),
            'host' => Registry::getConfig()->getConfigParam('dbHost'),
            'username' => Registry::getConfig()->getConfigParam('dbUser'),
            'password' => Registry::getConfig()->getConfigParam('dbPwd'),
            'database' => Registry::getConfig()->getConfigParam('dbName'),
            'port' => Registry::getConfig()->getConfigParam('dbPort'),
            'socket' => Registry::getConfig()->getConfigParam('dbUnixSocket')
        ];

        // Process the webhook request
        $data = $this->getRequestData();

// check gate
        Bootstrap\Gate::check($config);

// start performance measurement
        Bootstrap\Performance::start();

// initialize process handler
        $process = new Bootstrap\Process($config);
// register plugin handlers
        $process->registerHandler('application:data', fn($request) => Application::applicationData($config));
        $process->registerHandler('threats:admin_users', fn($request) => Application::threatsUserData($config));


        $data = $process->executeHandler();

// return data as json
        (new Bootstrap\Data($data, Bootstrap\Performance::end()))->output();
        exit();
    }
}
