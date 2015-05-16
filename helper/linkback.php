<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

/**
 * Class helper_plugin_blogtng_linkback
 */
class helper_plugin_blogtng_linkback extends DokuWiki_Plugin {
    /**
     * @return bool
     */
    public function linkbackAllowed() {
        $entry = $this->getPost();
        return !plugin_isdisabled('blogtng') &&
               $this->getConf('receive_linkbacks') &&
               $entry['blog'] !== '' && $entry['blog'] !== null &&
               $entry['commentstatus'] === 'enabled';
    }

    /**
     * @return array|null
     */
    private function getPost() {
        global $ID;
        /** @var helper_plugin_blogtng_entry $ehelper */
        $ehelper = plugin_load('helper', 'blogtng_entry');
        $ehelper->load_by_pid(md5($ID));
        return $ehelper->entry;
    }

    /**
     * @param $type
     * @param $title
     * @param $sourceUri
     * @param $excerpt
     * @param $id
     * @return bool
     */
    public function saveLinkback($type, $title, $sourceUri, $excerpt, $id) {
        /** @var helper_plugin_blogtng_sqlite $sqlitehelper */
        $sqlitehelper = plugin_load('helper', 'blogtng_sqlite');
        if (!$sqlitehelper->ready()) return false;

        $comment = array('source' => $type,
                         'name'   => $title,
                         'web'    => $sourceUri,
                         'text'   => $excerpt,
                         'pid'    => md5($id),
                         'page'   => $id,
                         'subscribe' => null,
                         'status' => 'hidden',
                         'ip' => clientIP(true));

        $query = 'SELECT web, source FROM comments WHERE pid = ?';

        $resid = $sqlitehelper->getDB()->query($query, $comment['pid']);
        if ($resid === false) {
            return false;
        }

        $comments = $sqlitehelper->getDB()->res2arr($resid);

        foreach($comments as $c) {
            if ($c['web'] === $comment['web'] && $c['source'] === $comment['source']) {
                return false;
            }
        }

        /** @var helper_plugin_blogtng_comments $chelper */
        $chelper = plugin_load('helper', 'blogtng_comments');
        $chelper->save($comment);
        return true;
    }
}
