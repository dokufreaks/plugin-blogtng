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
    <img src="<?php $comment->tpl_avatar(60,60)?>" class="avatar" width="60" height="60" alt="" align="left" />

    <?php $comment->tpl_hcard()?><?php $comment->tpl_created()?> <?php echo '<a href="#comment_'.$comment->data["cid"].'" class="blogtng_num">'.$comment->getLang('comment_reply').'</a>'?>
    <?php $comment->tpl_comment()?>
</div>
