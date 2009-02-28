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
        'pid' => null,
        'page' => null,
        'title' => null,
        'image' => null,
        'created' => null,
        'lastmod' => null,
        'author' => null,
        'login' => null,
        'email' => null,
    );

    var $sqlite = null;

    /**
     * Constructor, loads the sqlite helper plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function helper_plugin_blogtng_entry() {
        $this->sqlite =& plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function load_by_pid($pid) {
        $pid = trim($pid);
        if (!preg_match('^[0-9a-f]{32}$', $pid)) {
            // FIXME we got an invalid pid, shout at the user accordingly
            return false;
        }

        $query = 'SELECT page, title, image, created, lastmod, author, login, email FROM entries WHERE pid = ?';
        $resid = $this->sqlite->query($query, $pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load entry', -1);
            $this->entry = null;
            return false;
        }

        $result = $this->sqlite->res2arr($resid);
        $this->entry = array(
            'pid' => $pid,
            'page' => array_shift($result[0]),
            'title' => array_shift($result[0]),
            'image' => array_shift($result[0]),
            'created' => array_shift($result[0]),
            'lastmod' => array_shift($result[0]),
            'author' => array_shift($result[0]),
            'login' => array_shift($result[0]),
            'email' => array_shift($result[0]),
        );
        return true;
    }

    function load_by_res($resid, $index) {
        // FIXME validate resid and index
        if($resid === false) {
            msg('blogtng plugin: failed to load entry, did not get a valid resource id!', -1);
            $this->entry = null;
            return false;
        }

        $result = $this->sqlite->res2row($resid, $index);
        $this->entry = array(
            'pid' => array_shift($result),
            'page' => array_shift($result),
            'title' => array_shift($result),
            'image' => array_shift($result),
            'created' => array_shift($result),
            'lastmod' => array_shift($result),
            'author' => array_shift($result),
            'login' => array_shift($result),
            'email' => array_shift($result),
        );
        return true;
    }

    /**
     * Save an entry into the database
     */
    function save() {
        $query = 'INSERT OR IGNORE INTO entries (pid, page, title, image, created, lastmod, author, login, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $result = $this->sqlite->query(
            $query,
            $this->entry['pid'],
            $this->entry['page'],
            $this->entry['title'],
            $this->entry['image'],
            $this->entry['created'],
            $this->entry['lastmod'],
            $this->entry['author'],
            $this->entry['login'],
            $this->entry['email']
        );
        $query = 'UPDATE entries SET page=?, title=?, image=?, lastmod=?, author=?, login=?, email=? WHERE pid=?';
        $result = $this->sqlite->query(
            $query,
            $this->entry['page'],
            $this->entry['title'],
            $this->entry['image'],
            $this->entry['lastmod'],
            $this->entry['author'],
            $this->entry['login'],
            $this->entry['email'],
            $this->entry['pid']
        );
        if(!$result) {
            msg('blogtng plugin: failed to save new entry!', -1);
            return false;
        } else {
            return true;
        }
    }

    function tpl_content($tpl) {
        $tpl = DOKU_PLUGIN . 'blogtng/tpl/' . $tpl . '.php';
        if(file_exists($tpl)) {
            $entry = $this->entry;
            include($tpl);
        } else {
            msg('blogtng plugin: template ' . $tpl . ' does not exist!', -1);
        }
    }

    // FIXME readmore
    function tpl_entry() {
        print p_wiki_xhtml($this->entry['pid'], '');
    }

    // FIXME abstract lenght
    function tpl_abstract() {
        print $this->entry['abstract'];
    }

    function tpl_title() {
        print $this->entry['title'];
    }

    function tpl_created($format) {
        print strftime($format, $this->entry['created']);
    }

    function tpl_lastmodified($format) {
        print strftime($format, $this->entry['lastmod']);
    }

    function tpl_author() {
        if(empty($this->entry['author'])) return;
        print $this->entry['author'];
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
        // option to link author name with email or webpage?

        $html = '<div class="vcard">'
              . DOKU_TAB . '<a href="FIXME" class="fn nickname">' . $this->entry['author'] . '</a>' . DOKU_LF
              . '</div>' . DOKU_LF;

        print $html;
    }

    function tpl_commentcount() {}
    function tpl_linkbacks() {}
    function tpl_tags() {}
}
// vim:ts=4:sw=4:et:enc=utf-8:
