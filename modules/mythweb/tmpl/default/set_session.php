<?php
/**
 * Configure MythWeb Session info
 *
 * @license     GPL
 * @package     MythWeb
 * @subpackage  Settings
 */
?>

<form class="form" method="post" action="<?= htmlspecialchars(form_action, ENT_QUOTES, 'UTF-8') ?>">

    <table border="0" cellspacing="0" cellpadding="0">
        <tr class="x-sep">
            <th scope="row"><?= htmlspecialchars(t('MythWeb Skin'), ENT_QUOTES, 'UTF-8') ?>:</th>
            <td><?php skin_select('skin', skin); ?></td>
        </tr>
        <tr class="x-sep">
            <th scope="row"><?= htmlspecialchars(t('Language'), ENT_QUOTES, 'UTF-8') ?>:</th>
            <td><?php language_select(); ?></td>
        </tr>
        <tr>
            <td align="center">
                <input type="reset" class="submit" value="<?= htmlspecialchars(t('Reset'), ENT_QUOTES, 'UTF-8') ?>">
            </td>
            <td align="center">
                <input type="submit" class="submit" name="save" value="<?= htmlspecialchars(t('Save'), ENT_QUOTES, 'UTF-8') ?>">
            </td>
        </tr>
    </table>

</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const skinSelect = document.getElementById('skin');
    
    if (skinSelect) {
        let currentSkin = skinSelect.value;

        skinSelect.addEventListener('change', () => {
            const newSkin = skinSelect.value;
            const stylesheets = document.querySelectorAll('head link[rel="stylesheet"]');

            stylesheets.forEach(link => {
                if (link.href.includes(`skins/${currentSkin}`)) {
                    link.href = link.href.replace(`skins/${currentSkin}`, `skins/${newSkin}`);
                }
            });

            currentSkin = newSkin;
        });
    }
});
</script>