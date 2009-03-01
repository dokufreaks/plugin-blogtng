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

    function tpl_tags($fmt_tags) {
        $count = count($this->tags);
        $prepared = array();
        foreach ($this->tags as $tag) {
            array_push($prepared, '<a href="#" class="tag">'.hsc($tag).'</a>');
        }
        $html = '<span class="blogtng_tags">'.sprintf($fmt_tags, join(', ', $prepared)).'</span>';
        print $html;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
