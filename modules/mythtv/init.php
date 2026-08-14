<?php
/**
 * This is pretty much a dummy module to hold the settings sections that
 * pertain to Mythbackend and other things not actually present within MythWeb
 * itself.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 **/

    $Settings['mythtv'] = [
        'name'    => t('MythTV'),
        'choices' => [
            'settings' => t('Settings Table'),
            'keys'     => t('Key Bindings'),
        ],
        'default' => 'settings',
    ];