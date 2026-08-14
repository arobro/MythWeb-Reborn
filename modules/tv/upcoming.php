<?php
/**
 * View and fix scheduling conflicts.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

// Load the sorting routines
    require_once 'includes/sorting.php';

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

// Doing something to a program? Load its detailed info
    if ($_GET['chanid'] && $_GET['starttime']) {
        $program = load_one_program($_GET['starttime'], $_GET['chanid'], $_GET['manualid']);

        if (is_object($program)) {
        // Forget all knowledge of old recordings
            if (!empty($_REQUEST['forget_old']))
                $program->rec_forget_old();
        // Fake an old recording so that this show won't record again
            elseif (!empty($_REQUEST['never_record']))
                $program->rec_never_record();
        // Revert to default recording rules
            elseif (!empty($_REQUEST['default']))
                $program->rec_default();
        // Suppress something that shouldn't be recorded
            elseif (!empty($_REQUEST['dontrec']))
                $program->rec_override(rectype_dontrec);
        // Record a show that wouldn't otherwise record (various reasons, read below)
            elseif (!empty($_REQUEST['record']))
                $program->rec_override(rectype_override);
            elseif (!empty($_REQUEST['activate']))
                $program->activate();
        }
        else {
            if (!empty($_REQUEST['dontrec']))
                $schedule = Schedule::find($_GET['chanid'], $_GET['starttime'])->save(rectype_dontrec);
            else
                add_warning('Unknown program.');
        }

    // Redirect back to the page again, but without the query string, so reloads are cleaner
        redirect_browser(root_url.'tv/upcoming');
    }

// Ignore certain shows?
    if (!empty($_POST['change_display'])) {
        $_SESSION['scheduled_recordings']['disp_scheduled']   = !empty($_POST['disp_scheduled']);
        $_SESSION['scheduled_recordings']['disp_duplicates']  = !empty($_POST['disp_duplicates']);
        $_SESSION['scheduled_recordings']['disp_deactivated'] = !empty($_POST['disp_deactivated']);
        $_SESSION['scheduled_recordings']['disp_conflicts']   = !empty($_POST['disp_conflicts']);
        $_SESSION['scheduled_recordings']['disp_recgroup']    = $_POST['disp_recgroup'] ?? null;
        $_SESSION['scheduled_recordings']['disp_title']       = $_POST['disp_title'] ?? null;
    }

// Defaults
    if (!isset($_SESSION['scheduled_recordings'])
        || !is_array($_SESSION['scheduled_recordings'])
        || !(!empty($_SESSION['scheduled_recordings']['disp_scheduled'])
             || !empty($_SESSION['scheduled_recordings']['disp_duplicates'])
             || !empty($_SESSION['scheduled_recordings']['disp_deactivated'])
             || !empty($_SESSION['scheduled_recordings']['disp_conflicts']))) {
        $_SESSION['scheduled_recordings'] = array('disp_scheduled'   => TRUE,
                                                  'disp_duplicates'  => FALSE,
                                                  'disp_deactivated' => FALSE,
                                                  'disp_conflicts'   => TRUE
                                                 );
    }

// Parse the list of scheduled recordings
    $all_shows = array();
    $Groups = array();
    $Program_Titles = array();
    if (empty($scheduled)) {
        $scheduled = array();
    }

    foreach (Schedule::findScheduled() as $callsign => $shows) {
    // Now the shows in this channel
        foreach ($shows as $starttime => $show_group) {
        // Parse each show group
            foreach ($show_group as $key => $show) {
                if (!isset($Groups[$show->recgroup])) {
                    $Groups[$show->recgroup] = 0;
                }
                $Groups[$show->recgroup]++;
            // Skip things we've already recorded (or missed)
                if ($starttime <= time() && $show->recstatus != 'Recording')
                    continue;
            // Make sure this is a valid show (ie. skip in-progress recordings and other junk)
                if (!$callsign || $show->length < 1)
                    continue;
                if (!isset($Program_Titles[$show->title])) {
                    $Program_Titles[$show->title] = 0;
                }
                $Program_Titles[$show->title]++;
            // Skip scheduled shows?
                if (in_array($show->recstatus, array('WillRecord', 'ForceRecord'))) {
                    if (empty($_SESSION['scheduled_recordings']['disp_scheduled']) || !empty($_GET['skip_scheduled']))
                        continue;
                }
            // Skip conflicting shows?
                elseif (in_array($show->recstatus, array('Conflict', 'Overlap'))) {
                    if (empty($_SESSION['scheduled_recordings']['disp_conflicts']) || !empty($_GET['skip_conflicts']))
                        continue;
                }
            // Skip duplicate or ignored shows?
                elseif (in_array($show->recstatus, array('NeverRecord', 'PreviousRecording', 'CurrentRecording'))) {
                    if (empty($_SESSION['scheduled_recordings']['disp_duplicates']) || !empty($_GET['skip_duplicates']))
                        continue;
                }
            // Skip deactivated shows?
                elseif ($show->recstatus != 'Recording') {
                    if (empty($_SESSION['scheduled_recordings']['disp_deactivated']) || !empty($_GET['skip_deactivated']))
                        continue;
                }
            // Show specific recgroup only
                if ((!empty($_SESSION['scheduled_recordings']['disp_recgroup']) && $show->recgroup != $_SESSION['scheduled_recordings']['disp_recgroup'])
                    || (!empty($_GET['recgroup']) && $show->recgroup != $_GET['recgroup']))
                    continue;
            // Show specific title only
                if ((!empty($_SESSION['scheduled_recordings']['disp_title']) && $show->title != $_SESSION['scheduled_recordings']['disp_title'])
                    || (!empty($_GET['title']) && $show->title != $_GET['title']))
                    continue;
            // Assign this show to the array
                $all_shows[] = $show;
            }
        }
    }

// Sort the programs
    if (count($all_shows))
    {
        sort_programs($all_shows, 'scheduled_sortby');

        uksort($Groups, 'by_no_articles');
        uksort($Program_Titles, 'by_no_articles');
    }

// Load the class for this page
    require tmpl_dir.'upcoming.php';

// Exit
    exit;