<?php
/**
 * MythWeb Streaming/Download module
 *
 * @license     GPL
 * @package     MythWeb
 * @subpackage  Stream
 */

// 1. Extract path arguments
$chanid    = $Path[count($Path) - 2] ?? null;
$starttime = end($Path) ?? null;

// Strip extension suffix if present in $starttime parameter (e.g., 1786602480.mpg)
if (!empty($starttime)) {
    $starttime = preg_replace('/\.\w+$/', '', $starttime);
}

// 2. Query MythTV DB to resolve $basename and details using MythWeb's $db object
if (!empty($chanid) && !empty($starttime) && isset($db)) {
    // Query recorded table
    $sh = $db->query('SELECT basename, title, subtitle, season, episode
                        FROM recorded
                       WHERE starttime = FROM_UNIXTIME('.$db->escape($starttime).')
                         AND chanid = '.$db->escape($chanid));

    if ($row = $sh->fetch_assoc()) {
        $basename = $row['basename'];
        $title    = $row['title'];
        $subtitle = $row['subtitle'];
        $season   = $row['season'];
        $episode  = $row['episode'];
    }
    $sh->finish();

    // 3. Resolve absolute file path on disk across MythTV Storage Groups
    if (!empty($basename)) {
        $sh = $db->query('SELECT DISTINCT dirname FROM storagegroup');
        while ($row = $sh->fetch_assoc()) {
            $dir = rtrim($row['dirname'], '/');
            if (file_exists("$dir/$basename")) {
                $filename = "$dir/$basename";
                break;
            }
        }
        $sh->finish();
    }
}

// 4. Validate resolved recording file
if (empty($filename) || empty($basename)) {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: text/plain; charset=UTF-8");
    echo "$basename does not exist in any recognized storage group directories for this host.";
    exit;
}

// 5. Inspect Request URI & Delegate to Streaming Sub-handler
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (preg_match('/\.asx$/i', $requestUri)) {
    require_once "modules/stream/stream_asx.php";
} elseif (preg_match('/\.webm$/i', $requestUri)) {
    require_once "modules/stream/stream_webm.php";
} else {
    require_once "modules/stream/stream_raw.php";
}

/**
 * Escape a parameter for safe use in a command-line call
 *
 * @param string $str
 * @return string
 */
function shell_escape(string $str): string
{
    return "'" . str_replace("'", "'\\''", $str) . "'";
}