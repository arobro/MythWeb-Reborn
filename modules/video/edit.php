<?php
/**
 * Edit MythVideo file information
 *
 *
 * @package     MythWeb
 * @subpackage  Video
 *
 **/

/** @todo FIXME: this file needs to be rewritten to split out content/display */

header("Content-Type: text/html; charset=utf-8");
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="<?php echo root_url; ?>dcss/style.css" />
    <style type="text/css">
    <!--
        body {
           font-family:         Arial, Helvetica, sans-serif;
           font-size:           12px;
           background-color:    green;
           color:               white;
        }

        input, select, textarea {
            width:              90%;
            margin-left:        1em;
        }

    -->
    </style>
<?php
//get data to prefill form if we're editing an existing entry
    $intid = $_REQUEST['intid'] ?? null;
    $Video = new Video($intid);

//check to see if the form has been submitted
    if (isset($_REQUEST['submit'])) {
        $Video->title       = $_REQUEST['title'] ?? null;
        $Video->subtitle    = $_REQUEST['subtitle'] ?? null;
        $Video->season      = $_REQUEST['season'] ?? null;
        $Video->episode     = $_REQUEST['episode'] ?? null;
        $Video->director    = $_REQUEST['director'] ?? null;
        $Video->plot        = $_REQUEST['plot'] ?? null;
        $Video->category    = $_REQUEST['category'] ?? null;
        $Video->rating      = $_REQUEST['rating'] ?? null;
        $Video->inetref     = $_REQUEST['inetref'] ?? null;
        $Video->year        = $_REQUEST['year'] ?? null;
        $Video->userrating  = $_REQUEST['userrating'] ?? null;
        $Video->length      = $_REQUEST['length'] ?? null;
        $Video->showlevel   = $_REQUEST['showlevel'] ?? null;
        $Video->browse      = $_REQUEST['browse'] ?? null;
        if (isset($_FILES['coverfile']['tmp_name']) && is_uploaded_file($_FILES['coverfile']['tmp_name'])) {
            $filename = setting('VideoArtworkDir', hostname).'/id-'.$intid.'.jpg';
            move_uploaded_file($_FILES['coverfile']['tmp_name'], $filename);
            chmod($filename, 0644); // make cover file readable by other users
            $Video->cover_file = $filename;
        }
        $Video->save();
        ?>
        <script type="text/javascript">
        <!--Hide script from old browsers
            parent.update_video('<?php echo htmlspecialchars((string)$intid, ENT_QUOTES); ?>');
            parent.$('window').hide();
        //Stop hiding script from old browsers -->
        </script>
        <?php
    }

?>

</head>
<body>

<form method="post" action="<?php echo root_url ?>video/edit" enctype="multipart/form-data">
<?php echo t('Title'); ?><br>
<input name="title" type="text" value="<?php echo htmlspecialchars((string)($Video->title ?? ''), ENT_QUOTES ); ?>"><br><br>
<?php echo t('Subtitle'); ?><br>
<input name="subtitle" type="text" value="<?php echo htmlspecialchars((string)($Video->subtitle ?? ''), ENT_QUOTES ); ?>"><br><br>
<?php echo t('Season'); ?><br>
<input name="season" type="text" size="3" value="<?php echo intval($Video->season ?? 0); ?>"><br><br>
<?php echo t('Episode'); ?><br>
<input name="episode" type="text" size="3" value="<?php echo intval($Video->episode ?? 0); ?>"><br><br>
<?php echo t('Director'); ?><br>
<input name="director" type="text" value="<?php echo htmlspecialchars((string)($Video->director ?? ''), ENT_QUOTES ); ?>"><br><br>
<?php echo t('Plot'); ?><br>
<textarea name="plot" rows="5" cols="30" wrap="VIRTUAL"><?php echo htmlspecialchars((string)($Video->plot ?? ''), ENT_QUOTES ); ?></textarea><br><br>
<?php echo t('Category'); ?><br>
<select name="category">
<option <?php if (($Video->category ?? null) == 0) echo ' SELECTED'; ?> value="0"><?php echo t('Uncategorized'); ?></option>
<?php
    $sh = $db->query('SELECT * FROM videocategory');
    while ($cat_data = $sh->fetch_assoc()) {
        echo '<option value="'.$cat_data['intid'].'"';
        if (($Video->category ?? null) == $cat_data['intid'])
            echo ' SELECTED';
        echo '>'.html_entities($cat_data['category']).'</option>';
    }
    $sh->finish();
?></select><br><br>
<?php echo t('Rating'); ?><br>
<input name="rating" type="text" value="<?php echo htmlspecialchars((string)($Video->rating ?? ''), ENT_QUOTES); ?>"><br><br>
<?php echo t('IMDB'); ?><br>
<input name="inetref" type="text" value="<?php echo htmlspecialchars((string)($Video->inetref ?? ''), ENT_QUOTES); ?>"><br><br>
<?php echo t('Year'); ?><br>
<input name="year" type="text" size="4" value="<?php echo htmlspecialchars((string)($Video->year ?? ''), ENT_QUOTES); ?>"><br><br>
<?php echo t('User Rating'); ?><br>
<input name="userrating" type="text" size="3" value="<?php echo htmlspecialchars((string)($Video->userrating ?? ''), ENT_QUOTES); ?>"><br><br>
<?php echo t('Length in minutes'); ?><br>
<input name="length" type="text" size="3" value="<?php echo htmlspecialchars((string)($Video->length ?? ''), ENT_QUOTES); ?>"><br><br>
<?php echo t('Browsable'); ?><br>
<select name="browse">
 <option value="0" <?php if (($Video->browse ?? null) == 0) echo ' SELECTED'; ?>><?php echo t('No'); ?></option>
 <option value="1" <?php if (($Video->browse ?? null) == 1) echo ' SELECTED'; ?>><?php echo t('Yes'); ?></option>
</select><br><br>
<?php echo t('Parental Level'); ?><br>
<select name="showlevel">
 <option value="1" <?php if (($Video->showlevel ?? null) == 1) echo ' SELECTED'; ?>><?php echo t('1 - Lowest'); ?></option>
 <option value="2" <?php if (($Video->showlevel ?? null) == 2) echo ' SELECTED'; ?>>2</option>
 <option value="3" <?php if (($Video->showlevel ?? null) == 3) echo ' SELECTED'; ?>>3</option>
 <option value="4" <?php if (($Video->showlevel ?? null) == 4) echo ' SELECTED'; ?>><?php echo t('4 - Highest'); ?></option>
</select><br><br>
<?php echo t('Cover Image'); ?><br>
<input type="file" name="coverfile"><br><br>
<input type="hidden" name="intid" value="<?php echo htmlspecialchars((string)$intid, ENT_QUOTES); ?>">
<input class="submit" type="submit" name="submit" value="<?php echo t('Submit'); ?>">

</form>

</body>
</html>