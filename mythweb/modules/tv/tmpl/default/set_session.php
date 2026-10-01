<?php
/**
 * Configure MythWeb Session info
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/
?>

<form class="form" method="post" action="<?php echo defined('form_action') ? form_action : '' ?>">

<table border="0" cellspacing="0" cellpadding="0">
<tr>
    <td colspan="2"><?php echo t('Recorded Programs') ?>:</th>
</tr><tr>
    <th><label for="recorded_pixmaps"><?php echo t('Show pixmaps') ?></label>:</th>
    <td><input class="radio" type="checkbox" title="Show recording thumbnails." id="recorded_pixmaps" name="recorded_pixmaps"<?php if (!empty($_SESSION['recorded_pixmaps'])) echo ' CHECKED' ?>></td>
</tr><tr>
    <th><?php echo t('Download URL override') ?></label>:</th>
    <td>file://<input type="text" id="file_url_override" name="file_url_override" value="<?php echo html_entities(trim($_SESSION['file_url_override'] ?? '')) ?>" title="Instead of streaming downloaded recordings, load from a local file:// path instead."></td>
</tr><tr>
    <th><?php echo t('Page recorded programs') ?></label>:</th>
    <td><select name="recorded_paging">
    <?php
        foreach(array('null', 10, 25, 50, 100) as $value) {
            echo '<option value="'.$value.'" ';
            if (isset($_SESSION['recorded_paging']) && $value == $_SESSION['recorded_paging'])
                echo ' SELECTED ';
            if ($value == 'null')
                $value = t('Off');
            echo '>'.$value.'</option>';
        }
    ?>
    </select></td>
</tr><tr class="x-sep">
    <td colspan="2"><?php echo t('Guide Settings') ?>:</th>
</tr><tr>
    <th><?php echo t('Only display favorite channels') ?>:</th>
    <td ><input class="radio" type="checkbox" title="In the program listing, only show channels marked as favorite channels" name="guide_favonly"<?php if (!empty($_SESSION['guide_favonly'])) echo ' CHECKED' ?>></td>
</tr><tr>
    <th><?php echo t('Max star rating for movies') ?>:</th>
    <td><input type="text" size="5" name="max_stars" value="<?php echo intVal($_SESSION['max_stars'] ?? 0) ?>"></td>
</tr><tr>
    <th><?php echo t('Star character') ?>:</th>
    <td><input type="text" size="5" name="star_character" value="<?php echo html_entities($_SESSION['star_character'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Timeslot size') ?>:</th>
    <td><input type="text" size="5" name="timeslot_size" value="<?php echo intVal(($_SESSION['timeslot_size'] ?? 0) / 60) ?>"> <?php echo t('minutes') ?></td>
</tr><tr>
    <th><?php echo t('Number of timeslots') ?>:</th>
    <td><input type="text" size="5" name="num_time_slots" value="<?php echo intVal($_SESSION['num_time_slots'] ?? 0) ?>"></td>
</tr><tr>
    <th><?php echo t('Group timeslots every') ?>:</th>
    <td><input type="text" size="5" name="timeslot_blocks" value="<?php echo intVal($_SESSION['timeslot_blocks'] ?? 0) ?>"></td>
</tr><tr class="x-sep">
    <th><?php echo t('Rows to show between timeslot info') ?>:</th>
    <td><input type="text" size="5" name="timeslotbar_skip" value="<?php echo intVal($_SESSION['timeslotbar_skip'] ?? 0) ?>"></td>
</tr><tr>
    <td><?php echo t('Date Formats') ?>:</th>
    <td><div class="small" style="float:right"><a href="https://php.net/manual/en/function.date.php" target="_blank"><?php echo t('format help') ?></a></div></td>
</tr><tr>
    <th><?php echo t('Status Bar') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_statusbar2" value="<?php    echo html_entities($_SESSION['date_statusbar2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Upcoming Recordings') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_scheduled2" value="<?php    echo html_entities($_SESSION['date_scheduled2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Scheduled Popup') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_scheduled_popup2" value="<?php echo html_entities($_SESSION['date_scheduled_popup2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Recorded Programs') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_recorded2" value="<?php     echo html_entities($_SESSION['date_recorded2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Search Results') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_search2" value="<?php       echo html_entities($_SESSION['date_search2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Listing Time Key') ?>:&nbsp;</td>
    <td><input type="text" size="24" name="date_listing_key2" value="<?php  echo html_entities($_SESSION['date_listing_key2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Listing &quot;Jump to&quot;') ?>&nbsp;</td>
    <td><input type="text" size="24" name="date_listing_jump2" value="<?php echo html_entities($_SESSION['date_listing_jump2'] ?? '') ?>"></td>
</tr><tr>
    <th><?php echo t('Channel &quot;Jump to&quot;') ?>&nbsp;</td>
    <td><input type="text" size="24" name="date_channel_jump2" value="<?php echo html_entities($_SESSION['date_channel_jump2'] ?? '') ?>"></td>
</tr><tr class="x-sep">
    <th><?php echo t('Hour Format') ?>&nbsp;</td>
    <td><select name="time_format2" style="text-align: center"><?php
        foreach (array('g:i A', 'H:i') as $code) {
            echo "<option value=\"$code\"";
            if (isset($_SESSION['time_format2']) && $_SESSION['time_format2'] == $code)
                echo ' SELECTED';
            echo '>'.date($code, strtotime('9:00 AM')).' / '.date($code, strtotime('9:00 PM')).'</option>';
        }
        ?></select></td>
</tr><tr>
    <td align="center"><input type="reset" value="<?php echo t('Reset') ?>"></td>
    <td align="center"><input type="submit" name="save" value="<?php echo t('Save') ?>"></td>
</tr>
</table>

</form>