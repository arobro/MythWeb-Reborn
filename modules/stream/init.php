<?php
/**
 * This is pretty much a dummy module to hold the settings sections that
 * pertain to Mythbackend and other things not actually present within MythWeb
 * itself.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Stream
 **/

    $Settings['stream'] = [
        'name'    => t('Streaming'),
        'choices' => [
            'protocol' => t('Protocol'),
        ],
        'default' => 'protocol',
    ];

    $Modules['stream'] = [
        'path'   => 'stream',
        'hidden' => true,
    ];