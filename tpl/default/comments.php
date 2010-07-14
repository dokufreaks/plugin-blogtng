<?php
/**
 * default comment template
 *
 * This template is called from $entry->tpl_comments to display
 * comments. It is used to display a single comment in
 * the list and is called multiple times (once for each shown comment)
 */
?>
<div class="blogtng_comment blogtng_comment_status_<?php $comment->tpl_status()?>" id="comment_<?php $comment->tpl_cid()?>">
    <img src="<?php $comment->tpl_avatar(48,48)?>" class="avatar" width="48" height="48" alt="" align="left" />

    <?php $comment->tpl_number()?>
    <?php $comment->tpl_comment()?>
    <?php $comment->tpl_hcard()?>
    <?php $comment->tpl_created()?>
</div>
