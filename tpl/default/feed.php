<?php echo $entry->get_entrycontent() ?>

<p>
    <small>
        <?php 
            global $lang; 
            global $conf;
            echo $lang['created'].": ";$entry->tpl_created($conf['dformat']);echo ", ";
            if ($entry->entry['created'] != $entry->entry['lastmod']) {
                echo $lang['lastmod'].": ";$entry->tpl_lastmodified($conf['dformat']);echo ", ";
            }
            echo $this->getLang('author').": ";$entry->tpl_author();echo ", ";
            if ($entry->has_tags()) {
                echo $this->getLang('tags').": ";$entry->tpl_tags('');
            }
        ?>
    </small>
</p>
