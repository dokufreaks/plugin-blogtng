<?php echo $entry->get_entrycontent() ?>

<p>
    <small>
        <?php 
            global $lang; 
            echo $lang['created'].": ";$entry->tpl_created('%Y-%m-%d %H:%M');echo ", ";
            if ($entry->entry['created'] != $entry->entry['lastmod']) {
                echo $lang['lastmod'].": ";$entry->tpl_lastmodified('%Y-%m-%d %H:%M');echo ", ";
            }
            echo $this->getLang('author').": ";$entry->tpl_author();echo ", ";
            if ($entry->has_tags()) {
                echo $this->getLang('tags').": ";$entry->tpl_tags('');
            }
        ?>
    </small>
</p>
