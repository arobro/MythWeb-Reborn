<?php
/**
 * Display/save mythweb default settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
        $width    = (int)($_POST['width'] ?? 0);
        $vbitrate = (int)($_POST['vbitrate'] ?? 0);
        $abitrate = (int)($_POST['abitrate'] ?? 0);

    // Validation
        if ($width < 1)
            $width = 320;
        elseif ($width < 160)
            $width = 160;

    // Turn on/off
        setting('WebFLV_on', null, !empty($_POST['flvplayer']) ? 1 : 0);
    // Dimensions...
        setting('WebFLV_w',  null, $width);
    // Bitrates
        setting('WebFLV_vb', null, $vbitrate > 0 ? $vbitrate : 256);
        setting('WebFLV_ab', null, $abitrate > 0 ? $abitrate : 64);

    }

// These settings are limited to MythWeb itself
    $Settings_Hosts = 'MythWeb';