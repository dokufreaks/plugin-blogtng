<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class helper_plugin_blogtng_entry extends DokuWiki_Plugin {

    var $entry = array(
        'pid' => null;
        'page' => null;
        'title' => null;
        'image' => null;
        'created' => null;
        'lastmod' => null;
        'author' => null;
        'login' => null;
    );

    var $sqlite = null;

    /**
     * Constructor, loads the sqlite helper plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function helper_plugin_blogtng_entry() {
        $sqlite =& plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    /**
     * Populate the helper plugin with the data of the blog entry
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function load($pid) {
        $query = 'FIXME';
        $this->entry = $this->sqlite->query($query);
    }

    /**
     * Save an entry into the database
     */
    function save() {
        $query = 'FIXME';
        $this->sqlite->query($query);
    }

    /**
     * Print a simple hcard
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function tpl_hcard() {
        if(empty($this->entry['author'])) return;

        // FIXME 
        // which url to link email/wiki/user page

        $html = '<div class="vcard">'
              . DOKU_TAB . '<a href="FIXME" class="fn nickname">' . $this->entry['author'] . '</a>' . DOKU_LF
              . '</div>' . DOKU_LF;

        print $html;
    }

    function tpl_discussion() {}
    function tpl_linkbacks() {}
    function tpl_tags() {}
}
// vim:ts=4:sw=4:et:enc=utf-8:
