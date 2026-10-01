<?php
/**
 * Show all upcoming recordings.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

// Set the desired page title
    $page_title = 'MythWeb - '.t('Upcoming Recordings');

// Custom headers
    $headers[] = '<link rel="stylesheet" type="text/css"      href="'.(defined('skin_url') ? skin_url : '').'/tv_upcoming.css">';

// Print the page header
    require 'modules/_shared/tmpl/'.(defined('tmpl') ? tmpl : '').'/header.php';

/** @todo FIXME:  pull this out of the theme page! */
// Which field are we grouping by?
    $group_field = $_SESSION['scheduled_sortby'][0]['field'] ?? '';
    if (empty($group_field))
        $group_field = 'recdate';
    elseif (!in_array($group_field, array('title', 'channum', 'airdate', 'recdate')))
        $group_field = '';

?>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const rootUrl = '<?php echo defined('root_url') ? root_url : ''; ?>';

        // Override the auto-initialized mythTooltip's fetchData callback
        if (window.mythTooltip) {
            window.mythTooltip.options.fetchData = function(element) {
                const chanId = element.getAttribute('data-chanid');
                const startTime = element.getAttribute('data-starttime');

                if (!chanId || !startTime) return null;

                const url = `${rootUrl}tv/get_show_details?chanid=${encodeURIComponent(chanId)}&starttime=${encodeURIComponent(startTime)}&ajax=true`;

                return fetch(url)
                    .then(response => response.json())
                    .then(data => data.info || '')
                    .catch(err => {
                        console.error('Error fetching show tooltip:', err);
                        return '';
                    });
            };
        }
    });
</script>

<form id="change_display" action="<?php echo defined('root_url') ? root_url : '' ?>tv/upcoming" method="post">
<div><input type="hidden" name="change_display" value="1"></div>

<table id="display_options" class="commandbox commands" border="0" cellspacing="0" cellpadding="0">
<tr>
    <td class="x-title"><?php echo t('Display') ?>:</td>
    <td class="x-check">
        <label for="disp_scheduled">
        <input type="checkbox" id="disp_scheduled" name="disp_scheduled" class="radio" onclick="$('change_display').submit()"<?php
            if (!empty($_SESSION['scheduled_recordings']['disp_scheduled'])) echo ' CHECKED' ?>>
        <?php echo t('Scheduled') ?></label>
        </td>
    <td class="x-check">
        <label for="disp_duplicates">
        <input type="checkbox" id="disp_duplicates" name="disp_duplicates" class="radio" onclick="$('change_display').submit()" <?php
            if (!empty($_SESSION['scheduled_recordings']['disp_duplicates'])) echo ' CHECKED' ?>>
        <?php echo t('Duplicates/Ignored') ?></label>
        </td>
    <td class="x-check">
        <label for="disp_deactivated">
        <input type="checkbox" id="disp_deactivated" name="disp_deactivated" class="radio" onclick="$('change_display').submit()" <?php
            if (!empty($_SESSION['scheduled_recordings']['disp_deactivated'])) echo ' CHECKED' ?>>
        <?php echo t('Deactivated') ?></label>
        </td>
    <td class="x-check">
        <label for="disp_conflicts">
        <input type="checkbox" id="disp_conflicts" name="disp_conflicts" class="radio" onclick="$('change_display').submit()" <?php
            if (!empty($_SESSION['scheduled_recordings']['disp_conflicts'])) echo ' CHECKED' ?>>
        <?php echo t('Conflicts') ?></label>
        </td>
</tr>
<tr>
    <td colspan="5">
<?php if (isset($Groups) && is_countable($Groups) && count($Groups) > 1) { ?>
    <select name="disp_recgroup" onchange="$('change_display').submit()">
        <option id="All groups" value=""><?php echo t('All groups') ?></option><?php
        foreach($Groups as $recgroup => $count) {
            echo '<option id="Group '.htmlspecialchars($recgroup).'" value="'.htmlspecialchars($recgroup).'"';
            if (isset($_SESSION['scheduled_recordings']['disp_recgroup']) && $_SESSION['scheduled_recordings']['disp_recgroup'] == $recgroup)
                echo ' SELECTED';
            echo '>'.html_entities($recgroup)
                .' ('.tn('$1 episode', '$1 episodes', $count)
                .')</option>';
        }
        ?>
    </select>
<?php
    }
?>
    <select name="disp_title" onchange="$('change_display').submit()">
        <option id="All titles" value="">All titles</option>
<?php
    if (isset($Program_Titles) && is_array($Program_Titles)) {
        foreach($Program_Titles as $title => $count) {
            echo '<option id="Title '.htmlspecialchars($title).'" value="'.htmlspecialchars($title).'"';
            if (isset($_SESSION['scheduled_recordings']['disp_title']) && $_SESSION['scheduled_recordings']['disp_title'] == $title)
                echo ' SELECTED';
            echo '>'.html_entities($title)
                .($count > 1 ? ' ('.tn('$1 episode', '$1 episodes', $count).')' : "")
                ."</option>\n";
        }
    }
?>
    </select>
    </td>
</tr>

</table>

</form>

<table id="listings" border="0" cellpadding="4" cellspacing="2" class="list small">
<tr class="menu">
    <?php if ($group_field != '') echo "<td class=\"list\">&nbsp;</td>\n" ?>
        <th class="x-status"><?php  echo t('Status') ?></th>
    <?php if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['title']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['title'] == 'on') { ?>
        <th class="x-title"><?php   echo get_sort_link('title',   t('Title'))   ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['original airdate']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['original airdate'] == 'on') { ?>
        <th class="x-originalairdate"><?php echo get_sort_link('originalairdate', t('Original Airdate')) ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['episode number']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['episode number'] == 'on') { ?>
        <th class="x-episodenumber"><?php echo get_sort_link('episodenumber', t('Episode Number')) ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['channel']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['channel'] == 'on') { ?>
        <th class="x-channum"><?php echo get_sort_link('channum', t('Channel')) ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['recording group']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['recording group'] == 'on') { ?>
        <th class="x-recgroup"><?php echo get_sort_link('recgroup', t('Recording Group')) ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['airdate']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['airdate'] == 'on') { ?>
        <th class="x-airdate"><?php echo get_sort_link('airdate', t('Airdate')) ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['record date']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['record date'] == 'on') { ?>
        <th class="x-recdate"><?php  echo get_sort_link('recdate',  t('Record Date'))  ?></th>
    <?php } if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['length']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['length'] == 'on') { ?>
        <th class="x-length"><?php  echo get_sort_link('length',  t('Record Length'))  ?></th>
    <?php } ?>
</tr><?php
    $row = 0;

    $prev_group = '';
    $cur_group  = '';
    if (isset($all_shows) && (is_array($all_shows) || is_object($all_shows))) {
    foreach ($all_shows as $show) {
        flush();

    // Set the class to be used to display the recording status character
        $rec_class = implode(' ', array(recstatus_class($show), $show->recstatus));
    // Reset the command variable to a default URL
        $commands = array();
        $urlstr = $show->chanid.'/'.$show->starttime;
        if (Schedule::find($show->recordid)->search == searchtype_manual)
            $urlstr .= '/'.$show->recordid;
    // Set the recording status character, class and any applicable commands for each show
        switch ($show->recstatus) {
            case 'Recording':
                $rec_char   = $show->inputname;
                $css_class  = 'scheduled';
                break;
            case 'WillRecord':
                $rec_char   = $show->inputname;
                $css_class  = 'scheduled';
                $commands[] = 'dontrec';
            // Offer to suppress any recordings that have enough info to do so.
                if (preg_match('/\\S/', $show->title)
                        && (preg_match('/\\S/', $show->programid.$show->subtitle.$show->description))) {
                    $commands[] = 'never_record';
                }
                break;
            case 'PreviousRecording':
                $rec_char   = t('Duplicate');
                $css_class  = 'duplicate';
                $commands[] = 'record';
                $commands[] = 'forget_old';
                break;
            case 'CurrentRecording':
                $rec_char   = t('Recorded');
                $css_class  = 'duplicate';
                $commands[] = 'record';
                $commands[] = 'forget_old';
                break;
            case 'Repeat':
                $rec_char   = 'Rerun';
                $css_class  = 'duplicate';
                $commands[] = 'record';
                break;
            case 'EarlierShowing':
                $rec_char   = t('Earlier');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'default';
                break;
            case 'TooManyRecordings':
                $rec_char   = t('Too Many');
                $css_class  = 'deactivated';
                break;
            case 'NeverRecord':
                $rec_char   = t('Never Record');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'forget_old';
                break;
            case 'Cancelled':
                $rec_char   = t('Cancelled');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'default';
                break;
            case 'DontRecord':
                $rec_char   = t('Don\'t Record');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'default';
                break;
            case 'Conflict':
                $rec_char = t('Conflict');
            // We normally use the recstatus value as the name of the class
            //  used when displaying the recording status character.
            // However, there is already a class named 'conflict' so we
            //  need to modify this specific recstatus to avoid a conflict.
                $rec_class = implode(' ', array(recstatus_class($show),
                                     'conflicting'));
                $css_class  = 'conflict';
                $commands[] = 'record';
                $commands[] = 'never_record';
                break;
            case 'LaterShowing':
                $rec_char   = t('Later');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'default';
                break;
            case 'LowDiskSpace':
                $rec_char   = t('Low Space');
                $css_class  = 'deactivated';
                $commands[] = 'Not Enough Disk Space';
                break;
            case 'TunerBusy':
                $rec_char   = t('Tuner Busy');
                $css_class  = 'deactivated';
                $commands[] = 'Tuner is busy';
                break;
            case 'Overlap':
                $rec_char   = t('Override');
                $css_class  = 'conflict';
                $commands[] = 'record';
                $commands[] = 'dontrec';
                break;
            case 'ManualOverride':
                $rec_char   = t('Override');
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'default';
                break;
            case 'ForceRecord':
                $rec_char   = $show->inputname ? $show->inputname : t('Forced');
                $css_class  = 'scheduled';
                $commands[] = 'dontrec';
                $commands[] = 'default';
                break;
            case 'NotListed':
                $rec_char   = t('Not Listed');
                $css_class  = 'deactivated';
                $commands[] = 'Not airing in timeslot this day';
                break;
            default:
                $rec_char   = '&nbsp;';
                #$rec_char   = $show->recstatus;
                $rec_class  = '';
                $css_class  = 'deactivated';
                $commands[] = 'activate';
                $commands[] = 'dontrec';
                break;
        }
    // Now do the necessary replacements for each command
        $rootUrlStr = defined('root_url') ? root_url : '';
        foreach ($commands as $key => $val) {
            switch ($val) {
                case 'dontrec':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?dontrec=yes"'
                                     .' title="'.html_entities(t('info: dont record')).'">'
                                     .t('Don\'t Record').'</a>';
                    break;
                case 'never_record':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?never_record=yes"'
                                     .' title="'.html_entities(t('info:never record')).'">'
                                     .t('Never Record').'</a>';
                    break;
                case 'record':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?record=yes"'
                                     .' title="'.html_entities(t('info: record this')).'">'
                                     .t('Record This').'</a>';
                    break;
                case 'forget_old':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?forget_old=yes"'
                                     .' title="'.html_entities(t('info:forget old')).'">'
                                     .t('Forget Old').'</a>';
                    break;
                case 'activate':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?activate=yes"'
                                     .' title="'.html_entities(t('info: activate recording')).'">'
                                     .t('Activate').'</a>';
                    break;
                case 'default':
                    $commands[$key] = '<a href="'.$rootUrlStr.'tv/upcoming/'.$urlstr.'?default=yes"'
                             .' title="'.html_entities(t('info: default recording')).'">'
                             .t('Default').'</a>';
                    break;
            }
        }

    // Print a dividing row if grouping changes
		$dateFormat = $_SESSION['date_listing_jump2'] ?? 'Y-m-d';
        if ($group_field == "airdate")
            $cur_group = date($dateFormat, (int)$show->starttime);
        elseif ($group_field == "recdate")
            $cur_group = date($dateFormat, (int)$show->recstartts);
        elseif ($group_field == "channum")
            $cur_group = $show->channel->channum.' - '.$show->channel->name;
        elseif ($group_field == "title")
            $cur_group = $show->title;

        if ( $cur_group != $prev_group && $group_field != '' ) {
?><tr class="list_separator">
    <td colspan="<?php echo 4 + (isset($_SESSION['settings']['screens']['tv']['upcoming recordings']) && is_countable($_SESSION['settings']['screens']['tv']['upcoming recordings']) ? count($_SESSION['settings']['screens']['tv']['upcoming recordings']) : 0) ?>" class="list_separator"><?php echo $cur_group ?></td>
</tr><?php
        }

    // Print the content
?><tr class="<?php echo $css_class ?>">
<?php if (!empty($group_field)) echo "    <td class=\"list\">&nbsp;</td>\n" ?>
    <td class="x-status rec_class <?php echo $rec_class ?>"><?php echo $rec_char ?></td>
    <?php
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['title']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['title'] == 'on') {
            ?>
                <td class="x-title <?php echo $show->css_class ?>"><?php
                    if ($show->hdtv)
                        echo '<span class="hdtv_icon">HD</span>';
                    if ($show->starstring)
                        echo '<span class="starstring" style="float: right">'.$show->starstring.'</span>';
                // Print the link to edit this scheduled recording
                        echo '<a id="program-'.$show->chanid.'-'.$show->starttime.'"';
                    if (!empty($_SESSION["show_popup_info"])) {
                        echo ' data-tooltip="true"';
                        echo ' data-chanid="', html_entities($show->chanid), '"';
                        echo ' data-starttime="', html_entities($show->starttime), '"';
                    }
                    else {
                        $timeFmt = $_SESSION['time_format2'] ?? 'g:i A';
                        $formattedStart = date($timeFmt, (int)$show->starttime);
                        $formattedEnd = date($timeFmt, (int)$show->endtime);
                        echo ' title="',html_entities($formattedStart
                                     .' - '.$formattedEnd
                                     .' -- '
                                     .$show->title
                                     .($show->subtitle
                                         ? ':  '.$show->subtitle
                                         : '')), '"';
                    }
                    if ($show->recstatus == 'NotListed')
                        echo ' href="', $rootUrlStr, 'tv/schedules#schedule-',
                             $show->recordid;
                    else
                        echo ' href="', $rootUrlStr, 'tv/detail/', $urlstr;
                    echo '">', $show->title,
                         ($show->subtitle
                            ? ':  '.$show->subtitle
                            : ''),
                         '</a>';
                    ?>
                </td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['original airdate']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['original airdate'] == 'on') {
            ?>
                <td class="x-originalairdate"><?php echo $show->airdate ?></td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['episode number']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['episode number'] == 'on') {
            ?>
                <td class="x-episodenumber"><?php echo $show->syndicatedepisodenumber ?></td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['channel']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['channel'] == 'on') {
            ?>
                <td class="x-channum"><?php echo $show->channum, ' - ', $show->channame ?></td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['recording group']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['recording group'] == 'on') {
            ?>
                <td class="x-recgroup"><?php echo $show->recgroup ?></td>
            <?php
        }
		if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['airdate']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['airdate'] == 'on') {
            $dateSchedFmt = $_SESSION['date_scheduled2'] ?? 'Y-m-d';
            $airdateStr = date($dateSchedFmt, (int)$show->starttime);
            ?>
                <td class="x-airdate" nowrap><?php echo $airdateStr ?></td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['record date']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['record date'] == 'on') {
            $dateSchedFmt = $_SESSION['date_scheduled2'] ?? 'Y-m-d';
            $recstartStr = date($dateSchedFmt, (int)$show->recstartts);
            ?>
                <td class="x-recdate" nowrap><?php echo $recstartStr ?></td>
            <?php
        }
        if (!empty($_SESSION['settings']['screens']['tv']['upcoming recordings']['length']) && $_SESSION['settings']['screens']['tv']['upcoming recordings']['length'] == 'on') {
            ?>
                <td class="x-length"><?php  echo nice_length($show->length) ?></td>
            <?php
        }
        if ($show->recstatus == 'Recording') {
            echo '    <td class="x-commands commands x-recording" colspan="2">',
                 '<a href="', $rootUrlStr, 'tv/detail/', $show->chanid, '/', $show->starttime, '">',
                 t('Currently Recording:  Edit'),"</a></td>\n";
        }
        else {
            foreach ($commands as $command) {
                echo '    <td class="x-commands commands">',$command,"</td>\n";
            }
        }
?>
</tr><?php
        $prev_group = $cur_group;
        $row++;
    }
    }
?>

</table>
<?php
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $ical_href = "ical{$requestUri}?";
    if (empty($_SESSION['scheduled_recordings']['disp_scheduled']))
        $ical_href .='skip_scheduled&';
    if (empty($_SESSION['scheduled_recordings']['disp_conflicts']))
        $ical_href .='skip_conflicts&';
    if (empty($_SESSION['scheduled_recordings']['disp_duplicates']))
        $ical_href .='skip_duplicates&';
    if (empty($_SESSION['scheduled_recordings']['disp_deactivated']))
        $ical_href .='skip_deactivated&';
    if (!empty($_SESSION['scheduled_recordings']['disp_recgroup']))
        $ical_href .= 'recgroup='.urlencode($_SESSION['scheduled_recordings']['disp_recgroup']).'&';
    if (!empty($_SESSION['scheduled_recordings']['disp_title']))
        $ical_href .= 'title='.urlencode($_SESSION['scheduled_recordings']['disp_title']).'&';
    $skinUrlStr = defined('skin_url') ? skin_url : '';
    echo '<div id="feed_buttons"><a href="'.$ical_href.'"><img src="'.$skinUrlStr.'/img/iCal2.0.png"></a></div>';

// Print the page footer
    require 'modules/_shared/tmpl/'.(defined('tmpl') ? tmpl : '').'/footer.php';