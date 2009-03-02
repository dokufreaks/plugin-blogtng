<div class="blogtng_entry">
    <?php $entry->tpl_entry(false, false, false) ?>
    <div class="blogtng_footer level1">
        This blog post was created <?php $entry->tpl_created('on %Y-%m-%d at %H:%M')?> by 
        <?php $entry->tpl_author()?>. It is tagged with <?php $entry->tpl_tags()?>.
    </div>
    <h2 id="the__comments">Comments</h2>
    <div class="level2">
        <?php $entry->tpl_comments('default') ?>
        <?php $entry->tpl_commentform() ?>
    </div>
</div>
