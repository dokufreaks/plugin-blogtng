<?php
/**
 * default recent comments template
 *
 * This template is used in the <blog recentcomments> syntax.
 * It is used to display a single comment in
 * the list and is called multiple times (once for each shown comment)
 */
?>
<li><div class="li">
    <a href="<?php $entry->tpl_link('comment_'.$comment->data['cid'])?>" class="wikilink1"><?php $entry->tpl_title()?></a><br />
    <?php global $lang; echo $lang['by']?>
    <?php $comment->tpl_name();?>
    <?php $comment->tpl_created('%f')?>
</div></li>

