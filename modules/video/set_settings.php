<?php
/**
 * Video settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Weather
 **/

// Load all of the known mythtv frontend hosts
    $Settings_Hosts = array();
    $sh = $db->query('SELECT DISTINCT hostname
                        FROM settings
                       WHERE LENGTH(hostname) > 0
                    ORDER BY hostname');
    while (list($host) = $sh->fetch_row())
        $Settings_Hosts[$host] = $host;
    $sh->finish();

// Make sure we have a valid host selected
    $current_host = $_SESSION['settings']['host'] ?? null;
    if (!isset($Settings_Hosts[$current_host])) {
        $keys = array_keys($Settings_Hosts);
        $_SESSION['settings']['host'] = reset($keys);
    }

// Make sure the session host gets updated to the posted one.
    if (isset($_POST['host']))
        $_SESSION['settings']['host'] = $_POST['host'];

    if (isset($_POST['save']) && $_POST['save']) {
        setting('web_video_imdb_path',          $_SESSION['settings']['host'], $_POST['web_video_imdb_path'] ?? null);
        setting('web_video_imdb_type',          $_SESSION['settings']['host'], $_POST['web_video_imdb_type'] ?? null);
        setting('VideoAggressivePC',            $_SESSION['settings']['host'], $_POST['VideoAggressivePC'] ?? null);
        setting('VideoArtworkDir',              $_SESSION['settings']['host'], $_POST['VideoArtworkDir'] ?? null);
        setting('VideoBrowserNoDB',             $_SESSION['settings']['host'], $_POST['VideoBrowserNoDB'] ?? null);
        setting('VideoDefaultParentalLevel',    $_SESSION['settings']['host'], $_POST['VideoDefaultParentalLevel'] ?? null);
        setting('VideoDefaultPlayer',           $_SESSION['settings']['host'], $_POST['VideoDefaultPlayer'] ?? null);
        setting('VideoGalleryAspectRatio',      $_SESSION['settings']['host'], $_POST['VideoGalleryAspectRatio'] ?? null);
        setting('VideoGalleryColsPerPage',      $_SESSION['settings']['host'], $_POST['VideoGalleryColsPerPage'] ?? null);
        setting('VideoGalleryNoDB',             $_SESSION['settings']['host'], $_POST['VideoGalleryNoDB'] ?? null);
        setting('VideoGalleryRowsPerPage',      $_SESSION['settings']['host'], $_POST['VideoGalleryRowsPerPage'] ?? null);
        setting('VideoGallerySubtitle',         $_SESSION['settings']['host'], $_POST['VideoGallerySubtitle'] ?? null);
        setting('VideoListUnknownFiletypes',    $_SESSION['settings']['host'], $_POST['VideoListUnknownFiletypes'] ?? null);
        setting('VideoNewBrowsable',            $_SESSION['settings']['host'], $_POST['VideoNewBrowsable'] ?? null);
        setting('VideoStartupDir',              $_SESSION['settings']['host'], $_POST['VideoStartupDir'] ?? null);
        setting('VideoTreeLoadMetaData',        $_SESSION['settings']['host'], $_POST['VideoTreeLoadMetaData'] ?? null);
        setting('VideoTreeNoDB',                $_SESSION['settings']['host'], $_POST['VideoTreeNoDB'] ?? null);
        setting('Default MythVideo View',       $_SESSION['settings']['host'], $_POST['DefaultMythVideoView'] ?? null);
        setting('mythvideo.sort_ignores_case',  $_SESSION['settings']['host'], $_POST['mythvideosort_ignores_case'] ?? null);
        setting('mythvideo.db_folder_view',     $_SESSION['settings']['host'], $_POST['mythvideodb_folder_view'] ?? null);
        setting('mythvideo.ImageCacheSize',     $_SESSION['settings']['host'], $_POST['mythvideoImageCacheSize'] ?? null);
        setting('web_video_thumbnail_height',   $_SESSION['settings']['host'], $_POST['web_video_thumbnail_height'] ?? null);
        setting('web_video_thumbnail_width',    $_SESSION['settings']['host'], $_POST['web_video_thumbnail_width'] ?? null);
    }

    $imdb_path = setting('web_video_imdb_path', $_SESSION['settings']['host']);
// If the path is not set, we check the usual suspects...
    if (!$imdb_path && $_SESSION['settings']['host'] == hostname) {
        if (file_exists('/usr/share/mythtv/mythvideo/scripts/imdb.pl'))
            $imdb_path = '/usr/share/mythtv/mythvideo/scripts/imdb.pl';
        elseif (file_exists('/usr/local/share/mythtv/mythvideo/scripts/imdb.pl'))
            $imdb_path = '/usr/local/share/mythtv/mythvideo/scripts/imdb.pl';
    }