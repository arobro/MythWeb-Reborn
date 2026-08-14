<?php
/**
 * The display code for the Remote module.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Remote
 *
 **/

// Set the desired page title
    $page_title = 'MythFrontend Remote Control';

// Custom headers
    $headers[] = '<link rel="stylesheet" type="text/css" href="'.skin_url.'/remote.css">';

// Print the page header
    require 'modules/_shared/tmpl/'.tmpl.'/header.php';

// Safe check for active session frontends
    $active_frontends = isset($_SESSION['remote']['frontends']) && is_array($_SESSION['remote']['frontends']) 
        ? $_SESSION['remote']['frontends'] 
        : array();

// Safe check for Frontends variable
    $Frontends = isset($Frontends) && is_array($Frontends) ? $Frontends : array();

// Print the content itself
?>

<script type="text/javascript">
<!--

// List of enabled frontends
    var frontends = [<?php
        $hosts = array();
        foreach (array_keys($active_frontends) as $host) {
            $hosts[] = "'".addslashes($host)."'";
        }
        echo implode(',', $hosts);
        ?>];

// Process the currently-selected frontends to make sure they are still alive.
    Event.observe(window, 'load', verify_frontends);
    function verify_frontends() {
        for (var i=0;i<frontends.length;i++) {
            var host = frontends[i];
            var r = new Ajax.Request('remote/',
                                     {
                                        parameters: 'ping='+encodeURIComponent(host),
                                      asynchronous: false
                                     });
        // Handle the response
            if (r.transport.responseText == '0') {
                $('host_'+host).removeClassName('x-selected');
                frontends.splice(i, 1);
            }
        }
    }

// Handle a request to enable a specific frontend
    function handle_frontend(host) {
        var i = frontends.indexOf(host);
    // Turn a host on (assuming it is alive)
        if (i == -1) {
            var r = new Ajax.Request('remote/',
                                     {
                                        parameters: 'ping='+encodeURIComponent(host),
                                      asynchronous: false
                                     });
        // Handle the response
            if (r.transport.responseText == '0') {
                alert("<?php echo t('$1 is not responding.', '"+host+"') ?>");
                return;
            }
            $('host_'+host).addClassName('x-selected');
            frontends.push(host);
        }
    // Unset an active host?
        else {
            frontends.splice(i, 1);
            $('host_'+host).removeClassName('x-selected');
            new Ajax.Request('remote/',
                             {
                                parameters: 'unping='+encodeURIComponent(host),
                              asynchronous: true
                             });
        }
    }

// -->
</script>

<table id="remote" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td class="x-overview" rowspan="2" valign="top">
        <div class="x-title">
            <?php echo t('Frontends') ?>:
        </div>
<?php
        if (empty($Frontends)) {
            echo t('No Frontends allow remote control.');
        }
        else {
?>
        <div class="x-modules">
            <ul><?php
                foreach (array_keys($Frontends) as $host) {
                    echo '<li><a id="host_', html_entities($host), '"',
                         ' onclick="handle_frontend(\'', addslashes($host), '\')"';
                    if (!empty($active_frontends[$host]))
                        echo ' class="x-selected"';
                    echo '>', html_entities($host), '</a></li>';
                }
                ?></ul>
        </div>
<?php
        }
?>
        </td>
    <td class="x-sections">
        <ul><?php
            $selected_type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
            $links = Modules::getModuleProperty('remote', 'links');
            if (is_array($links)) {
                foreach ($links as $link => $name) {
                    echo '<li><a href="', Modules::getModuleProperty('remote', 'path'), '/', $link, '"';
                    if ($link == $selected_type)
                        echo ' class="x-selected"';
                    echo '>', html_entities($name), '</a></li>';
                }
            }
        ?></ul>
        </td>
</tr><tr>
<td class="x-content" colspan="2" valign="top">
<?php
    if (!empty($selected_type)) {
        require_once tmpl_dir.'/'.$selected_type.'.php';
    }
?>
</td>
</tr><tr>
    <td class="x-screenshots" colspan="2">
        <ul style="list-style-type: none"><?php
            foreach (array_keys($Frontends) as $host) {
                if (empty($active_frontends[$host]))
                    continue;
                echo '<li><img id="hostscreen_', html_entities($host), '" class="x-screenshot"',
                     ' src="', root, html_entities(Modules::getModuleProperty('remote', 'path').'/screenshot?format=jpg&width=960&host='.urlencode($host)),
                     '" /></li>';
            }
            ?></ul>
        </td>
</tr>
</table>
<?php

// Print the page footer
    require 'modules/_shared/tmpl/'.tmpl.'/footer.php';