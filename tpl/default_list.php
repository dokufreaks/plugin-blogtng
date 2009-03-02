<div class="blog_post">
<?php $entry->tpl_entry('read more...')?>

<p class="blog_footer">
    <a href="<?php $entry->tpl_link()?>#the__comments"><?php $entry->tpl_commentcount('%d Comments', '%d Comment','%d Comments')?></a>
    &middot;
    <?php $entry->tpl_created('%Y-%m-%d')?>
    &middot;
    <?php $entry->tpl_tags()?>
</p>

</div>
