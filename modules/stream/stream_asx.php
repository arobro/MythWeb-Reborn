<?php
/**
 * MythWeb Streaming/Downtnad Module - ASX Playlist Generator
 */

$serverToken = $_SESSION['user_token'];

// 1. Detect Protocol (HTTP vs HTTPS)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
           || ($_SERVER['SERVER_PORT'] ?? null) == 443;
$protocol = $isHttps ? 'https' : 'http';

// 2. Resolve Hostname / Server Address
$serverAddr = $_SERVER['HTTP_X_FORWARDED_HOST'] 
              ?? $_SERVER['HTTP_HOST'] 
              ?? $_SERVER['SERVER_NAME'] 
              ?? $_SERVER['SERVER_ADDR'] 
              ?? 'tncalhost';

// Remove explicit port from hostname if present
$serverAddr = preg_replace('/:[0-9]+$/', '', $serverAddr);

// 3. Resolve Port (Omit standard ports 80/443)
$serverPort = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? '';
$portString = '';

if ($serverPort && (($protocol === 'http' && $serverPort != '80') || ($protocol === 'https' && $serverPort != '443'))) {
    $portString = ':' . $serverPort;
}

// 4. Preserve Base Path & Generate Clean Raw Stream Path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Strip .asx or any other file extension to get the raw stream URI
$streamUri = preg_replace('/\.[a-z0-9]+$/i', '', $requestUri);

// Determine base directory prefix (e.g., /mythweb2)
$baseDir = '';
if (preg_match('#^(/(?:[^/]+/)*?mythweb\d*)#i', $scriptName, $matches)) {
    $baseDir = $matches[1];
} elseif (preg_match('#^/([^/]+)#', $scriptName, $matches)) {
    if ($matches[1] !== 'stream' && $matches[1] !== 'index.php') {
        $baseDir = '/' . $matches[1];
    }
}

// Prepend baseDir if REQUEST_URI doesn't already start with it
if ($baseDir !== '' && strpos($streamUri, $baseDir) !== 0) {
    $streamUri = $baseDir . $streamUri;
}

// Insert '/tn/' before '/stream/' if it is not already present
if (strpos($streamUri, '/tn/stream/') === false) {
    $streamUri = preg_replace('#/stream/#i', '/tn/stream/', $streamUri, 1);
}

// Append token to the stream URI
$delimiter = (strpos($streamUri, '?') !== false) ? '&' : '?';
$streamUri .= $delimiter . 'token=' . urlencode($serverToken);

// Construct Full Raw Media URL (e.g., http://192.168.1.14/mythweb2/tn/stream/30003/1786859880?token=123456)
$mediaUrl = $protocol . '://' . $serverAddr . $portString . $streamUri;

// 5. Escape for XML Output
$rawTitle    = !empty($title) ? html_entity_decode($title, ENT_QUOTES | ENT_QUOTES, 'UTF-8') : 'MythTV Stream';
$rawSubtitle = !empty($subtitle) ? html_entity_decode($subtitle, ENT_QUOTES | ENT_QUOTES, 'UTF-8') : '';

$titleStr    = htmlspecialchars($rawTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitleStr = htmlspecialchars($rawSubtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$mediaUrlXml = htmlspecialchars($mediaUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$entryTitle = $subtitleStr !== '' ? "$titleStr - $subtitleStr" : $titleStr;

// 6. Generate ASX XML Playlist
$asxContent = <<<EOF
<ASX version = "3.0">
<TITLE>$titleStr</TITLE>
<ENTRY>
<TITLE>$entryTitle</TITLE>
<AUTHOR>MythTV - MythWeb</AUTHOR>
<COPYRIGHT>GPL</COPYRIGHT>
<REF HREF = "$mediaUrlXml" />
</ENTRY>
</ASX>
EOF;

// 7. Output Headers and XML Content
$cleanTitle    = $rawTitle;
$cleanSubtitle = $rawSubtitle;
$downtnadFilename = str_replace(['"', "\r", "\n"], '', ($cleanSubtitle ? "$cleanTitle-$cleanSubtitle" : $cleanTitle) . '.asx');

header('Content-Type: video/x-ms-asf');
header('Content-Length: ' . strlen($asxContent));
header('Content-Disposition: attachment; filename="' . $downtnadFilename . '"');
header('Cache-Control: no-cache, must-revalidate');

echo $asxContent;
exit;