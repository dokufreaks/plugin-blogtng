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

    var $entry = null;

    var $sqlitehelper = null;
    var $commenthelper = null;

    /**
     * Constructor, loads the sqlite helper plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function helper_plugin_blogtng_entry() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
        $this->entry = $this->prototype();
    }

    /**
     * Return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function load_by_pid($pid) {
        $pid = trim($pid);
        if (!$this->is_valid_pid($pid)) {
            // FIXME we got an invalid pid, shout at the user accordingly
            msg('blogtng plugin: "'.$pid.'" is not a valid pid!', -1);
            return null;
        }

        $query = 'SELECT pid, page, title, blog, image, created, lastmod, author, login, email FROM entries WHERE pid = ?';
        $resid = $this->sqlitehelper->query($query, $pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load entry!', -1);
            $this->entry = $this->prototype();
            return null;
        }
        if (sqlite_num_rows($resid) == 0) {
            $this->entry = $this->prototype();
            $this->entry['pid'] = $pid;
            return false;
        }

        $result = $this->sqlitehelper->res2arr($resid);
        $this->entry = $result[0];
        $this->entry['pid'] = $pid;
        return $this->poke();
    }

    function load_by_res($resid, $index) {
        // FIXME validate resid and index
        if($resid === false) {
            msg('blogtng plugin: failed to load entry, did not get a valid resource id!', -1);
            $this->entry = $this->prototype();
            return false;
        }

        $result = $this->sqlitehelper->res2row($resid, $index);
        $this->entry = $result;
        return $this->poke();
    }

    function set($entry) {
        foreach (array_keys($entry) as $key) {
            if (!in_array($key, array('pid', 'page', 'created', 'login')) || empty($this->entry[$key])) {
                $this->entry[$key] = $entry[$key];
            }
        }
    }

    function prototype() {
        return array(
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
    }

    /**
     * Poke the entry with a stick and see if it is alive
     *
     * If page does not exist, delete DB entry
     */
    function poke(){
        if(!$this->entry['page']) return true;

        if(!page_exists($this->entry['page'])){
            $this->delete();
            return false;
        }
        return true;
    }

    /**
     * delete the current entry
     */
    function delete(){
        if(!$this->entry['pid']) return false;

        $sql = "DELETE FROM entry WHERE pid = ?";
        $ret = $this->sqlitehelper->query($sql,$this->entry['pid']);
        $this->entry = $this->prototype();
        return (bool) $ret;
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
        $result = $this->sqlitehelper->query(
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
        $query = 'UPDATE entries SET page = ?, title=?, blog=?, image=?, created = ?, lastmod=?, login = ?, author=?, email=? WHERE pid=?';
        $result = $this->sqlitehelper->query(
            $query,
            $this->entry['page'],
            $this->entry['title'],
            $this->entry['blog'],
            $this->entry['image'],
            $this->entry['created'],
            $this->entry['lastmod'],
            $this->entry['login'],
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

    function get_blogs() {
        $pattern = DOKU_PLUGIN . 'blogtng/tpl/*_entry.php';
        $files = glob($pattern);
        $blogs = array('');
        foreach ($files as $file) {
            array_push($blogs, substr($file, strlen(DOKU_PLUGIN . 'blogtng/tpl/'), -10));
        }
        return $blogs;
    }

    function get_blog() {
        if ($this->entry != null) {
            return $this->entry['blog'];
        } else {
            return '';
        }
    }

    function tpl_content($tpl) {
        $tpl = DOKU_PLUGIN . 'blogtng/tpl/' . $tpl . '_list.php';
        if(file_exists($tpl)) {
            $entry = $this;
            include($tpl);
        } else {
            msg('blogtng plugin: template ' . $tpl . ' does not exist!', -1);
        }
    }

    // FIXME readmore
    function tpl_entry() {
        static $recursion = false;
        if($recursion){
            msg('blogtng: preventing infinite loop',-1);
            return false; // avoid infinite loops
        }
        $recursion = true;

        $id = $this->entry['page'];
        $content = p_wiki_xhtml($id,'');

        $recursion = false;

        // clean up content
        $patterns = array(
            '!<div class="toc">.*?(</div>\n</div>)!s', // remove toc
            '!<h4>(.*?)</h4>!s',                       // downsize
            '!<h3>(.*?)</h3>!s',                       // downsize
            '!<h2>(.*?)</h2>!s',                       // downsize
            '!<h1>(.*?)</h1>!s',                       // downsize
            '!<div class="level4">!s',                 // downsize
            '!<div class="level3">!s',                 // downsize
            '!<div class="level2">!s',                 // downsize
            '!<div class="level1">!s',                 // downsize
            '! href="#!',                              // fix internal links
        );
        $replace  = array(
            '',
            '<h5>\\1</h5>',
            '<h4>\\1</h4>',
            '<h3>\\1</h3>',
            '<h2>\\1</h2>',
            '<div class="level5">',
            '<div class="level4">',
            '<div class="level3">',
            '<div class="level2">',
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

    /**
     * Print comments
     *
     * Wrapper around commenthelper->tpl_comments()
     */
    function tpl_comments($author_url='email') {
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comment');
        }
        $this->commenthelper->load($this->entry['pid']);
        $this->commenthelper->tpl_comments($author_url);
    }

    /**
     * Print comment count
     *
     * Wrapper around commenthelper->tpl_commentcount()
     */
    function tpl_commentcount($fmt_zero_comments, $fmt_one_comment, $fmt_comments) {
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comment');
        }
        $this->commenthelper->load($this->entry['pid']);
        $this->commenthelper->tpl_count($fmt_zero_comments, $fmt_one_comment, $fmt_comments);
    }

    /**
     * Print comment form
     *
     * Wrapper around commenthelper->tpl_form()
     */
    function tpl_commentform() {
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comment');
        }
        $this->commenthelper->tpl_form($this->entry['pid']);
    }

    function tpl_linkbacks() {}
    function tpl_tags() {}

    function is_valid_pid($pid) {
        return (preg_match('/^[0-9a-f]{32}$/', trim($pid)));
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
