<li><div class="li">
    <a href="<?php $entry->tpl_link($comment->tpl_cid())?>" class="wikilink1"><?php $entry->tpl_title()?></a><br />
    <?php global $lang; echo $lang['by']?>
    <?php $comment->tpl_name();?>
    <?php $comment->tpl_created('%f')?>
</div></li>

