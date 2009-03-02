<div class="blogtng_entry">
    <?php $entry->tpl_entry() ?>
    <div class="blogtng_tags">
        <?php $entry->tpl_tags() ?>
    </div>
    <h2 id="the__comments">Comments</h2>
    <div class="level2">
        <?php $entry->tpl_comments('default') ?>
        <?php $entry->tpl_commentform() ?>
    </div>
</div>
