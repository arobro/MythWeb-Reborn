<?php
/**
 * Configure MythTV Key Bindings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/
?>
<div class="error" style="padding: 5px">
<?= t('info:jumppoints') ?>
</div>

<form class="form" method="post" action="<?= form_action ?>">
<input type="hidden" name="host" value="<?= html_entities($_SESSION['settings']['host'] ?? '') ?>" />

<table border="0" cellpadding="4" cellspacing="2" class="list small" align="center">
<tr class="menu large" align="center">
    <td colspan="3"><?= t('JumpPoints Editor') ?></td>
</tr><tr class="menu" align="center">
    <td width="33%"><?= t('Destination') ?></td>
    <td width="33%"><?= t('Description') ?></td>
    <td width="33%"><?= t('Key bindings') ?></td>
</tr><?php
    foreach ($Jumps as $jumppoint) {
?><tr class="settings" align="center">
    <td><?= html_entities($jumppoint['destination']) ?></td>
    <td><?= html_entities($jumppoint['description']) ?></td>
    <td><input type="text" size="35"
               name="jump[<?= html_entities($jumppoint['destination']) ?>]"
               value="<?= html_entities($jumppoint['keylist']) ?>"></td>
</tr><?php
    }
?>
</table>

<p></p>

<table border="0" cellpadding="4" cellspacing="2" class="list small" align="center">
<tr class="menu large" align="center">
        <td colspan="4"><?= t('Keybindings Editor') ?></td>
</tr><tr class="menu" align="center">
        <td width="15%"><?= t('Context')      ?></td>
        <td width="25%"><?= t('Action')       ?></td>
        <td width="40%"><?= t('Description')  ?></td>
        <td width="20%"><?= t('Key bindings') ?></td>
</tr><?php
    foreach ($Keys as $key) {
?><tr class="settings" align="center">
        <td><?= html_entities($key['context'])     ?></td>
        <td><?= html_entities($key['action'])      ?></td>
        <td><?= html_entities($key['description']) ?></td>
        <td><input type="text" size="25"
                   name="key[<?= html_entities($key['context']) ?>][<?= html_entities($key['action']) ?>]"
                   value="<?= html_entities($key['keylist']) ?>"></td>
</tr>
<?php
    }
?>
</table>

<p align="center">
<input type="submit" class="submit" name="save" value="<?= t('Save') ?>">
</p>

</form>