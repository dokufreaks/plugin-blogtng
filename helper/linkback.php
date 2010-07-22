<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

class helper_plugin_blogtng_linkback extends DokuWiki_Plugin {
    public function isPost($id = false) {
        $entry = $this->getPost($id);
        return $entry['blog'] !== '';
    }

    public function getPost($id = false) {
        if ($id === false) {
            global $ID;
            $id = $ID;
        }
        $pid = md5($id);
        $ehelper = plugin_load('helper', 'blogtng_entry');
        $ehelper->load_by_pid($pid);
        return $ehelper->entry;
    }

    public function saveLinkback($type, $title, $sourceUri, $excerpt, $id) {
        $comment = array('source' => $type,
                         'name'   => $title,
                         'web'    => $sourceUri,
                         'text'   => $excerpt,
                         'pid'    => md5($id),
                         'page'   => $id,
                         'subscribe' => null,
                         'status' => 'hidden',
                         'ip' => clientIP(true));

        $sqlitehelper = plugin_load('helper', 'blogtng_sqlite');
        $query = 'SELECT web, source FROM comments WHERE pid = ?';

        $resid = $sqlitehelper->query($query, $comment['pid']);
        if ($resid === false) {
            return false;
        }

        $comments = $sqlitehelper->res2arr($resid);

        foreach($comments as $c) {
            if ($c['web'] === $comment['web'] && $c['source'] === $comment['source']) {
                return false;
            }
        }

        $chelper = plugin_load('helper', 'blogtng_comments');
        $chelper->save($comment);
        return true;
    }
}
