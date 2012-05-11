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

    const RET_OK          = 1;
    const RET_ERR_DB      = -1;
    const RET_ERR_BADPID  = -2;
    const RET_ERR_NOENTRY = -3;
    const RET_ERR_DEL     = -4;
    const RET_ERR_RES     = -5;


    var $entry = null;

    var $sqlitehelper  = null;
    var $commenthelper = null;
    var $taghelper     = null;
    var $toolshelper   = null;

    var $renderer      = null;

    /**
     * Constructor, loads the sqlite helper plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function helper_plugin_blogtng_entry() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
        $this->entry = $this->prototype();
    }


    //~~ data access methods

    function load_by_pid($pid) {
        $this->entry = $this->prototype();
        $this->taghelper = null;
        $this->commenthelper = null;

        $pid = trim($pid);
        if (!$this->is_valid_pid($pid)) {
            // FIXME we got an invalid pid, shout at the user accordingly
            msg('blogtng plugin: "'.$pid.'" is not a valid pid!', -1);
            return self::RET_ERR_BADPID;
        }

        $query = 'SELECT pid, page, title, blog, image, created, lastmod, author, login, mail, commentstatus FROM entries WHERE pid = ?';
        $resid = $this->sqlitehelper->query($query, $pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load entry!', -1);
            $this->entry = $this->prototype();
            return self::RET_ERR_DB;
        }
        if (sqlite_num_rows($resid) == 0) {
            $this->entry = $this->prototype();
            $this->entry['pid'] = $pid;
            return self::RET_ERR_NOENTRY;
        }

        $result = $this->sqlitehelper->res2arr($resid);
        $this->entry = $result[0];
        $this->entry['pid'] = $pid;
        if($this->poke()){
            return self::RET_OK;
        }else{
            return self::RET_ERR_DEL;
        }
    }

    /**
     * FIXME: Is this deprecated?
     *
     * @param $resid
     * @param $index
     * @return unknown_type
     */
    function load_by_res($resid, $index) {
        $this->entry = $this->prototype();
        $this->taghelper = null;
        $this->commenthelper = null;

        // FIXME validate resid and index
        if($resid === false) {
            msg('blogtng plugin: failed to load entry, did not get a valid resource id!', -1);
            $this->entry = $this->prototype();
            // FIXME undefined constant
            return self::RET_ERR_BADRES;
        }

        $result = $this->sqlitehelper->res2row($resid, $index);
        $this->load_by_row($result);
    }

    function load_by_row($row) {
        $this->entry = $row;
        if($this->poke()){
            return self::RET_OK;
        }else{
            return self::RET_ERR_DEL;
        }
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
            'mail' => null,
        );
    }

    /**
     * Poke the entry with a stick and see if it is alive
     *
     * If page does not exist, delete DB entry
     */
    function poke(){
        if(!$this->entry['page'] or !page_exists($this->entry['page'])){
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

        // delete comment
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        }
        $this->commenthelper->delete_all($this->entry['pid']);

        // delete entry
        $sql = "DELETE FROM entries WHERE pid = ?";
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

        $query = 'INSERT OR IGNORE INTO entries (pid, page, title, blog, image, created, lastmod, author, login, mail, commentstatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
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
            $this->entry['mail'],
            $this->entry['commentstatus']
        );
        $query = 'UPDATE entries SET page = ?, title=?, blog=?, image=?, created = ?, lastmod=?, login = ?, author=?, mail=?, commentstatus=? WHERE pid=?';
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
            $this->entry['mail'],
            $this->entry['commentstatus'],
            $this->entry['pid']
        );
        if(!$result) {
            msg('blogtng plugin: failed to save new entry!', -1);
            return false;
        } else {
            return true;
        }
    }

    //~~ xhtml functions

    /**
     * List matching blog entries
     *
     * Calls the *_list template for each entry in the result set
     */
    function xhtml_list($conf, &$renderer=null){
        $posts = $this->get_posts($conf);
        if (!$posts) return '';
        $rendererBackup =& $this->renderer;
        $this->renderer =& $renderer;

        $entryBackup = $this->entry;
        ob_start();
        if($conf['listwrap']) echo '<ul class="blogtng_list">';
        foreach ($posts as $row) {
            $this->load_by_row($row);
            $this->tpl_content($conf['tpl'], 'list');
        }
        if($conf['listwrap']) echo '</ul>';
        $output = ob_get_contents();
        ob_end_clean();
        $this->entry = $entryBackup; // restore previous entry in order to allow nesting
        $this->renderer =& $rendererBackup; // clean up again
        return $output;
    }

    /**
     * Display pagination links for the configured list of entries
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     */
    function xhtml_pagination($conf){
        $sortkey = ($conf['sortby'] == 'random') ? 'Random()' : $conf['sortby'];
        $blog_query = '(blog = '.
                      $this->sqlitehelper->quote_and_join($conf['blog'],
                                                          ' OR blog = ').')';
        $tag_query = $tag_table = "";
        if(count($conf['tags'])){
            $tag_query  = ' AND (tag = '.
                          $this->sqlitehelper->quote_and_join($conf['tags'],
                                                              ' OR tag = ').
                          ') AND A.pid = B.pid GROUP BY A.pid';
            $tag_table  = ', tags B';
        }

        // get the number of all matching entries
        $query = 'SELECT A.pid
                    FROM entries A'.$tag_table.'
                   WHERE '.$blog_query.$tag_query;
        $resid = $this->sqlitehelper->query($query);
        if (!$resid) return;
        $count = sqlite_num_rows($resid);
        if($count <= $conf['limit']) return '';

        // we now prepare an array of pages to show
        $pages = array();

        // calculate page boundaries
        $max = ceil($count/$conf['limit']);
        $cur = floor($conf['offset']/$conf['limit'])+1;

        $pages[] = 1;     // first page always
        $pages[] = $max;  // last page always
        $pages[] = $cur;  // current always

        if($max > 1){                // if enough pages
            $pages[] = 2;            // second and ..
            $pages[] = $max-1;       // one before last
        }

        // three around current
        if($cur-1 > 0) $pages[] = $cur-1;
        if($cur-2 > 0) $pages[] = $cur-2;
        if($cur-3 > 0) $pages[] = $cur-3;
        if($cur+1 < $max) $pages[] = $cur+1;
        if($cur+2 < $max) $pages[] = $cur+2;
        if($cur+3 < $max) $pages[] = $cur+3;

        sort($pages);
        $pages = array_unique($pages);

        // we're done - build the output
        $out = '';
        $out .= '<div class="blogtng_pagination">';
        if($cur > 1){
            $out .= '<a href="'.wl($conf['target'],
                                   array('btng[pagination][start]'=>$conf['limit']*($cur-2),
                                         'btng[post][tags]'=>join(',',$conf['tags']))).
                             '" class="prev">'.$this->getLang('prev').'</a> ';
        }
        $out .= '<span class="blogtng_pages">';
        $last = 0;
        foreach($pages as $page){
            if($page - $last > 1){
                $out .= ' <span class="sep">...</span> ';
            }
            if($page == $cur){
                $out .= '<span class="cur">'.$page.'</span> ';
            }else{
                $out .= '<a href="'.wl($conf['target'],
                                    array('btng[pagination][start]'=>$conf['limit']*($page-1),
                                          'btng[post][tags]'=>join(',',$conf['tags']))).
                                 '">'.$page.'</a> ';
            }
            $last = $page;
        }
        $out .= '</span>';
        if($cur < $max){
            $out .= '<a href="'.wl($conf['target'],
                                   array('btng[pagination][start]'=>$conf['limit']*($cur),
                                         'btng[post][tags]'=>join(',',$conf['tags']))).
                             '" class="next">'.$this->getLang('next').'</a> ';
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Displays a list of related blog entries
     */
    function xhtml_related($conf){
        ob_start();
        $this->tpl_related($conf['limit'],$conf['blog'],$conf['page'],$conf['tags']);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Displays a form to create new entries
     */
    function xhtml_newform($conf){
        global $ID;

        // allowed to create?
        if(!$this->toolshelper) {
            $this->toolshelper =& plugin_load('helper', 'blogtng_tools');
        }
        $new = $this->toolshelper->mkpostid($conf['format'],'dummy');
        if(auth_quickaclcheck($new) < AUTH_CREATE) return '';

        $form = new Doku_Form($ID, wl($ID,array('do'=>'btngnew'),false,'&'));
        if ($conf['title']) {
            $form->addElement(form_makeOpenTag('h3'));
            $form->addElement(hsc($conf['title']));
            $form->addElement(form_makeCloseTag('h3'));
        }
        if (isset($conf['select'])) {
            $form->addElement(form_makeMenuField('btng[new][title]', array_filter(preg_split('/\s*,\s*/', $conf['select'])), '', $this->getLang('title'), 'btng__nt', 'edit'));
        } else {
            $form->addElement(form_makeTextField('btng[new][title]', '', $this->getLang('title'), 'btng__nt', 'edit'));
        }
        $form->addElement(form_makeButton('submit', null, $this->getLang('create')));
        $form->addHidden('btng[new][format]', hsc($conf['format']));
        $form->addHidden('btng[post][blog]', hsc($conf['blog'][0]));

        return '<div class="blogtng_newform">' . $form->getForm() . '</div>';
    }

    //~~ template methods

    function tpl_content($name, $type) {
        $whitelist = array('list', 'entry', 'feed');
        if(!in_array($type, $whitelist)) return false;

        $tpl = helper_plugin_blogtng_tools::getTplFile($name, $type);
        if($tpl !== false) {
            $entry = $this;
            include($tpl);
        }
    }

    /**
     * Print the whole entry, reformat it or cut it when needed
     *
     * @param bool   $included   - set true if you want content to be reformated
     * @param string $readmore   - where to cut the entry valid: 'syntax', FIXME
     * @param bool   $inc_level  - FIXME
     * @param bool   $skipheader - Remove the first header
     * @return bool false if a recursion was detected and the entry could not be printed, true otherwise
     */
    function tpl_entry($included=true, $readmore='syntax',
                       $inc_level=true, $skipheader=false) {
        $content = $this->get_entrycontent($readmore, $inc_level, $skipheader);

        if ($included) {
            $content = $this->_convert_footnotes($content);
            $content .= $this->_edit_button();
        } else {
            $content = tpl_toc(true).$content;
        }

        echo html_secedit($content, !$included);
        return true;
    }

    function tpl_link($anchor=''){
        echo wl($this->entry['page']).(!empty($anchor) ? '#'.$anchor : '');
    }

    function tpl_permalink($str) {
        echo '<a href="' . wl ($this->entry['page']) . '" title="' . hsc($this->entry['title']) . '">' . $str . '</a>';
    }

    function tpl_abstract($len=0) {
        $this->_load_abstract();
        if($len){
            $abstract = utf8_substr($this->entry['abstract'], 0, $len).'…';
        }else{
            $abstract = $this->entry['abstract'];
        }
        echo hsc($abstract);
    }

    function tpl_title() {
        print hsc($this->entry['title']);
    }

    function tpl_created($format='') {
        if(!$this->entry['created']) return; // uh oh, something went wrong
        print dformat($this->entry['created'],$format);
    }

    function tpl_lastmodified($format='') {
        if(!$this->entry['lastmod']) return; // uh oh, something went wrong
        print dformat($this->entry['lastmod'], $format);
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
    function tpl_comments($name,$types=null) {
        if ($this->entry['commentstatus'] == 'disabled') return;
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        }
        $this->commenthelper->load($this->entry['pid']);
        $this->commenthelper->tpl_comments($name,$types);
    }

    /**
     * Print comment count
     *
     * Wrapper around commenthelper->tpl_commentcount()
     */
    function tpl_commentcount($fmt_zero_comments='', $fmt_one_comment='', $fmt_comments='',$types=null) {
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        }
        $this->commenthelper->load($this->entry['pid']);
        $this->commenthelper->tpl_count($fmt_zero_comments, $fmt_one_comment, $fmt_comments);
    }


    /**
     * Print a list of related posts
     *
     * Can be called statically. Also exported as syntax <blog related>
     *
     * @param int    $num    - maximum number of links
     * @param array  $blogs  - blogs to search
     * @param string $id     - reference page (false for current)
     * @param array  $tags   - additional tags to consider
     */
    function tpl_related($num=5,$blogs=array('default'),$id=false,$tags=array()){
        global $INFO;
        if($id === false) $id = $INFO['id']; //sidebar safe

        $pid = md5(cleanID($id));

        $query = "SELECT tag
                    FROM tags
                   WHERE pid = '$pid'";
        $res = $this->sqlitehelper->query($query);
        $res = $this->sqlitehelper->res2arr($res);
        foreach($res as $row){
            $tags[] = $row['tag'];
        }
        $tags = array_unique($tags);
        $tags = array_filter($tags);
        if(!count($tags)) return; // no tags for comparison

        $tags  = $this->sqlitehelper->quote_and_join($tags,',');
        $blog_query = '(A.blog = '.
                       $this->sqlitehelper->quote_and_join($blogs,
                                                           ' OR A.blog = ').')';

        $query = "SELECT page, title, COUNT(B.pid) AS cnt
                    FROM entries A, tags B
                   WHERE $blog_query
                     AND A.pid != '$pid'
                     AND A.pid = B.pid
                     AND B.tag IN ($tags)
                GROUP BY B.pid HAVING cnt > 0
                ORDER BY cnt DESC, created DESC
                   LIMIT ".(int) $num;
        $res = $this->sqlitehelper->query($query);
        if(!sqlite_num_rows($res)) return; // no results found
        $res = $this->sqlitehelper->res2arr($res);

        // now do the output
        echo '<ul class="related">';
        foreach($res as $row){
            echo '<li class="level1"><div class="li">';
            echo '<a href="'.wl($row['page']).'" class="wikilink1">'.hsc($row['title']).'</a>';
            echo '</div></li>';
        }
        echo '</ul>';
    }

    /**
     * Print comment form
     *
     * Wrapper around commenthelper->tpl_form()
     */
    function tpl_commentform() {
        if ($this->entry['commentstatus'] == 'closed' || $this->entry['commentstatus'] == 'disabled') return;
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        }
        $this->commenthelper->tpl_form($this->entry['page'], $this->entry['pid']);
    }

    function tpl_linkbacks() {}

    /**
     * Print a list of tags associated with the entry
     *
     * @param string $target - tag links will point to this page, tag is passed as parameter
     */
    function tpl_tags($target) {
        if (!$this->taghelper) {
            $this->taghelper =& plugin_load('helper', 'blogtng_tags');
        }
        $this->taghelper->load($this->entry['pid']);
        $this->taghelper->tpl_tags($target);
    }

    function tpl_tagstring($target, $separator=', ') {
        if (!$this->taghelper) {
            $this->taghelper =& plugin_load('helper', 'blogtng_tags');
        }
        $this->taghelper->load($this->entry['pid']);
        $this->taghelper->tpl_tagstring($target, $separator);
    }

    /**
     * Renders the link to the previous blog post using the given template.
     *
     * @param $tpl    string a template specifing the link text. May contain placeholders
     *                       for title, author and creation date of post
     * @param $id     string page id of blog post for which to generate the adjacent link
     * @param $return bool   whether to return the link or print it, defaults to print
     * @param bool/string if there is no such link, false. otherwise, if $return is true,
     *                    a string containing the generated HTML link, otherwise true.
     */
    function tpl_previouslink($tpl, $id=false, $return=false) {
        $out =  $this->_navi_link($tpl, 'prev', $id);
        if ($return) {
            return $out;
        } else if ($out !== false) {
            echo $out;
            return true;
        }
        return false;
    }

    /**
     * Renders the link to the next blog post using the given template.
     *
     * @param $tpl    string a template specifing the link text. May contain placeholders
     *                       for title, author and creation date of post
     * @param $id     string page id of blog post for which to generate the adjacent link
     * @param $return bool   whether to return the link or print it, defaults to print
     * @param bool/string if there is no such link, false. otherwise, if $return is true,
     *                    a string containing the generated HTML link, otherwise true.
     */
    function tpl_nextlink($tpl, $id=false, $return=false) {
        $out =  $this->_navi_link($tpl, 'next', $id);
        if ($return) {
            return $out;
        } else if ($out !== false) {
            echo $out;
            return true;
        }
        return false;
    }

    //~~ utility methods

    function get_blogs() {
        $pattern = DOKU_PLUGIN . 'blogtng/tpl/*{_,/}entry.php';
        $files = glob($pattern, GLOB_BRACE);
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

    function get_posts($conf) {
        $sortkey = ($conf['sortby'] == 'random') ? 'Random()' : $conf['sortby'];
        $blog_query = '(blog = '.
                      $this->sqlitehelper->quote_and_join($conf['blog'],
                                                          ' OR blog = ').')';
        $tag_query = $tag_table = "";
        if(count($conf['tags'])){
            $tag_query  = ' AND (tag = '.
                          $this->sqlitehelper->quote_and_join($conf['tags'],
                                                              ' OR tag = ').') AND A.pid = B.pid';
            $tag_table  = ', tags B';
        }

        $query = 'SELECT A.pid as pid, page, title, blog, image, created,
                         lastmod, login, author, mail
                    FROM entries A'.$tag_table.'
                   WHERE '.$blog_query.$tag_query.'
                GROUP BY A.pid
                ORDER BY '.$sortkey.' '.$conf['sortorder'].
                 ' LIMIT '.$conf['limit'].
                ' OFFSET '.$conf['offset'];

        $resid = $this->sqlitehelper->query($query);
        return $this->sqlitehelper->res2arr($resid);
    }

    /**
     * FIXME
     * @param $readmore
     * @param $inc_level
     * @param $skipheader
     * @return unknown_type
     */
    function get_entrycontent($readmore='syntax', $inc_level=true, $skipheader=false) {
        static $recursion = array();

        $id = $this->entry['page'];

        if(in_array($id, $recursion)){
            msg('blogtng: preventing infinite loop',-1);
            return false; // avoid infinite loops
        }

        $recursion[] = $id;

        // FIXME do some caching here!
        global $ID, $TOC, $conf;
        $info = array();

        $backupID = $ID;
        $ID = $id; // p_cached_instructions doesn't change $ID, so we need to do it or plugins like the discussion plugin might store information for the wrong page
        $ins = p_cached_instructions(wikiFN($id));
        $ID = $backupID; // restore the original $ID as otherwise _convert_instructions won't do anything
        $this->_convert_instructions($ins, $inc_level, $readmore, $skipheader);
        $ID = $id;

        $handleTOC = ($this->renderer !== null); // the call to p_render below might set the renderer

        if ($handleTOC){
            $renderer =& $this->renderer; // save the renderer before p_render changes it
            $backupTOC = $TOC; // the renderer overwrites the global $TOC
            $backupTocminheads = $conf['tocminheads'];
            $conf['tocminheads'] = 1; // let the renderer always generate a toc
        }

        $content = p_render('xhtml', $ins, $info);

        if ($handleTOC){
            if ($TOC && $backupTOC !== $TOC && $info['toc']){
                $renderer->toc = array_merge($renderer->toc, $TOC);
                $TOC = null; // Reset the global toc as it is included in the renderer now
                             // and if the renderer decides to not to output it the 
                             // global one should be empty
            }
            $conf['tocminheads'] = $backupTocminheads;
            $this->renderer =& $renderer;
        }

        $ID = $backupID;

        array_pop($recursion);
        return $content;
    }

    function is_valid_pid($pid) {
        return (preg_match('/^[0-9a-f]{32}$/', trim($pid)));
    }

    function has_tags() {
        if (!$this->taghelper) {
            $this->taghelper =& plugin_load('helper', 'blogtng_tags');
        }
        return ($this->taghelper->count($this->entry['pid']) > 0);
    }

    /**
     * Gets the adjacent (previous and next) links of a blog entry.
     *
     * @param $id string page id of the entry for which to get said links
     * @param array 2d assoziative array containing page id, title, author and creation date
     *              for both prev and next link
     */
    function getAdjacentLinks($id = false) {
        global $INFO;
        if($id === false) $id = $INFO['id']; //sidebar safe
        $pid = md5(cleanID($id));

        $related = array();
        foreach (array('prev', 'next') as $type) {
            $query = 'SELECT A.page AS page, A.title AS title,
                             A.author AS author, A.created AS created
                        FROM entries A, entries B
                       WHERE B.pid = ?
                         AND A.pid != B.pid
                         AND A.created ' . (($type == 'prev') ? '<' : '>') . ' B.created
                         AND A.blog = B.blog
                    ORDER BY A.created ' . (($type == 'prev') ? 'DESC' : 'ASC') . '
                       LIMIT 1';
            $res = $this->sqlitehelper->query($query, $pid);
            if (sqlite_num_rows($res) > 0) {
                $row = $this->sqlitehelper->res2row($res, 0);
                $related[$type] = $row;
            }
        }
        return $related;
    }

    /**
     * Returns a reference to the comment helper plugin preloaded with
     * the current entry
     */
    function &getCommentHelper(){
        if(!$this->commenthelper) {
            $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
            $this->commenthelper->load($this->entry['pid']);
        }
        return $this->commenthelper;
    }

    /**
     * Returns a reference to the tag helper plugin preloaded with
     * the current entry
     */
    function &getTagHelper(){
        if (!$this->taghelper) {
            $this->taghelper =& plugin_load('helper', 'blogtng_tags');
            $this->taghelper->load($this->entry['pid']);
        }
        return $this->taghelper;
    }



    //~~ private methods

    function _load_abstract(){
        if(isset($this->entry['abstract'])) return;
        $id = $this->entry['page'];

        $this->entry['abstract'] = p_get_metadata($id,'description abstract',true);
    }

    function _convert_instructions(&$ins, $inc_level, $readmore, $skipheader) {
        global $ID;

        $id = $this->entry['page'];
        if (!page_exists($id)) return false;

        // check if included page is in same namespace
        $ns = getNS($id);
        $convert = (getNS($ID) == $ns) ? false : true;

        $first_header = true;
        $open_sections = 0;
        $n = count($ins);
        for ($i = 0; $i < $n; $i++) {
            $current = $ins[$i][0];
            if ($convert && (substr($current, 0, 8) == 'internal')) {
                // convert internal links and media from relative to absolute
                $ins[$i][1][0] = $this->_convert_internal_link($ins[$i][1][0], $ns);
            } elseif ($current == 'header') {
                // convert header levels and convert first header to permalink
                $text = $ins[$i][1][0];
                $level = $ins[$i][1][1];

                // change first header to permalink
                if ($first_header) {
                    if($skipheader){
                        unset($ins[$i]);
                    }else{
                        $ins[$i] = array('plugin',
                            array(
                                'blogtng_header',
                                array(
                                    $text,
                                    $level
                                ),
                            ),
                            $ins[$i][1][2]
                        );
                    }
                }
                $first_header = false;

                // increase level of header
                if ($inc_level) {
                    $level = $level + 1;
                    if ($level > 5) $level = 5;
                    if (is_array($ins[$i][1][1])) {
                        // permalink header
                        $ins[$i][1][1][1] = $level;
                    } else {
                        // normal header
                        $ins[$i][1][1] = $level;
                    }
                }
            } elseif ($current == 'section_open') {
                // the same for sections
                if ($inc_level) $level = $ins[$i][1][0] + 1;
                if ($level > 5) $level = 5;
                $ins[$i][1][0] = $level;
                $open_sections++;
            } elseif ($current == 'section_close') {
                $open_sections--;
            } elseif (($current == 'plugin') && ($ins[$i][1][0] == 'blogtng_readmore') && $readmore) {
                // cut off the instructions here
                $this->_read_more($ins, $i, $open_sections, $inc_level);
                $open_sections = 0;
                break;
            }
        }
        $this->_finish_convert($ins, $open_sections);
        return true;
    }

    /**
     * Convert relative internal links and media
     *
     * @param    integer $i: counter for current instruction
     * @param    string  $ns: namespace of included page
     * @return   string  $link: converted, now absolute link
     */
    function _convert_internal_link($link, $ns) {
        if ($link{0} == '.') {
            // relative subnamespace
            if ($link{1} == '.') {
                // parent namespace
                return getNS($ns).':'.substr($link, 2);
            } else {
                // current namespace
                return $ns.':'.substr($link, 1);
            }
        } elseif (strpos($link, ':') === false) {
            // relative link
            return $ns.':'.$link;
        } elseif ($link{0} == '#') {
            // anchor
            return $this->entry['page'].$link;
        } else {
            // absolute link - don't change
            return $link;
        }
    }

    function _read_more(&$ins, $i, $open_sections, $inc_level) {
        $append_link = (is_array($ins[$i+1]) && $ins[$i+1][0] != 'document_end');
        $ins = array_slice($ins, 0, $i);
        if ($append_link) {
            $last = $ins[$i-1];
            for ($i = 0; $i < $open_sections; $i++) {
                $ins[] = array('section_close', array(), $last[2]);
            }
            $ins[] = array('section_open', array(($inc_level ? 2 : 1)), $last[2]);
            $ins[] = array('p_open', array(), $last[2]);
            $ins[] = array('internallink',array($this->entry['page'].'#readmore_'.str_replace(':', '_', $this->entry['page']), $this->getLang('readmore')),$last[2]);
            $ins[] = array('p_close', array(), $last[2]);
            $ins[] = array('section_close', array(), $last[2]);
        }
    }

    /**
     * Adds 'document_start' and 'document_end' instructions if not already there
     */
    function _finish_convert(&$ins, $open_sections) {
        if ($ins[0][0] != 'document_start')
            @array_unshift($ins, array('document_start', array(), 0));
        $c = array_pop(array_keys($ins)); // we can't use count here, instructions are not even indexed
        if ($ins[$c][0] != 'document_end')
            $ins[] = array('document_end', array(), 0);
    }

    function _convert_footnotes($html) {
        $id = str_replace(':', '_', $this->entry['page']);
        $replace = array(
            '!<a href="#fn__(\d+)" name="fnt__(\d+)" id="fnt__(\d+)" class="fn_top">!' =>
                '<a href="#fn__'.$id.'__\1" name="fnt__'.$id.'__\2" id="fnt__'.$id.'__\3" class="fn_top">',
            '!<a href="#fnt__(\d+)" id="fn__(\d+)" name="fn__(\d+)" class="fn_bot">!' =>
                '<a href="#fnt__'.$id.'__\1" name="fn__'.$id.'__\2" id="fn__'.$id.'__\3" class="fn_bot">',
        );
        $html = preg_replace(array_keys($replace), array_values($replace), $html);
        return $html;
    }

    /**
     * Display an edit button for the included page
     */
    function _edit_button() {
        global $ID;
        $id = $this->entry['page'];
        $perm = auth_quickaclcheck($id);

        if (page_exists($id)) {
            if (($perm >= AUTH_EDIT) && (is_writable(wikiFN($id)))) {
                $action = 'edit';
            } else {
                return '';
            }
        } elseif ($perm >= AUTH_CREATE) {
            $action = 'create';
        }

        $params = array('do' => 'edit');
        $params['redirect_id'] = $ID;
        return '<div class="secedit">'.DOKU_LF.DOKU_TAB.
            html_btn($action, $id, '', $params, 'post').DOKU_LF.
            '</div>'.DOKU_LF;
    }

    /**
     * Generates the HTML output of the link to the previous or to the next blog
     * entry in respect to the given page id using the specified template.
     *
     * @param $tpl string  a template specifing the link text. May contain placeholders
     *                     for title, author and creation date of post
     * @param $type string type of link to generate, may be 'prev' or 'next'
     * @param $id   string page id of blog post for which to generate the adjacent link
     * @return string a string containing the prepared HTML anchor tag, or false if there
     *                is no fitting post to link to
     */
    function _navi_link($tpl, $type, $id = false) {
        $related = $this->getAdjacentLinks($id);
        if (isset($related[$type])) {
            $replace = array(
                '@TITLE@' => $related[$type]['title'],
                '@AUTHOR@' => $related[$type]['author'],
                '@DATE@' => dformat($related[$type]['created']),
            );
            $out =  '<a href="' . wl($related[$type]['page'], '') . '" class="wikilink1" rel="'.$type.'">' . str_replace(array_keys($replace), array_values($replace), $tpl) . '</a>';
            return $out;
        }
        return false;
    }

}
// vim:ts=4:sw=4:et:
