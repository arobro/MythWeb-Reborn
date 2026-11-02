<?php
/**
 * Proxies requests to ttvdb.com so that we can grab images from their site
 * Tries to avoid being a total open proxy by only proxying to thetvdb.com
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

    header('Content-Type: image/jpeg');

    $url = $_REQUEST['url'] ?? '';

    if (strpos($url, '..') !== false || strpos($url, '//') !== false) {
        exit('Invalid URL');
    }

    $image = @file_get_contents("https://www.thetvdb.com" . $url);

    if ($image !== false) {
        echo $image;
    }