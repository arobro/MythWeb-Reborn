<?php
/**
 * Display/save recommendation settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
        setting('recommend_enabled', null, isset($_POST['recommend_enabled']) ? $_POST['recommend_enabled'] : 0);
        setting('recommend_server', null, $_POST['recommend_server'] ?? null);
        setting('recommend_key', null, $_POST['recommend_key'] ?? null);
    }

// These settings are limited to MythWeb itself
    $Settings_Hosts = 'MythWeb';