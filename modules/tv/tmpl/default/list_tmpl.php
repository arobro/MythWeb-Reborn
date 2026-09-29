<?php
/**
 * Program Listing page description of the TV module.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

$page_title = 'MythWeb - ' . t('Program Listing') . ': ' . date($_SESSION['date_statusbar2'] ?? 'Y-m-d H:i', $list_starttime);
$headers[] = '<link rel="stylesheet" type="text/css" href="' . root_url . '/skins/epg_common.css">' .
             '<script src="' . root_url . 'js/epg_debug.js"></script>';

if (!isset($initial_epg_data)) {
    $initial_epg_data = array();
}

require 'modules/_shared/tmpl/'.tmpl.'/header.php';
?>

<div id="modern_epg_tooltip"></div>

<div id="list_content">
    <!-- Combined Sticky Top Bar -->
    <div id="sticky_header_wrapper">
        <div id="list_header">
            <div id="list_head" class="clearfix">
			<form class="form" id="program_listing" onsubmit="return false;">
                <div id="epg_time_status">
                    <div id="x_current_time">
                            <span id="lbl_currently_browsing"><?php echo t('Currently Browsing:'); ?></span>
                        <?php echo ' ' . date($_SESSION['date_statusbar2'] ?? 'Y-m-d h:i A', $list_starttime); ?>
                    </div>
                    <div id="x_current_time_drag">
                            <span id="lbl_currently_browsing_drag"><?php echo t('Currently Browsing:'); ?></span>...
                    </div>
                </div>
				<div class="jumpto-container">
					<table id="x-jumpto" class="commandbox commands" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td class="x-jumpto"><span class='link' id="btn_jumpto"><?php echo t('Jump To') ?></span>:</td>
							<td class="x-hour"><?php hour_select('id="hour_select"') ?></td>
							<td class="x-day">
								<a class="link" id="btn_prev_day"><img src="<?php echo skin_url ?>img/left.gif" alt="left"></a>
								<?php date_select('id="date_select"') ?>
								<a class="link" id="btn_next_day"><img src="<?php echo skin_url ?>img/right.gif" alt="right"></a>
							</td>
						</tr>
					</table>

					<span class="link" id="btn_back_to_top">&uarr;</span>
				</div>
			</form>
            </div>
        </div>

        <!-- Sticky Navigation Bar -->
        <div class="epg-header-sticky-bar">
            <div id="timelineLeftNav" class="menu nav-arrow"></div>
            <div class="epg-timeline-wrapper" id="timelineWrapper">
                <div id="timelineHeader" class="mythweb-timeline-row"></div>
            </div>
            <div id="timelineRightNav" class="menu nav-arrow"></div>
        </div>
    </div>

    <!-- Scrollable Grid Viewport -->
    <div class="epg-container">
        <div class="epg-viewport-wrapper">
            <div class="epg-channels" id="channelLabels"></div>
            <div class="epg-scroll-container" id="scrollContainer">
                <div id="gridBody" class="epg-grid-body"></div>
            </div>
            <div class="epg-right-sidebar"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var GRID_ORIGIN_TIME = <?php echo json_encode($list_starttime); ?>;
    var SERVER_TIMEZONE  = <?php echo json_encode(date_default_timezone_get()); ?>;
    var ROOT_URL         = <?php echo json_encode(root_url); ?>;
    var SKIN_URL         = <?php echo json_encode(skin_url); ?>;
    var INITIAL_EPG_DATA = <?php echo json_encode($initial_epg_data); ?>;
</script>
<script src="<?php echo root_url ?>js/epg.js"></script>

<div id="ajax_working" class="hidden" style="z-index: 10;">
    <span id="ajax_num_requests">0</span> requests pending.
</div>

<?php require 'modules/_shared/tmpl/'.tmpl.'/footer.php'; ?>