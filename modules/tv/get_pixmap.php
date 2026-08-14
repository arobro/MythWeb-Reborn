<?php
/**
 * Get a pixmap
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

    $hostname   = $Path[2] ?? null;
    $chanid     = $Path[3] ?? null;
    $starttime  = $Path[4] ?? 0;
    $width      = $Path[5] ?? null;
    $height     = $Path[6] ?? null;
    $seconds_in = $Path[7] ?? null;

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $starttime) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }
    }

    $data = Program::get_preview_pixmap($hostname, $chanid, $starttime, $width, $height, $seconds_in);
    if ($data !== null && strlen((string)$data) > 0) {
        header('Pragma: public', true);
        header('Content-Type: image/png');
        header("Content-Length: ".strlen((string)$data));
        header("Cache-Control: max-age=".(7*24*60*60*60).", public");
        header("Last-Modified: ".gmdate("D, d M Y H:i:s", intval($starttime))." GMT");
        header("Expires: ".gmdate("D, d M Y H:i:s", intval($starttime) + (7*24*60*60*60))." GMT");

        echo $data;
    }
    else
        header("Status: 404 Not Found");

    exit;