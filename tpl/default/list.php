<?php
/**
 * default list template
 *
 * This template is used by the <blog list> syntax and can be chosen
 * using the 'tpl' attribute. It is used to display a single entry in
 * the list and is called multiple times (once for each shown entry)
 *
 * This example shows full entries and add a footer with info
 * on tags and comments.
 *
 * @var $entry helper_plugin_blogtng_entry
 */
?>
<div class="blogtng_list">
<?php
    if ($entry->tpl_entry(true, 'syntax', false)) {
?>
<div class="blogtng_footer level1">
<a href="<?php $entry->tpl_link()?>" class="wikilink1 blogtng_permalink"><?php echo $this->getLang('permalink')?></a>
    &middot;
    <?php global $conf; $entry->tpl_created($conf['dformat'])?>
    &middot;
    <?php $entry->tpl_author()?>
    &middot;
    <a href="<?php $entry->tpl_link('the__comments')?>" class="wikilink1 blogtng_commentlink"><?php $entry->tpl_commentcount($this->getLang('0comments'),$this->getLang('1comments'),$this->getLang('Xcomments'))?></a>
    &middot;
    <?php echo $this->getLang('tags').": ";$entry->tpl_tags('')?>
</div>
<?php
    }
?>
</div>
