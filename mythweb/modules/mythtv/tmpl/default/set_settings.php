<?php
/**
 * Configure MythTV Settings table
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/
?>
<form class="form" method="post" action="<?= form_action ?>">
<input type="hidden" name="host" value="<?= html_entities($_SESSION['settings']['host'] ?? '') ?>" />

<table border="0" cellpadding="4" cellspacing="2" class="list small" align="center">
<tr class="menu" align="center">
    <td><?= t('Value') ?></td>
    <td><?= t('Data') ?></td>
    <td><?= t('Delete') ?></td>
</tr><?php
    foreach ($MythSettings as $value => $data) {
?><tr class="settings">
    <td align="right"><?= html_entities($value) ?></td>
    <td><input type="text" size="64" name="settings[<?= html_entities($value) ?>]" value="<?= html_entities($data) ?>"></td>
    <td><input type="checkbox" name="delete[<?= html_entities($value) ?>]" value="1"></td>
</tr><?php
    }
?>
</table>

<p align="center">
<input type="submit" class="submit" name="save" value="<?= t('Save') ?>">
</p>

</form>