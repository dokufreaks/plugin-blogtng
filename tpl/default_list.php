<div class="blogtng_list">
<?php
    if ($entry->tpl_entry(true, 'syntax', false)) {
?>
<div class="blogtng_footer level1">
    <a href="<?php $entry->tpl_link()?>" class="wikilink1 blogtng_permalink"><?php $entry->tpl_title()?></a>
    &middot;
    <?php $entry->tpl_created('%Y-%m-%d %H:%M')?>
    &middot;
    <?php $entry->tpl_author()?>
    &middot;
    <a href="<?php $entry->tpl_link('the__comments')?>" class="wikilink1 blogtng_commentlink"><?php $entry->tpl_commentcount('%d Comments', '%d Comment','%d Comments')?></a>
    &middot;
    Tags: <?php $entry->tpl_tags()?>
</div>
<?php
    }
?>
</div>
