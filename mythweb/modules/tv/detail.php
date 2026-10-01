<?php
/**
 * This displays details about a program, as well as provides recording
 * commands.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

// Just a quick ajax responder to track show/hide of the advanced options
    if (isset($_REQUEST['show_advanced_schedule'])) {
        $_SESSION['tv']['show_advanced_schedule'] = !empty($_REQUEST['show_advanced_schedule']);
        exit;
    }

// Use the new directory structure?
    if (empty($_REQUEST['chanid']) && empty($_REQUEST['starttime'])) {
        $_REQUEST['chanid']    = $Path[2] ?? null;
        $_REQUEST['starttime'] = $Path[3] ?? null;
        $_REQUEST['manualid']  = $Path[4] ?? null;
    }
// Just in case
    $_GET['chanid']    = intval($_REQUEST['chanid'] ?? 0);
    $_GET['starttime'] = intval($_REQUEST['starttime'] ?? 0);
    $_GET['manualid']  = intval($_REQUEST['manualid'] ?? 0);

// Set to defaults
    if ( !isset($_SESSION['recording_details'] )) {
        $_SESSION['recording_details']['show_Conflict'] = true;
        $_SESSION['recording_details']['show_PreviousRecording'] = true;
        $_SESSION['recording_details']['show_EarlierShowing'] = true;
        $_SESSION['recording_details']['show_CurrentRecording'] = true;
        $_SESSION['recording_details']['show_WillRecord'] = true;
    }

    if ( isset($_POST['change_display'])) {
        $_SESSION['recording_details']['show_Conflict']          = $_POST['Conflict'] ?? null;
        $_SESSION['recording_details']['show_PreviousRecording'] = $_POST['PreviousRecording'] ?? null;
        $_SESSION['recording_details']['show_EarlierShowing']    = $_POST['EarlierShowing'] ?? null;
        $_SESSION['recording_details']['show_CurrentRecording']  = $_POST['CurrentRecording'] ?? null;
        $_SESSION['recording_details']['show_WillRecord']        = $_POST['WillRecord'] ?? null;
    }

// Auto-expire -- only available for javascript templates
    if (isset($_REQUEST['toggle_autoexpire']) && $_GET['chanid'] && $_GET['starttime']) {
        $sh = $db->query('UPDATE recorded
                             SET autoexpire = ?
                           WHERE chanid = ? AND starttime = FROM_UNIXTIME(?)',
                         !empty($_REQUEST['toggle_autoexpire']) ? 1 : 0,
                         $_GET['chanid'],
                         $_GET['starttime']);
    // Report back, and then exit.
        if ($sh->affected_rows())
            echo 'success';
        else
            echo "Failed to update the database for chanid {$_GET['chanid']} at {$_GET['starttime']}!";
        exit;
    }

// Load the jobqueue info
    require_once 'includes/jobqueue.php';

// Load the sorting routines
    require_once 'includes/sorting.php';

// Load the program info, unless a schedule was requested
    $program = null;
    if (empty($_GET['recordid'])) {
    // Starttime in the past -- See if it's a recording
        if ($_GET['starttime'] < time()) {
            $record = MythBackend::find()->queryProgramRows('QUERY_RECORDING TIMESLOT '.$_GET['chanid'].' '.unix2mythtime($_GET['starttime']), 1);
            if (isset($record[0]) && is_array($record[0])) {
                $prog = new Program($record[0]);
                if ($_GET['chanid'] == $prog->chanid && $_GET['starttime'] == $prog->recstartts) {
                    $program = $prog;
                }
            }
        }
    // Load the program
        if (empty($program) || !$program->recstartts)
            $program = load_one_program($_GET['starttime'], $_GET['chanid'], $_GET['manualid']);
    }

// Get the schedule for this recording, if one exists
    if ($program && $program->recordid)
        $schedule = Schedule::find($program->recordid);
    elseif (!empty($_GET['recordid']))
        $schedule = Schedule::find($_GET['recordid']);
    else
        $schedule = new Schedule(NULL);

// Handle custom search schedules.  This will cause the "cancel" or "don't"
// option to be selected as "schedule via custom search"
    if ($schedule->search && $schedule->search != searchtype_manual) {
        $schedule->type = null;
    }

// Make sure this is a valid program.  If not, forward the user back to the listings page
    if ((!$program || !strlen((string)$program->starttime)) && !$schedule->recordid) {
        if (!empty($_GET['recordid'])) {
            add_warning(t('Unknown Recording Schedule.'));
            header('Location: '.root_url.'tv/schedules');
        }
        else {
            add_warning(t('Unknown Program.'));
            $list_time = $_SESSION['list_time'] ?? time();
            header('Location: '.root_url.'tv/list?time='.$list_time);
        }
        save_session_errors();
        exit;
    }

// If there is a program for this, import its values into the schedule
    if ($program) {
    // Back up the search title
        if ($schedule->search) {
            $schedule->search_title = $schedule->title;
        }
        $schedule->chanid            = $program->chanid;
        $schedule->starttime         = $program->starttime;
        $schedule->endtime           = $program->endtime;
        $schedule->title             = $program->title;
        $schedule->subtitle          = $program->subtitle;
        $schedule->description       = $program->description;
        $schedule->fancy_description = $program->fancy_description;
        $schedule->category          = $program->category;
        $schedule->station           = $program->channel->callsign;       // Note that "callsign" becomes "station"
        $schedule->seriesid          = $program->seriesid;
        $schedule->programid         = $program->programid;
    }

// Queue a job?
    if ($program && $program->filename && !empty($_REQUEST['job'])) {
        $host = "";
        if (setting("JobsRunOnRecordHost")){
            $host = $program->hostname;
        }
        $db->query('INSERT INTO jobqueue
                       SET chanid       = ?,
                           starttime    = FROM_UNIXTIME(?),
                           inserttime   = NOW(),
                           type         = ?,
                           hostname     = ?,
                           args         = "",
                           status       = ?,
                           statustime   = NOW(),
                           schedruntime = NOW(),
                           comment      = "Queued via MythWeb",
                           flags        = ?',
                   $program->chanid,
                   $program->recstartts,
                   $_REQUEST['job'],
                   $host,
                   JOB_QUEUED,
                   JOB_USE_CUTLIST
                  );
        MythBackend::find()->rescheduleRecording();
    // Redirect back to the page again, but without the query string, so the
    // user doesn't accidentally repost this request on a page reload.
        redirect_browser(root_url.'tv/detail/'.$program->chanid.'/'.$program->recstartts);
    }

// Load the utility/display functions for scheduling
    require_once 'includes/schedule_utils.php';

// The user tried to update the recording settings - update the database and the variable in memory
    if (isset($_POST['save'])) {
        if ($schedule) {
        // Which type of recording is this?  Make sure an illegal one isn't specified
            switch ($_POST['record'] ?? null) {
                case rectype_once:        $type = rectype_once;        break;
                case rectype_daily:       $type = rectype_daily;       break;
                case rectype_always:      $type = rectype_always;      break;
                case rectype_weekly:      $type = rectype_weekly;      break;
                case rectype_findone:     $type = rectype_findone;     break;
                case rectype_override:    $type = rectype_override;    break;
                case rectype_dontrec:     $type = rectype_dontrec;     break;
                default:                  $type = 0;
            }
        // Probably cancelling a schedule?
            if ($type == 0) {
            // Changing something about the currently selected search schedule?
            /** @todo Search schedules go a little funny here.  If we try to save them,
             *  the search settings will be overwritten by the program's info
             *  subtitle, etc). */
                if ($schedule->search) {
                    add_warning('Modifications to search schedules cannot yet be made from this page.');
                }
            // Cancel this schedule
                elseif ($schedule && $schedule->recordid) {
                // Delete the schedule
                    $schedule->delete();
                // Try to cancel the currently recording program if we can
                    if ($program)
                        $program->stopRecording();
                // Deleted a schedule but not editing a specific program?  Redirect back to the schedule list
                    if (!$program) {
                        add_warning(t('The requested recording schedule has been deleted.'));
                        save_session_errors();
                        header('Location: '.root_url.'tv/schedules');
                        exit;
                    }
                // Relocate back to the program details page
                    redirect_browser(root_url.'tv/detail/'.$schedule->chanid.'/'.$schedule->starttime);
                }
            }
        // Modifying an existing schedule, or adding a new one
            else {
            // Set things as the user requested
                $schedule->profile       = $_POST['profile'] ?? null;
                $schedule->recgroup      = $_POST['recgroup'] ?? null;
                $schedule->storagegroup  = $_POST['storagegroup'] ?? null;
                $schedule->playgroup     = $_POST['playgroup'] ?? null;
                $schedule->autoexpire    = !empty($_POST['autoexpire'])   ? 1 : 0;
                $schedule->autocommflag  = !empty($_POST['autocommflag']) ? 1 : 0;
                $schedule->autouserjob1  = !empty($_POST['autouserjob1']) ? 1 : 0;
                $schedule->autouserjob2  = !empty($_POST['autouserjob2']) ? 1 : 0;
                $schedule->autouserjob3  = !empty($_POST['autouserjob3']) ? 1 : 0;
                $schedule->autouserjob4  = !empty($_POST['autouserjob4']) ? 1 : 0;
                $schedule->autometadata  = !empty($_POST['autometadata']) ? 1 : 0;
                $schedule->maxnewest     = !empty($_POST['maxnewest'])    ? 1 : 0;
                $schedule->inactive      = !empty($_POST['inactive'])     ? 1 : 0;
                $schedule->dupin         = _or(intval($_POST['dupin'] ?? 0) + intval($_POST['dupin2'] ?? 0), dupsin_all);
                $schedule->dupmethod     = _or($_POST['dupmethod'] ?? null, 6);
                $schedule->recpriority   = intval($_POST['recpriority'] ?? 0);
                $schedule->maxepisodes   = intval($_POST['maxepisodes'] ?? 0);
                $schedule->startoffset   = intval($_POST['startoffset'] ?? 0);
                $schedule->endoffset     = intval($_POST['endoffset'] ?? 0);
                $schedule->autotranscode = !empty($_POST['autotranscode']) ? 1 : 0;
                $schedule->transcoder    = $_POST['transcoder'] ?? null;
                $schedule->prefinput     = $_POST['prefinput'] ?? null;
                $schedule->inetref       = $_POST['inetref'] ?? null;
                $schedule->season        = intval($_POST['season'] ?? 0);
                $schedule->episode       = intval($_POST['episode'] ?? 0);
                $schedule->filter        = generateFilter();

            // Keep track of the parent recording for overrides
                if (($_POST['record'] ?? null) == rectype_override) {
                    $schedule->parentid = $schedule->recordid;
                }
            // Search schedules saved here will create a new standard schedule,
            // so we should wipe out the old search-type data.
                if ($schedule->search) {
                    $schedule->recordid = null;
                }
                $schedule->search = 0;
            // Back up the program type, and save the schedule
                $schedule->save($type);
            }
        }
    // Redirect back to the page again, but without the query string, so reloads are cleaner
        if ($db->query_col('SELECT COUNT(*) FROM program WHERE chanid = ? and starttime = FROM_UNIXTIME(?) LIMIT 1', $program->chanid, $program->starttime) == 0)
            redirect_browser(root.'tv/detail?recordid='.$schedule->recordid);
        redirect_browser(root_url.'tv/detail/'.$program->chanid.'/'.$program->starttime);
    }
    elseif (!empty($_REQUEST['forget_old'])) {
        $program->rec_forget_old();
    // Redirect back to the page again, but without the query string, so reloads are cleaner
        redirect_browser(root_url.'tv/detail/'.$program->chanid.'/'.$program->starttime);
    }
    elseif (isset($_GET['never_record']) || isset($_POST['never_record'])) {
        $program->rec_never_record();

    // Redirect back to the page again, but without the query string, so reloads are cleaner
        redirect_browser(root_url.'tv/detail/'.$program->chanid.'/'.$program->starttime);
    }
    else {
    // Load default values from Default recording rule template
        $schedule->merge(Schedule::recording_template('Default'));
    }

// Load the channel
    if ($program)
        $channel = $program->channel;
    else
        $channel = Channel::find($schedule->chanid);

// Parse the list of scheduled recordings for possible conflicts
    $conflicting_shows = array();
    foreach (Schedule::findScheduled() as $callsign => $shows) {
    // Now the shows in this channel
        foreach ($shows as $starttime => &$show_group) {
        // Clearly not a match
            if ($program && $starttime > $program->endtime)
                continue;
        // Parse each show group
            foreach ($show_group as $key => &$show) {
            // Ignore this show
                if ($program && $show->chanid == $program->chanid && $show->starttime == $program->starttime)
                    continue;
            // Make sure this is a valid show (ie. skip in-progress recordings and other junk)
                if (!$callsign || $show->length < 1)
                    continue;
            // Not a conflict
                if ($program && $show->endtime < $program->starttime)
                    continue;
            // Assign a reference to this show to the various arrays
                $conflicting_shows[] = $show;
            }
        }
    }

// Sort the programs -- but don't use a session variable
    if (count($conflicting_shows)) {
        $GLOBALS['user_sort_choice'] = array(array('field' => 'title'),
                                             array('field' => 'subtitle'),
                                             array('field' => 'airdate')
                                            );
        usort($conflicting_shows, 'by_user_choice');
    }

// Load the jobqueue info before displaying
    if ($program && $program->filename) {
        $program->load_jobs();
    }

// Setup some vars for the ipod template
    $Page_Previous_Location = root_url.'tv/list_shows_in_title_and_group?group='.urlencode($program->recgroup ?? '').'&title='.urlencode($program->title ?? '');
    $Page_Previous_Location_Name = $program->title ?? '';
    $Page_Title_Short = 'Details';

// Fetch alternative screenings based on Title match
	if ($program->recstatus != 'Recorded' and $program->recstatus != 'Recording'){
		$alt_screenings = array();
		if ($program && !empty($program->title)) {
			$sql = "
		SELECT
			MIN(p.chanid) AS chanid,
			p.starttime,
			p.endtime,
			p.title,
			p.subtitle,
			c.name AS channel_name
		FROM
			program p
		INNER JOIN
			channel c ON p.chanid = c.chanid
		WHERE
			p.title = ?
			AND p.endtime >= NOW()
			AND c.visible = 1
			AND p.description = ?
			AND p.subtitle = ?
			AND NOT (p.starttime = ?)
		GROUP BY
			p.starttime,
			p.endtime,
			p.title,
			p.subtitle
		ORDER BY
			p.starttime ASC
		LIMIT 5;
			";

			$starttime_datetime = unix2mythtime($program->starttime);

			$sh = $db->query($sql,
							$program->title,
							$program->description,
							$program->subtitle,
							$starttime_datetime);

			while ($row = $sh->fetch_assoc()) {
				$alt_screenings[] = $row;
			}
		}
	}

// Display the page
    require_once tmpl_dir.'detail.php';

// Exit
    exit;