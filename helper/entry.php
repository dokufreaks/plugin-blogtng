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
        'blog' => null,
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
        if (!preg_match('/^[0-9a-f]{32}$/', $pid)) {
            // FIXME we got an invalid pid, shout at the user accordingly
            msg('blogtng plugin: "'.$pid.'" is not a valid pid!', -1);
            return null;
        }

        $query = 'SELECT pid, page, title, blog, image, created, lastmod, author, login, email FROM entries WHERE pid = ?';
        $resid = $this->sqlite->query($query, $pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load entry!', -1);
            $this->entry = null;
            return null;
        }
        if (sqlite_num_rows($resid) == 0) {
            $this->entry = array(
                'pid' => $pid,
            );
            return false;
        }

        $result = $this->sqlite->res2arr($resid);
        $this->entry = $result[0];
        $this->entry['pid'] = $pid;
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
        $this->entry = $result;
        return true;
    }

    /**
     * Save an entry into the database
     */
    function save() {
        if(!$this->entry['pid'] || $this->entry['pid'] == md5('')){
            msg('blogtng: no pid, refusing to save',-1);
            return false;
        }

        $query = 'INSERT OR IGNORE INTO entries (pid, page, title, blog, image, created, lastmod, author, login, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $result = $this->sqlite->query(
            $query,
            $this->entry['pid'],
            $this->entry['page'],
            $this->entry['title'],
            $this->entry['blog'],
            $this->entry['image'],
            $this->entry['created'],
            $this->entry['lastmod'],
            $this->entry['author'],
            $this->entry['login'],
            $this->entry['email']
        );
        $query = 'UPDATE entries SET title=?, blog=?, image=?, lastmod=?, author=?, email=? WHERE pid=?';
        $result = $this->sqlite->query(
            $query,
            $this->entry['title'],
            $this->entry['blog'],
            $this->entry['image'],
            $this->entry['lastmod'],
            $this->entry['author'],
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
        $id = $this->entry['pageid'];
        $content = p_wiki_xhtml($id,'');

        // clean up content
        $patterns = array(
            '!<div class="toc">.*?(</div>\n</div>)!s', // remove toc
            '!<h4>(.*?)</h4>!s',                       // downsize
            '!<h3>(.*?)</h3>!s',                       // downsize
            '!<h2>(.*?)</h2>!s',                       // downsize
            '!<h1>(.*?)</h1>!s',                       // downsize
            '! href="#!',                              // fix internal links
        );
        $replace  = array(
            '',
            '<h5>\\1</h5>',
            '<h4>\\1</h4>',
            '<h3>\\1</h3>',
            '<h2>\\1</h2>',
            ' href="'.wl($id).'#');
        $content  = preg_replace($patterns,$replace,$content);

        // replace first headline with link
        $content = preg_replace('!<h2><a !s','<h2><a href="'.wl($id).'" ',$content,1);

        echo $content;
    }

    function tpl_abstract($len=0) {
        if($len){
            $abstract = utf8_substr($this->entry, 0, $len).'…';
        }else{
            $abstract = $this->entry['abstract'];
        }
        echo hsc($abstract);
    }

    function tpl_title() {
        print hsc($this->entry['title']);
    }

    function tpl_created($format='') {
        global $conf;
        if(!$format) $format = $conf['dformat'];
        print strftime($format, $this->entry['created']);
    }

    function tpl_lastmodified($format='') {
        global $conf;
        if(!$format) $format = $conf['dformat'];
        print strftime($format, $this->entry['lastmod']);
    }

    function tpl_author() {
        if(empty($this->entry['author'])) return;
        print hsc($this->entry['author']);
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
              . DOKU_TAB . '<a href="FIXME" class="fn nickname">' .
                hsc($this->entry['author']) . '</a>' . DOKU_LF
              . '</div>' . DOKU_LF;

        print $html;
    }

    function tpl_commentcount() {}
    function tpl_linkbacks() {}
    function tpl_tags() {}
}
// vim:ts=4:sw=4:et:enc=utf-8:
