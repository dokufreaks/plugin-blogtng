<?php
/**
 * default tagsearch template
 *
 * This template is used by the <blog tagsearch> syntax and can be chosen
 * using the 'tpl' attribute. It is used to display a single entry in
 * the list and is called multiple times (once for each shown entry)
 *
 * This example shows page links and add a footer with info
 * on tags.
 *
 * @var $entry helper_plugin_blogtng_entry
 */
?>
<li class="blogtng_tagsearch">
    <a href="<?php $entry->tpl_link()?>" class="wikilink1 blogtng_permalink"><?php $entry->tpl_title()?></a>
    &middot;
    <?php global $conf;$entry->tpl_created($conf['dformat'])?>
    &middot;
    <?php $entry->tpl_author()?>
    &middot;
    Tags: <?php $entry->tpl_tags('')?>
</li>
