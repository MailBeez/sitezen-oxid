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

/**
 * Interface for token service
 */
interface TokenServiceInterface
{
    /**
     * Get the configured webhook token
     *
     * @return string The webhook token
     */
    public function getToken(): string;


}
