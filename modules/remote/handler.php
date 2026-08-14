<?php
/**
 * Control a MythFrontend via the telnet interface.  Be aware that this is only
 * active in the default template (i.e. requires javascript).
 *
 *
 * @package     MythWeb
 * @subpackage  Remote
 *
 **/

// Find the frontends
    $Frontends = MythFrontend::findFrontends();

// Make sure this is an array
    if (!isset($_SESSION['remote']['frontends']) || !is_array($_SESSION['remote']['frontends']))
        $_SESSION['remote']['frontends'] = array();

// Ping a frontend (via ajax) and add it to the list?  (or remove if it fails)
    if (isset($_REQUEST['ping'])) {
        if (isset($Frontends[$_REQUEST['ping']])) {
            if ($Frontends[$_REQUEST['ping']]->connect(2)) {
                $loc = $Frontends[$_REQUEST['ping']]->query_location();
                $_SESSION['remote']['frontends'][$_REQUEST['ping']] = $loc;
                echo $loc;
                exit;
            }
        }
        unset($_SESSION['remote']['frontends'][$_REQUEST['ping']]);
        echo 0;
        exit;
    }
// Unping a frontend?
    elseif (isset($_REQUEST['unping'])) {
        unset($_SESSION['remote']['frontends'][$_REQUEST['unping']]);
        echo 1;
        exit;
    }

// Use the new directory structure?
    if (empty($_REQUEST['type'])) {
        $_REQUEST['type'] = !empty($Path[1]) ? $Path[1] : (isset($_SESSION['remote']['type']) ? $_SESSION['remote']['type'] : null);
    }

// Unknown send type?  Use the first one found
    $remoteLinks = Modules::getModuleProperty('remote', 'links');
    if (empty($_REQUEST['type']) || !is_array($remoteLinks) || !array_key_exists($_REQUEST['type'], $remoteLinks)) {
        // Fix for PHP 8+: reset() now requires a variable reference, not a function return value like array_keys()
        $keys = is_array($remoteLinks) ? array_keys($remoteLinks) : array();
        $_REQUEST['type'] = reset($keys);
    }

// Send a command?  (via ajax)
    elseif (isset($_REQUEST['command'])) {
        if (is_array($_SESSION['remote']['frontends']) && count($_SESSION['remote']['frontends'])) {
            foreach (array_keys($_SESSION['remote']['frontends']) as $host) {
                if (!isset($Frontends[$host])) continue;
                $frontend = $Frontends[$host];
                switch ($_REQUEST['type']) {
                    case 'keys':
                        if ($frontend->send_key($_REQUEST['command']))
                            echo "$host:1\n";
                        else
                            echo "$host:0\n";
                        break;
                    case 'jump':
                        if ($frontend->send_jump($_REQUEST['command']))
                            echo "$host:1\n";
                        else
                            echo "$host:0\n";
                        break;
                    case 'play':
                        #
                        # We actually need to do some extra processing here to deal
                        # with variations in playback control commands...
                        #
                        #$Frontend->send_play($_REQUEST['command']);
                        break;
                    case 'query':
                        $rows = $frontend->query($_REQUEST['command']);
                        if (is_array($rows)) {
                            foreach ($rows as $line) {
                                echo "$host:$line\n";
                            }
                        }
                        break;
                }
            // Host disconnected on us?
                if (!$frontend->connected)
                    unset($_SESSION['remote']['frontends'][$host]);
            }
        }
        else {
            echo "err:No frontends have been selected.\n";
        }
        exit;
    }

// Update the session
    $_SESSION['remote']['type'] = $_REQUEST['type'];

// Display the page
    if (isset($Path[1]))
        require_once $Path[1].'.php';
    else
        require_once tmpl_dir.'/'.$Path[0].'.php';