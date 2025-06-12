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

namespace SiteZen\SiteZenOxid\Service;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

/**
 * Service for handling webhook token
 */
class TokenService implements TokenServiceInterface
{
    /**
     * Module ID
     */
    private const MODULE_ID = 'sitezen';

    /**
     * Token setting name
     */
    private const TOKEN_SETTING = 'sWebhookToken';

    /**
     * @var ModuleSettingServiceInterface
     */
    private $moduleSettingService;

    /**
     * Constructor
     *
     * @param ModuleSettingServiceInterface $moduleSettingService
     */
    public function __construct(ModuleSettingServiceInterface $moduleSettingService)
    {
        $this->moduleSettingService = $moduleSettingService;
    }

    /**
     * Get the configured webhook token
     *
     * @return string The webhook token
     */
    public function getToken(): string
    {
        return $this->moduleSettingService->getString(self::TOKEN_SETTING, self::MODULE_ID)->trim()->toString();
    }


}
