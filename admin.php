<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_blogtng extends DokuWiki_Admin_Plugin {

    var $commenthelper = null;
    var $entryhelper   = null;
    var $sqlitehelper  = null;
    var $taghelper     = null;

    function getMenuSort() { return 200; }
    function forAdminOnly() { return false; }

    function admin_plugin_blogtng() {
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        $this->entryhelper   =& plugin_load('helper', 'blogtng_entry');
        $this->sqlitehelper  =& plugin_load('helper', 'blogtng_sqlite');
        $this->taghelper     =& plugin_load('helper', 'blogtng_tags');
    }

    /**
     * Handles all actions of the admin component
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function handle() {
        global $lang;

        if (!isset($_REQUEST['btng']['admin'])) { $admin = null; }
        else { $admin = (is_array($_REQUEST['btng']['admin'])) ? key($_REQUEST['btng']['admin']) : $_REQUEST['btng']['admin']; }

        // handle actions
        switch($admin) {

            case 'comment_save':
                // FIXME error handling?
                $comment = $_REQUEST['btng']['comment'];
                $this->commenthelper->save($comment);
                msg($this->getLang('msg_comment_save'), 1);
                break;

            case 'comment_delete':
                // FIXME error handling
                $comment = $_REQUEST['btng']['comment'];
                $this->commenthelper->delete($comment['cid']);
                msg($this->getLang('msg_comment_delete'), 1);
                break;

            case 'comment_batch_edit':
                $batch = $_REQUEST['btng']['admin']['comment_batch_edit'];
                $cids  = $_REQUEST['btng']['comments']['cids'];
                if($cids) {
                    foreach($cids as $cid) {
                        switch($batch) {
                            // FIXME messages
                            case 'delete':
                                $this->commenthelper->delete($cid);
                                msg($this->getLang('msg_comment_delete', 1));
                                break;
                            case 'status_hidden':
                                $this->commenthelper->moderate($cid, 'hidden');
                                msg($this->getLang('msg_comment_status_change'), 1);
                                break;
                            case 'status_visible':
                                $this->commenthelper->moderate($cid, 'visible');
                                msg($this->getLang('msg_comment_status_change'), 1);
                                break;
                        }
                    }
                }
                break;

            case 'entry_set_blog':
                // FIXME errors?
                $pid = $_REQUEST['btng']['entry']['pid'];
                $blog = $_REQUEST['btng']['entry']['blog'];
                if($pid) {
                    $blogs = $this->entryhelper->get_blogs();
                    if(in_array($blog, $blogs)) {
                        $this->entryhelper->load_by_pid($pid);
                        $this->entryhelper->entry['blog'] = $blog;
                        $this->entryhelper->save();
                    }
                }
                msg($this->getLang('msg_entry_blog_change'), 1);
                break;

            case 'entry_set_commentstatus':
                $pid = $_REQUEST['btng']['entry']['pid'];
                $status = $_REQUEST['btng']['entry']['commentstatus'];
                if($pid) {
                    $blogs = $this->entryhelper->get_blogs();
                    if(in_array($status, array('disabled', 'enabled', 'closed'))) {
                        $this->entryhelper->load_by_pid($pid);
                        $this->entryhelper->entry['commentstatus'] = $status;
                        $this->entryhelper->save();
                    }
                }
                msg($this->getLang('msg_comment_status_change'), 1);
                break;

            default:
                // do nothing - show dashboard
                break;
        }
    }

    /**
     * Handles the XHTML output of the admin component
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function html() {
        global $conf;
        global $lang;
        global $ID;

        ptln('<h1>'.$this->getLang('menu').'</h1>');

        $admin = (is_array($_REQUEST['btng']['admin'])) ? key($_REQUEST['btng']['admin']) : $_REQUEST['btng']['admin'];

        ptln('<div id="blogtng__admin">');

        // display link back to dashboard
        if($admin) {
            ptln('<div class="level1">');
            ptln('<p><a href="' . wl($ID, array('do'=>'admin', 'page'=>'blogtng')) . '" title="' . $this->getLang('dashboard') . '">&larr; ' . $this->getLang('dashboard') . '</a></p>');
            ptln('</div>');

        }

        // display search form
        $this->xhtml_search_form();

        switch($admin) {
            case 'search':

                ptln('<h2>' . $this->getLang('searchresults') . '</h2>');
                $query = $_REQUEST['btng']['query'];

                switch($query['filter']) {
                    case 'entry_title':
                    case 'entry_author':
                        $this->xhtml_search_entries($query);
                        break;
                    case 'comment':
                    case 'comment_ip':
                        $this->xhtml_search_comments($query);
                        break;
                    case 'tags':
                        $this->xhtml_search_tags($query);
                        break;
                }

                break;

            case 'comment_edit':
            case 'comment_preview':
                if($admin == 'comment_edit') {
                    $obj = $this->commenthelper->comment_by_cid($_REQUEST['btng']['comment']['cid']);
                    $comment = $obj->data;
                    if($comment) {
                        $this->xhtml_comment_edit_form($comment);
                    }
                }
                if($admin == 'comment_preview') {
                    $this->xhtml_comment_edit_form($_REQUEST['btng']['comment']);
                    $this->xhtml_comment_preview($_REQUEST['btng']['comment']);
                }
                break;

            default:
                // print latest entries/commits
                printf('<h2>'.$this->getLang('comment_latest').'</h2>', 5);
                $this->xhtml_comment_latest();
                printf('<h2>'.$this->getLang('entry_latest').'</h2>', 5);
                $this->xhtml_entry_latest();
                break;
        }

        ptln('</div>');
    }

    /**
     * Displays a list of entries for a given matching title search
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_search_entries($data) {
        $query = 'SELECT * FROM entries ';
        if($data['blog']) {
            $query .= 'WHERE blog = "' . $data['blog'] . '" ';
        } else {
            $query .= 'WHERE blog != ""';
        }
        if($data['filter'] == 'entry_title') {
            $query .= 'AND ( title LIKE "%'.$data['string'].'%" ) ';
        }
        if($data['filter'] == 'entry_author') {
            $query .= 'AND ( author LIKE "%'.$data['string'].'%" ) ';
        }
        $query .= 'ORDER BY created DESC ';

        $resid = $this->sqlitehelper->query($query);
        if($resid) {
            $this->xhtml_search_result($resid, $data, 'xhtml_entry_list');
        }
    }

    /**
     * Displays a list of comments for a given search term
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_search_comments($data) {
        $query = 'SELECT DISTINCT cid, B.pid as pid, ip, source, name, B.mail as mail, web, avatar, B.created as created, text, status
                  FROM comments B LEFT JOIN entries A ON B.pid = A.pid ';
        if($data['blog']) {
            $query .= 'WHERE blog = "' . $data['blog'] . '" ';
        } else {
            $query .= 'WHERE blog != ""';
        }

        // check for search query
        if(isset($data['string']) && $data['filter'] == 'comment') {
            $query .= 'AND ( B.text LIKE "%'.$data['string'].'%" ) ';
        }

        if(isset($data['string']) && $data['filter'] == 'comment_ip') {
            $query .= 'AND ( B.ip LIKE "%'.$data['string'].'%" ) ';
        }

        // if pid is given limit to give page
        if(isset($data['pid'])) {
            $query .= 'AND ( B.pid = "' . $data['pid'] . '" ) ';
        }

        $query .= 'ORDER BY B.created DESC';

        $resid = $this->sqlitehelper->query($query);
        if($resid) {
            $this->xhtml_search_result($resid, $data, 'xhtml_comment_list');
        }
    }

    /**
     * Query the tag database for a give search string
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_search_tags($data) {
        $query = 'SELECT DISTINCT A.pid as pid, page, title, blog, image, created, lastmod, author, login, mail
                  FROM entries A LEFT JOIN tags B ON A.pid = B.pid ';
        if($data['blog']) {
            $query .= 'WHERE blog = "' . $data['blog'] . '" ';
        } else {
            $query .= 'WHERE blog != ""';
        }
        $query .= 'AND ( B.tag LIKE "%'.$data['string'].'%" ) ';
        $query .= 'ORDER BY created DESC ';

        $resid = $this->sqlitehelper->query($query);
        if($resid) {
            $this->xhtml_search_result($resid, $data, 'xhtml_entry_list');
        }
    }

    function xhtml_search_result($resid, $query, $callback) {
        global $lang;
        if(!$resid) return;

        // FIXME selectable?
        $limit = 20;

        $count = sqlite_num_rows($resid);
        $start = (isset($_REQUEST['btng']['query']['start'])) ? ($_REQUEST['btng']['query']['start'])  : 0;
        $end   = ($count >= ($start + $limit)) ? ($start + $limit) : $count;
        $cur   = ($start / $limit) + 1;

        $items = array();
        for($i = $start; $i < $end; $i++) {
            $items[] = $this->sqlitehelper->res2row($resid, $i);
        }

        if($items) {
            ptln('<div class="level2"><p><strong>' . $this->getLang('numhits') . ':</strong> ' . $count .'</p></div>');
            // show pagination only when enough items
            if($count > $limit) {
                $this->xhtml_pagination($query, $cur, $start, $count, $limit);
            }
            call_user_func(array($this, $callback), $items);
        } else {
            ptln('<div class="level2">');
            ptln($lang['nothingfound']);
            ptln('</div>');
        }

        // show pagination only when enough items
        if($count > $limit) {
            $this->xhtml_pagination($query, $cur, $start, $count, $limit);
        }
    }

    /**
     * Diplays that pagination links of a query
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_pagination($query, $cur, $start, $count, $limit) {
        global $ID;
        $max = ceil($count / $limit);

        $pages[] = 1;     // first always
        $pages[] = $max;  // last page always
        $pages[] = $cur;  // current always

        if($max > 1){            // if enough pages
            $pages[] = 2;        // second and ..
            $pages[] = $max-1;   // one before last
        }

        // three around current
        if($cur-1 > 0) $pages[] = $cur-1;
        if($cur-2 > 0) $pages[] = $cur-2;
        if($cur-3 > 0) $pages[] = $cur-3;
        if($cur+1 < $max) $pages[] = $cur+1;
        if($cur+2 < $max) $pages[] = $cur+2;
        if($cur+3 < $max) $pages[] = $cur+3;

        $pages = array_unique($pages);
        sort($pages);

        ptln('<div class="level2"><p>');

        if($cur > 1) {
            ptln('<a href="' . wl($ID, array('do'=>'admin',
                                             'page'=>'blogtng',
                                             'btng[admin]'=>'search',
                                             'btng[query][filter]'=>$query['filter'],
                                             'btng[query][blog]'=>$query['blog'],
                                             'btng[query][string]'=>$query['string'],
                                             'btng[query][start]'=>(($cur-2)*$limit))) . '" title="' . ($cur-1) . '">&laquo;</a>');
        }

        $last = 0;
        foreach($pages as $page) {
            if($page - $last > 1) {
                ptln('<span class="sep">...</span>');
            }
            if($page == $cur) {
                ptln('<span class="cur">' . $page . '</span>');
            } else {
                ptln('<a href="' . wl($ID, array('do'=>'admin',
                                                 'page'=>'blogtng',
                                                 'btng[admin]'=>'search',
                                                 'btng[query][filter]'=>$query['filter'],
                                                 'btng[query][blog]'=>$query['blog'],
                                                 'btng[query][string]'=>$query['string'],
                                                 'btng[query][start]'=>(($page-1)*$limit))) . '" title="' . $page . '">' . $page . '</a>');
            }
            $last = $page;
        }

        if($cur < $max) {
            ptln('<a href="' . wl($ID, array('do'=>'admin',
                                             'page'=>'blogtng',
                                             'btng[admin]'=>'search',
                                             'btng[query][filter]'=>$query['filter'],
                                             'btng[query][blog]'=>$query['blog'],
                                             'btng[query][string]'=>$query['string'],
                                             'btng[query][start]'=>($cur*$limit))) . '" title="' . ($cur+1) . '">&raquo;</a>');
        }

        ptln('</p></div>');
    }

    /**
     * Displays the latest blog entries
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_entry_latest() {
        $limit = 5;

        $query = 'SELECT *
                    FROM entries
                   WHERE blog != ""
                ORDER BY created DESC
                   LIMIT ' . $limit;

        $resid = $this->sqlitehelper->query($query);
        if(!$resid) return;
        $this->xhtml_search_result($resid, array(), 'xhtml_entry_list');
    }

    /**
     * Display the latest comments
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_comment_latest() {
        $limit = 5;

        $query = 'SELECT *
                    FROM comments
                ORDER BY created DESC
                   LIMIT ' . $limit;

        $resid = $this->sqlitehelper->query($query);
        if(!$resid) return;
        $this->xhtml_search_result($resid, array(), 'xhtml_comment_list');
    }

    /**
     * Displays a list of entries
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_entry_list($entries) {
        ptln('<div class="level2">');
        ptln('<table class="inline">');

        // FIXME language strings
        ptln('<th>' . $this->getLang('created') . '</th>');
        ptln('<th>' . $this->getLang('author') . '</th>');
        ptln('<th>' . $this->getLang('entry') . '</th>');
        ptln('<th>' . $this->getLang('blog') . '</th>');
        ptln('<th>' . $this->getLang('commentstatus') . '</th>');
        ptln('<th>' . $this->getLang('comments') . '</th>');
        ptln('<th>' . $this->getLang('tags') . '</th>');
        ptln('<th></th>');
        foreach($entries as $entry) {
            $this->xhtml_entry_item($entry);
        }
        ptln('</table>');
        ptln('</div>');
    }

    /**
     * Displays a single entry and related actions
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_entry_item($entry) {
        global $lang;
        global $conf;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        ptln('<td class="entry_created">' . dformat($entry['created']) . '</td>');
        ptln('<td class="entry_author">' . hsc($entry['author']) . '</td>');
        ptln('<td class="entry_title">' . html_wikilink($entry['page'], $entry['title']) . '</td>');

        ptln('<td class="entry_set_blog">' . $this->xhtml_entry_set_blog_form($entry) . '</th>');

        ptln('<td class="entry_set_commentstatus">' . $this->xhtml_entry_set_commentstatus_form($entry) . '</th>');

        $this->commenthelper->load($entry['pid']);

        // comments edit link
        ptln('<td class="entry_comments">');
        $count = $this->commenthelper->get_count(null, true);
        if($count > 0) {
            ptln('<a href="' . wl($ID, array('do'=>'admin',
                                                     'page'=>'blogtng',
                                                     'btng[admin]'=>'search',
                                                     'btng[query][filter]'=>'comment',
                                                     'btng[query][pid]'=>$entry['pid']))
                             . '" title="' . $this->getLang('comments') . '">' . $count . '</a>');
        } else {
            ptln($count);
        }
        ptln('</td>');

        // tags filter links
        ptln('<td class="entry_tags">');
        $this->taghelper->load($entry['pid']);
        $tags = $this->taghelper->tags;
        $count = count($tags);
        for($i=0;$i<$count;$i++) {
            $link = '<a href="' . wl($ID, array('do'=>'admin',
                                                     'page'=>'blogtng',
                                                     'btng[admin]'=>'search',
                                                     'btng[query][filter]'=>'tags',
                                                     'btng[query][string]'=>$tags[$i]))
                             . '" title="' . $tags[$i] . '">' . $tags[$i] . '</a>';
            if($i<($count-1)) $link .= ', ';
            ptln($link);
        }
        ptln('</td>');

        // edit links
        ptln('<td class="entry_edit">');
        ptln('<a href="' . wl($ID, array('id'=>$entry['page'],
                                                 'do'=>'edit'))
                         . '" class="blogtng_btn_edit" title="' . $lang['btn_secedit'] . '">' . $lang['btn_secedit'] . '</a>');
        ptln('</td>');

        ptln('</tr>');
    }

    /**
     * Displays a list of comments
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_comment_list($comments) {
        global $lang;
        global $ID;

        ptln('<div class="level2">');

        ptln('<form action="' . DOKU_SCRIPT . '" method="post" id="blogtng__comment_batch_edit_form">');
        ptln('<input type="hidden" name="page" value="blogtng" />');

        ptln('<table class="inline">');
        ptln('<th id="blogtng__admin_checkall_th"></th>');
        ptln('<th>' . $this->getLang('created') . '</th>');
        ptln('<th>' . $this->getLang('comment_ip') . '</th>');
        ptln('<th>' . $this->getLang('comment_name') . '</th>');
        ptln('<th>' . $this->getLang('comment_web') . '</th>');
        ptln('<th>' . $this->getLang('comment_status') . '</th>');
        ptln('<th>' . $this->getLang('comment_source') . '</th>');
        ptln('<th>' . $this->getLang('entry') . '</th>');
        ptln('<th>' . $this->getLang('comment_text') . '</th>');
        ptln('<th></th>');

        foreach($comments as $comment) {
            $this->xhtml_comment_item($comment);
        }

        ptln('</table>');
        ptln('<select name="btng[admin][comment_batch_edit]">');
        ptln('<option value="status_visible">Visible</option>');
        ptln('<option value="status_hidden">Hidden</option>');
        ptln('<option value="delete">'.$lang['btn_delete'].'</option>');
        ptln('</select>');
        ptln('<input type="submit" class="edit button" name="do[admin]" value="' . $lang['btn_update'] . '" />');
        ptln('</form>');

        ptln('</div>');
    }

    /**
     * Displays a single comment and related actions
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_comment_item($comment) {
        global $conf;
        global $lang;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        $cmt = new blogtng_comment();
        $cmt->init($comment);
        ptln('<td class="admin_checkbox"><input type="checkbox" class="comment_cid" name="btng[comments][cids][]" value="' . $comment['cid'] . '" /></td>');

        ptln('<td class="comment_created">' . dformat($comment['created']) . '</td>');
        ptln('<td class="comment_ip">' . hsc($comment['ip']) . '</td>');

        ptln('<td class="comment_name">');
        $avatar = $cmt->tpl_avatar(16,16,true);
        if($avatar) ptln('<img src="' . $avatar . '" alt="' . hsc($comment['name']) . '" class="avatar" /> ');
        if($comment['mail']){
            ptln('<a href="mailto:' . hsc($comment['mail']) . '" class="mail" title="' . hsc($comment['mail']) . '">' . hsc($comment['name']) . '</a>');
        }else{
            ptln(hsc($comment['name']));
        }
        ptln('</td>');

        if($comment['web']) {
            ptln('<td class="comment_web"><a href="' . hsc($comment['web']) . '" title="' . hsc($comment['web']) . '">' . hsc($comment['web']) . '</a></td>');
        } else {
            ptln('<td class="comment_web"></td>');
        }

        ptln('<td class="comment_status">' . hsc($comment['status']) . '</td>');
        ptln('<td class="comment_source">' . hsc($comment['source']) . '</td>');

        $this->entryhelper->load_by_pid($comment['pid']);
        ptln('<td class="comment_entry">' . html_wikilink($this->entryhelper->entry['page'], $this->entryhelper->entry['title']) . '</td>');

        ptln('<td class="comment_text">' . hsc($comment['text']) . '</td>');

        ptln('<td class="comment_edit"><a href="' . wl($ID, array('do'=>'admin',
                                                                          'page'=>'blogtng',
                                                                          'btng[comment][cid]'=>$comment['cid'],
                                                                          'btng[admin]'=>'comment_edit'))
                                                  . '" class="blogtng_btn_edit" title="' . $lang['btn_edit'] . '">' . $lang['btn_secedit'] . '</a></td>');

        ptln('</tr>');
    }

    function xhtml_comment_preview($data) {
        global $lang;
        // FIXME
        ptln('<div id="blogtng__comment_preview">');
        ptln(p_locale_xhtml('preview'));
        ptln('<br />');
        $comment = new blogtng_comment();
        $comment->init($data);
        $comment->output('default');
        ptln('</div>');
    }

    /**
     * Displays the form to set the blog a entry belongs to
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_entry_set_blog_form($entry) {
        global $lang;
        $blogs = $this->entryhelper->get_blogs();

        $form = new Doku_FOrm(array('id'=>'blogtng__entry_set_blog_form'));
        $form->addHidden('do', 'admin');
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[entry][pid]', $entry['pid']);
        $form->addElement(formSecurityToken());
        $form->addElement(form_makeListBoxField('btng[entry][blog]', $blogs, $entry['blog'], ''));
        $form->addElement('<input type="submit" name="btng[admin][entry_set_blog]" class="edit button" value="' . $lang['btn_update'] . '" />');

        ob_start();
        html_form('blotng__btn_entry_set_blog', $form);
        $form = ob_get_clean();
        return $form;
    }

    /**
     * Displays the form to set the comment status of a blog entry
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_entry_set_commentstatus_form($entry) {
        global $lang;
        $blogs = $this->entryhelper->get_blogs();

        $form = new Doku_FOrm(array('id'=>'blogtng__entry_set_commentstatus_form'));
        $form->addHidden('do', 'admin');
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[entry][pid]', $entry['pid']);
        $form->addElement(formSecurityToken());
        $form->addElement(form_makeListBoxField('btng[entry][commentstatus]', array('enabled', 'disabled', 'closed'), $entry['commentstatus'], ''));
        $form->addElement('<input type="submit" name="btng[admin][entry_set_commentstatus]" class="edit button" value="' . $lang['btn_update'] . '" />');

        ob_start();
        html_form('blotng__btn_entry_set_commentstatus', $form);
        $form = ob_get_clean();
        return $form;
    }

    /**
     * Displays the comment edit form
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_comment_edit_form($comment) {
        global $lang;

        ptln('<div class="level1">');
        $form = new Doku_Form(array('id'=>'blogtng__comment_edit_form'));
        $form->startFieldset($this->getLang('act_comment_edit'));
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[admin]', $action); //FIXME this var doesn't exist
        $form->addHidden('do', 'admin');
        $form->addHidden('btng[comment][cid]', $comment['cid']);
        $form->addHidden('btng[comment][pid]', $comment['pid']);
        $form->addHidden('btng[comment][created]', $comment['created']);
        $form->addElement(formSecurityToken());
        $form->addElement(form_makeListBoxField('btng[comment][status]', array('visible', 'hidden'), $comment['status'], $this->getLang('comment_status')));
        $form->addElement('<br />');
        $form->addElement(form_makeListBoxField('btng[comment][source]', array('comment', 'trackback', 'pingback'), $comment['source'], $this->getLang('comment_source')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('btng[comment][name]', $comment['name'], $this->getLang('comment_name')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('btng[comment][mail]', $comment['mail'], $this->getLang('comment_mail')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('btng[comment][web]', $comment['web'], $this->getLang('comment_web')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('btng[comment][avatar]', $comment['avatar'], $this->getLang('comment_avatar')));
        $form->addElement('<br />');
        $form->addElement('<textarea class="edit" name="btng[comment][text]" rows="10" cols="80">' . $comment['text'] . '</textarea>');
        $form->addElement('<input type="submit" name="btng[admin][comment_save]" class="edit button" value="' . $lang['btn_save'] . '" />');
        $form->addElement('<input type="submit" name="btng[admin][comment_preview]" class="edit button" value="' . $lang['btn_preview'] . '" />');
        $form->addElement('<input type="submit" name="btng[admin][comment_delete]" class="edit button" value="' . $lang['btn_delete'] . '" />');
        $form->endFieldset();
        html_form('blogtng__edit_comment', $form);
        ptln('</div>');
    }

    /**
     * Displays the search form
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_search_form() {
        global $lang;

        ptln('<div class="level1">');

        $blogs = $this->entryhelper->get_blogs();

        $form = new Doku_Form(array('id'=>'blogtng__search_form'));
        $form->startFieldset($lang['btn_search']);
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[admin]', 'search');
        $form->addElement(formSecurityToken());

        $form->addElement(form_makeListBoxField('btng[query][blog]', $blogs, $_REQUEST['btng']['query']['blog'], $this->getLang('blog')));
        $form->addElement(form_makeListBoxField('btng[query][filter]', array('entry_title', 'entry_author', 'comment', 'comment_ip', 'tags'), $_REQUEST['btng']['query']['filter'], $this->getLang('filter')));
        $form->addElement(form_makeTextField('btng[query][string]', $_REQUEST['btng']['query']['string'],''));

        $form->addElement(form_makeButton('submit', 'admin', $lang['btn_search']));
        $form->endFieldset();
        html_form('blogtng__search_form', $form);

        ptln('</div>');
    }

}
// vim:ts=4:sw=4:et:
