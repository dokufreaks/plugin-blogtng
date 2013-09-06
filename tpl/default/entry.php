<?php
/**
 * default entry template
 *
 * This template is used to display a single entry when the 'default'
 * blog was chosen from the dropdown on editing a page.
 *
 * It displays the entry and adds comments and navigational elements.
 *
 * @var $entry helper_plugin_blogtng_entry
 */
?>
<div class="blogtng_entry">
    <div class="blogtng_postnavigation level1">
    <?php if ($link = $entry->tpl_previouslink('« @TITLE@', $entry->entry['page'], true)) { ?>
        <div class="blogtng_prevlink">
            <?php echo $link?>
        </div>
    <?php } ?>
    <?php if ($link = $entry->tpl_nextlink('@TITLE@ »', $entry->entry['page'], true)) { ?>
        <div class="blogtng_nextlink">
            <?php echo $link?>
        </div>
    <?php } ?>
    </div>
    <?php $entry->tpl_entry(true, false, false) ?>
    <div class="blogtng_footer level1">
        <?php 
            global $lang; 
            global $conf;
            echo $this->getLang('created').": ";$entry->tpl_created($conf['dformat']);echo ", ";
            if ($entry->entry['created'] != $entry->entry['lastmod']) {
                echo $lang['lastmod'].": ";$entry->tpl_lastmodified($conf['dformat']);echo ", ";
            }
            echo $this->getLang('author').": ";$entry->tpl_author();
            if ($entry->has_tags()) {
                echo ", ";
                echo $this->getLang('tags').": ";$entry->tpl_tags('');
            }
        ?>
    </div>
    <?php if ($entry->entry['commentstatus'] != 'disabled') {?>
    <h2 id="the__comments"><?php echo $this->getLang('comments');?></h2>
        <div class="level2">
            <?php $entry->tpl_comments(basename(dirname(__FILE__))) ?>
            <?php $entry->tpl_commentform() ?>
        </div>
    <?php } ?>
</div>
