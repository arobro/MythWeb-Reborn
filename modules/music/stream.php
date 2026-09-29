<?php
/**
 * Stream a music file
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Music
 **/

// Yes, a db connection
    global $db;

// No timeouts
    set_time_limit(0);

// Validate input parameter types
    $art_id  = filter_input(INPUT_GET, 'a', FILTER_VALIDATE_INT);
    $song_id = filter_input(INPUT_GET, 'i', FILTER_VALIDATE_INT);

    $path        = null;
    $fname       = null;
    $xml_command = null;
    $xml_id      = null;

// Album art?
    if ($art_id) {
        list($path, $fname) = $db->query_row('SELECT md.path, ma.filename
                                                FROM music_albumart AS ma
                                                     LEFT JOIN music_directories AS md
                                                            ON ma.directory_id=md.directory_id
                                               WHERE ma.albumart_id = ?',
                                             $art_id);
        $xml_command = 'Content/GetAlbumArt';
        $xml_id      = $art_id;
    }
// Music?
    elseif ($song_id) {
        list($path, $fname) = $db->query_row('SELECT md.path, ms.filename
                                                FROM music_songs AS ms
                                                     LEFT JOIN music_directories AS md
                                                            ON ms.directory_id=md.directory_id
                                               WHERE ms.song_id = ?',
                                             $song_id);

        $db->query('UPDATE music_songs
                       SET numplays = numplays + 1, lastplay = NOW()
                     WHERE song_id = ?',
                   $song_id);

        $xml_command = 'Content/GetMusic';
        $xml_id      = $song_id;
    }

// Unknown request or empty file?
    if (empty($fname)) {
        exit;
    }

    $safe_fname = str_replace(array("\r", "\n", '"'), '', basename($fname));

// HTTP stream redirect restriction
    if (preg_match('#^\w+://#', $fname)) {
        // Ensure redirect is only permitted for allowed protocols/hosts
        if (preg_match('#^https?://#i', $fname)) {
            redirect_browser($fname);
        }
        exit;
    }

// Base music path setup
    $basepath = realpath(setting('MusicLocation', hostname));
    if (!$basepath) {
        exit;
    }

// Resolve real target file path to prevent Path Traversal
    $target_file = realpath($basepath . '/' . $path . '/' . $fname);

    if ($target_file && strpos($target_file, $basepath) === 0 && file_exists($target_file)) {
        
        // Dynamic MIME type detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $target_file) ?: 'application/octet-stream';
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $safe_fname . '"');
        header('Content-Length: ' . filesize($target_file));

        // Clear output buffer prior to reading binary stream
        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($target_file);
    }
// Otherwise, route via backend
    else {
        $Master_Host = setting('MasterServerIP');
        $port = _or(get_backend_setting('BackendStatusPort', $Master_Host),
                    get_backend_setting('BackendStatusPort'));

        if (stripos($Master_Host, ':') !== false) {
            $Master_Host = '[' . $Master_Host . ']';
        }

        $backend_url = sprintf(
            'http://%s:%d/%s?Id=%d',
            $Master_Host,
            (int)$port,
            urlencode($xml_command),
            (int)$xml_id
        );

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: inline; filename="' . $safe_fname . '"');

        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($backend_url);
    }

    exit;