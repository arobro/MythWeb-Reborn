<?php
/**
 * MythWeb Coverart/Download module
 *
 * @license     GPL
 * @package     MythWeb
 * @subpackage  Coverart
 */

// 1. Extract cover parameter
$cover = $_GET['cover'] ?? null;
if (!empty($Path[1])) {
    $cover = $Path[1];
}

// No match?
if (empty($cover) || !preg_match('/\w/', $cover)) {
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Unknown cover requested.\n";
    exit;
}

// 2. Find local file across MythTV Storage Groups using MythWeb's $db object
$filename = null;
if (isset($db)) {
    $sh = $db->query('SELECT dirname FROM storagegroup WHERE groupname = "Coverart"');
    while ($row = $sh->fetch_assoc()) {
        $coverart_dir = rtrim($row['dirname'], '/');
        if (file_exists("$coverart_dir/$cover")) {
            $filename = "$coverart_dir/$cover";
            break;
        }
    }
    $sh->finish();
}

if (empty($filename)) {
    header("Content-Type: text/plain; charset=UTF-8");
    echo "$cover does not exist in any recognized storage group directories for this host.";
    exit;
}

// 3. File size check
$size = filesize($filename);
if ($size < 1) {
    header("Content-Type: text/plain; charset=UTF-8");
    echo "$cover is an empty file.";
    exit;
}

// 4. Validate file type
$type = 'text/html';
if (preg_match('/\.jpg$/i', $cover)) {
    $type = 'image/jpeg';
} else {
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Unknown image type requested:  $cover\n";
    exit;
}

// 5. Open file for reading
$fp = fopen($filename, 'rb');
if (!$fp) {
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Can't read $cover";
    exit;
}

$start      = 0;
$end        = $size - 1;
$total_size = $size;
$read_size  = 1024;
$mtime      = filemtime($filename);

// 6. Handle HTTP Caching (If-Modified-Since)
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $check_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    if ($mtime <= $check_time) {
        header("HTTP/1.1 304 Not Modified");
        header("Content-Type: $type");
        fclose($fp);
        exit;
    }
}

// 7. Handle Range requests & Stream Headers
if (!empty($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes\W+(\d*)-(\d*)\W*$/', $_SERVER['HTTP_RANGE'], $matches);
    $start = isset($matches[1]) && $matches[1] !== '' ? (int)$matches[1] : 0;
    $end   = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : $size - 1;

    if ($end < 1 || $end >= $size) {
        $end = $size - 1;
    }

    $size = $end - $start + 1;
    if ($read_size > $size) {
        $read_size = $size;
    }

    header("HTTP/1.1 206 Partial Content");
    header("Content-Type: $type");
    header("Content-Length: $size");
    header("Accept-Ranges: bytes");
    header("Content-Range: bytes $start-$end/$total_size");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Content-Disposition: attachment; filename="' . $cover . '"');
} else {
    header("Content-Type: $type");
    header("Content-Length: $size");
    header("Accept-Ranges: bytes");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Content-Disposition: attachment; filename="' . $cover . '"');
}

// 8. Output content stream
fseek($fp, $start, SEEK_SET);

$bytes_left = $size;
while (!feof($fp) && $bytes_left > 0) {
    $chunk_size = min($read_size, $bytes_left);
    $buffer     = fread($fp, $chunk_size);
    echo $buffer;
    flush();
    $bytes_left -= strlen($buffer);
}

fclose($fp);

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