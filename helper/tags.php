<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <gina@foosel.net>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class helper_plugin_blogtng_tags extends DokuWiki_Plugin {

    var $sqlitehelper = null;

    var $tags = array();

    var $pid = null;

    /**
     * Constructor, loads the sqlite helper plugin
     */
    function helper_plugin_blogtng_tags() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Return some info
     */
    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    /**
     * Load tags for specified pid
     */
    function load($pid) {
        $this->pid = trim($pid);
        $query = 'SELECT tag FROM tags WHERE pid = ? ORDER BY tag ASC';

        $resid = $this->sqlitehelper->query($query, $this->pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load tags!', -1);
            $this->tags = array();
        }
        if (sqlite_num_rows($resid) == 0) {
            $this->tags = array();
        }

        $tags_from_db = $this->sqlitehelper->res2arr($resid);
        $tags = array();
        foreach ($tags_from_db as $tag_from_db) {
            array_push($tags, $tag_from_db['tag']);
        }
        $this->tags = $tags;
    }

    /**
     * Save comment
     */
    function save() {
        $query = 'BEGIN TRANSACTION';
        $this->sqlitehelper->query($query);
        $query = 'DELETE FROM tags WHERE pid = ?';
        $this->sqlitehelper->query($query, $this->pid);
        foreach ($this->tags as $tag) {
            $query = 'INSERT INTO tags (pid, tag) VALUES (?, ?)';
            $this->sqlitehelper->query($query, $this->pid, $tag);
        }
        $query = 'END TRANSACTION';
        $this->sqlitehelper->query($query);
    }

    function set($tags) {
        $this->tags = array_unique(array_filter(array_map('trim', $tags)));
    }

    function join_tag_query($tagquery) {
        if (!$tagquery) {
            return null;
        }

        $tags = array_map('trim', explode(' ', $tagquery));
        $tag_clauses = array(
            'OR' => array(),
            'AND' => array(),
            'NOT' => array(),
        );
        foreach ($tags as $tag) {
            if ($tag{0} == '+') {
                array_push($tag_clauses['AND'], 'tag = \'' . sqlite_escape_string(substr($tag, 1)) . '\'');
            } else if ($tag{0} == '-') {
                array_push($tag_clauses['NOT'], 'tag != \'' . sqlite_escape_string(substr($tag, 1)) . '\'');
            } else {
                array_push($tag_clauses['OR'], 'tag = \'' . sqlite_escape_string($tag) . '\'');
            }
        }
        $tag_clauses = array_map('array_unique', $tag_clauses);

        $where = '';
        if ($tag_clauses['OR']) {
            $where .= '('.join(' OR ', $tag_clauses['OR']).')';
        }
        if ($tag_clauses['AND']) {
            $where .= (!empty($where) ? ' AND ' : '').join(' AND ', $tag_clauses['AND']);
        }
        if ($tag_clauses['NOT']) {
            $where .= (!empty($where) ? ' AND ' : '').join(' AND ', $tag_clauses['NOT']);
        }
        return $where;
    }

    function tpl_tags($target){
        $count = count($this->tags);
        $prepared = array();
        foreach ($this->tags as $tag) {
            array_push($prepared, DOKU_TAB.'<li><div class="li"><a href="'.wl($target,array('btng[post][tags]'=>$tag)).'" class="tag">'.hsc($tag).'</a></div></li>');
        }
        $html = '<ul class="blogtng_tags">'.DOKU_LF.join(DOKU_LF, $prepared).'</ul>'.DOKU_LF;
        echo $html;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
