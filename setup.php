<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2024-2026 pbolkhovitin
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of SimplePortal plugin for GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ---------------------------------------------------------------------
 */

define('PLUGIN_SIMPLEPORTAL_VERSION', '1.0.0');

function plugin_version_simpleportal(): array
{
    return [
        'name'           => 'SimplePortal',
        'version'        => PLUGIN_SIMPLEPORTAL_VERSION,
        'author'         => 'pbolkhovitin',
        'license'        => 'GPL v3+',
        'homepage'       => 'https://github.com/pbolkhovitin/glpi-simpleportal',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0',
            ],
            'php'  => [
                'min' => '8.1',
            ],
        ],
    ];
}

function plugin_simpleportal_install(): bool
{
    $config = new \Config();
    $config->setConfigurationValues('simpleportal', [
        'api_url'         => '',
        'api_app_token'   => '',
        'api_user_token'  => '',
    ]);
    return true;
}

function plugin_simpleportal_uninstall(): bool
{
    $config = new \Config();
    $config->deleteConfigurationValues('simpleportal');
    return true;
}
