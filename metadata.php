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

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id'          => 'sitezen',
    'title'       => [
        'de' => 'SiteZen Modul',
        'en' => 'SiteZen Module',
    ],
    'description' => [
        'de' => 'Anbindung für SiteZen.io',
        'en' => 'This module provides a webhook endpoint and allows configuration of a token',
    ],
    'thumbnail'   => 'logo.png',
    'version'     => '1.4.0',
    'author'      => 'SiteZen by MailBeez',
    'url'         => 'https:/sitezen.io',
    'email'       => 'hello@sitezen.io',
    'settings'    => [
        [
            'group' => 'sitezen_settings',
            'name'  => 'sWebhookToken',
            'type'  => 'str',
            'value' => ''
        ],
    ],
    'controllers' => [
        'sitezen' => \SiteZen\SiteZenOxid\Controller\WebhookController::class,
    ],
];
