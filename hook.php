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
 * ---------------------------------------------------------------------
 */

function plugin_init_simpleportal(): void
{
    global $PLUGIN_HOOKS;

    $plugin = new \Plugin();
    if (!$plugin->isActivated('simpleportal')) {
        return;
    }

    $PLUGIN_HOOKS['menu_toadd']['simpleportal'] = [];

    if (\Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::CONFIG_PAGE]['simpleportal'] = 'config';
    }
}

function plugin_simpleportal_boot(): void
{
    \Glpi\Http\SessionManager::registerPluginStatelessPath('simpleportal', '#^/$#');
}
