<?php
/**
 * Does a query against the backend to look up metadata for a show
 * returns the result as JSON
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

    header('Content-Type: application/json');

    $url = "Video/LookupVideo";
    $args = array( 
                   'Title'        => isset($_REQUEST['title']) ? $_REQUEST['title'] : null,
                   'Subtitle'     => isset($_REQUEST['subtitle']) ? $_REQUEST['subtitle'] : null,
                   'Inetref'      => isset($_REQUEST['inetref']) ? $_REQUEST['inetref'] : null,
                   'Season'       => isset($_REQUEST['season']) ? $_REQUEST['season'] : null,
                   'Episode'      => isset($_REQUEST['episode']) ? $_REQUEST['episode'] : null,
                   'GrabberType'  => isset($_REQUEST['grabbertype']) ? $_REQUEST['grabbertype'] : null,
                   'AllowGeneric' => isset($_REQUEST['allowgeneric']) ? $_REQUEST['allowgeneric'] : null);

    echo MythBackend::find()->httpRequestAsJson($url, $args);