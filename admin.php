<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class admin_plugin_blogtng
 */
class admin_plugin_blogtng extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_blogtng_comments */
    protected $commenthelper = null;
    /** @var helper_plugin_blogtng_entry */
    protected $entryhelper   = null;
    /** @var helper_plugin_blogtng_sqlite */
    protected $sqlitehelper  = null;
    /** @var helper_plugin_blogtng_tags */
    protected $taghelper     = null;

    /**
     * Determine position in list in admin window
     * Lower values are sorted up
     *
     * @return int
     */
    public function getMenuSort() { return 200; }

    /**
     * Return true for access only by admins (config:superuser) or false if managers are allowed as well
     *
     * @return bool
     */
    public function forAdminOnly() { return false; }

    /**
     * Constructor
     */
    public function __construct() {
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
        $this->entryhelper   = plugin_load('helper', 'blogtng_entry');
        $this->sqlitehelper  = plugin_load('helper', 'blogtng_sqlite');
        $this->taghelper     = plugin_load('helper', 'blogtng_tags');
    }

    /**
     * Handles all actions of the admin component
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    public function handle() {
        if(!isset($_REQUEST['btng']['admin'])) {
            $admin = null;
        } else {
            $admin = (is_array($_REQUEST['btng']['admin'])) ? key($_REQUEST['btng']['admin']) : $_REQUEST['btng']['admin'];
        }
        //skip actions when no valid security token given
        $noSecTokenNeeded = array('search', 'comment_edit', 'comment_preview', null);
        if(!in_array($admin, $noSecTokenNeeded) && !checkSecurityToken()) {
            $admin = null;
        }

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
                                msg($this->getLang('msg_comment_delete'), 1);
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
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    public function html() {
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

        switch($admin) {
            case 'search':
                // display search form
                $this->xhtml_search_form();

                ptln('<h2>' . $this->getLang('searchresults') . '</h2>');

                $query = $_REQUEST['btng']['query'];
                $query['resultset'] = 'query';

                $this->xhtml_search_results($query);
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
                // display search form
                $this->xhtml_search_form();

                // print latest 'x' comments/entries
                $query = $_REQUEST['btng']['comment'];
                $query['resultset'] = 'comment';
                $this->xhtml_latest_items($query);

                $query = $_REQUEST['btng']['entry'];
                $query['resultset']  = 'entry';
                $this->xhtml_latest_items($query);
                break;
        }

        ptln('</div>');
    }

    /**
     * Displays a list of comments or entries for a given search term
     *
     * @param array $query url parameters for query
     */
    private function xhtml_search_results($query) {
        if(!$this->sqlitehelper->ready()) return;

        $db = $this->sqlitehelper->getDB();

        switch($query['filter']) {
            case 'entry_title':
            case 'entry_author':
                $select  = 'SELECT * ';
                $from    = 'FROM entries ';
                $orderby = 'ORDER BY created DESC ';
                $itemdisplaycallback = 'xhtml_entry_list';
                break;
            case 'comment':
            case 'comment_ip':
                $select = 'SELECT cid, comments.pid as pid, ip, source, name, comments.mail as mail, web, avatar, comments.created as created, text, status ';
                $from   = 'FROM comments LEFT JOIN entries ON comments.pid = entries.pid ';
                $orderby = 'ORDER BY comments.created DESC ';
                $itemdisplaycallback = 'xhtml_comment_list';
            break;
            case 'tags':
                $select = 'SELECT DISTINCT entries.pid as pid, page, title, blog, image, created, lastmod, author, login, mail ';
                $from   = 'FROM entries  LEFT JOIN tags ON entries.pid = tags.pid ';
                $orderby = 'ORDER BY created DESC ';
                $itemdisplaycallback = 'xhtml_entry_list';
                break;
            default:
                return;
        }
        $count  = 'SELECT COUNT(*) as count ';

        if(isset($query['blog']) && $query['blog']) {
            $where = 'WHERE blog = ' . $db->quote_string($query['blog']) . ' ';
        } else {
            $where = 'WHERE blog != "" ';
        }

        if(isset($query['string']) && $query['string'] != '') {
            switch($query['filter']) {
                case 'entry_title':
                    $where .= 'AND ( title LIKE \'%'.$db->escape_string($query['string']).'%\' ) ';
                    break;
                case 'entry_author':
                    $where .= 'AND ( author LIKE \'%'.$db->escape_string($query['string']).'%\' ) ';
                    break;
                case 'comment':
                    $where .= 'AND ( comments.text LIKE \'%'.$db->escape_string($query['string']).'%\' ) ';
                    break;
                case 'comment_ip':
                    $where .= 'AND ( comments.ip LIKE \'%'.$db->escape_string($query['string']).'%\' ) ';
                    break;
                case 'tags':
                    $where .= 'AND ( tags.tag LIKE \'%'.$db->escape_string($query['string']).'%\' ) ';
                    break;
            }
        }

        //comments: if pid is given limit to give page
        if(isset($query['pid']) && $query['pid'] != '') {
            $where .= 'AND ( comments.pid = ' . $db->quote_string($query['pid']) . ' ) ';
        }

        $sqlcount  = $count . $from . $where;
        $sqlselect = $select . $from . $where . $orderby;


        $sqlselect .= ' LIMIT '.$this->getLimitParam($query, 20);
        $offset = $this->getOffsetParam($query);
        if($offset > 0) {
            $sqlselect .= ' OFFSET '.$offset;
        }

        $res = $db->query($sqlcount);
        $count = $db->res2single($res);

        $resid = $db->query($sqlselect);
        if($resid) {
            $this->xhtml_show_paginated_result($resid, $query, $itemdisplaycallback, $count);
        }
    }

    /**
     * Display paginated results
     *
     * @param object $resid    Database resource object
     * @param array  $query    Query parameters
     * @param string $itemdisplaycallback called for each item, to display content of item
     * @param int    $count    Number of total items
     * @param int    $limit    Number of results to display per page (page size)
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_show_paginated_result($resid, $query, $itemdisplaycallback, $count, $limit = 20) {
        global $lang;
        if(!$resid) return;

        $offset = $this->getOffsetParam($query);
        $currentpage   =  floor($offset / $limit) + 1;

        $items = $this->sqlitehelper->getDB()->res2arr($resid);

        if($items) {
            ptln('<div class="level2"><p><strong>' . $this->getLang('numhits') . ':</strong> ' . $count .'</p></div>');

            // show pagination only when enough items
            if($count > $limit) {
                $this->xhtml_pagination($query, $currentpage, $count, $limit);
            }

            call_user_func(array($this, $itemdisplaycallback), $items, $query);

        } else {
            ptln('<div class="level2">');
            ptln($lang['nothingfound']);
            ptln('</div>');
        }

        // show pagination only when enough items
        if($count > $limit) {
            $this->xhtml_pagination($query, $currentpage, $count, $limit);
        }
    }

    /**
     * Diplays that pagination links of a query
     *
     * @param array  $query       Query parameters
     * @param int    $currentpage number of current page
     * @param int    $maximum     maximum number of items available
     * @param int    $limit       number of items per page
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    private function xhtml_pagination($query, $currentpage, $maximum, $limit) {
        $lastpage = (int) ceil($maximum / $limit);

        $pages[] = 1;             // first always
        $pages[] = $lastpage;     // last page always
        $pages[] = $currentpage;  // current page always

        if($lastpage > 1){            // if enough pages
            $pages[] = 2;             // second and ..
            $pages[] = $lastpage-1;   // one before last
        }

        // three around current
        if($currentpage-1 > 0) $pages[] = $currentpage-1;
        if($currentpage-2 > 0) $pages[] = $currentpage-2;
        if($currentpage-3 > 0) $pages[] = $currentpage-3;
        if($currentpage+1 < $lastpage) $pages[] = $currentpage+1;
        if($currentpage+2 < $lastpage) $pages[] = $currentpage+2;
        if($currentpage+3 < $lastpage) $pages[] = $currentpage+3;

        $pages = array_unique($pages);
        sort($pages);

        ptln('<div class="level2"><p>');

        if($currentpage > 1) {
            $this->xhtml_paginationurl($query, ($currentpage - 2) * $limit, $limit, '&laquo;', $currentpage - 1);
        }

        $last = 0;
        foreach($pages as $page) {
            if($page - $last > 1) {
                ptln('<span class="sep">...</span>');
            }
            if($page == $currentpage) {
                ptln('<span class="cur">' . $page . '</span>');
            } else {
                $this->xhtml_paginationurl($query, ($page - 1) * $limit, $limit, $page, $page);
            }
            $last = $page;
        }

        if($currentpage < $lastpage) {
            $this->xhtml_paginationurl($query, $currentpage * $limit, $limit, '&raquo;', $currentpage + 1);
        }

        ptln('</p></div>');
    }

    /**
     * Print a pagination link.
     * 
     * @param array   $query   Query parameters
     * @param int     $offset  number of previous items
     * @param int     $limit   number of items at this page
     * @param string  $text    text of url
     * @param string  $title   title of url
     * @internal param string $anchor url anchor
     */
    private function xhtml_paginationurl($query, $offset, $limit, $text, $title) {
        global $ID;
        list($params, $anchor) = $this->buildUrlParams($query, $offset, $limit);
        ptln("<a href='".wl($ID, $params).'#'.$anchor."' title='$title'>$text</a>");
    }

    /**
     * Build URL parameters.
     * 
     * @param array  $query   Query parameters
     * @param int    $offset  number of previous items
     * @param int    $limit   number of items at this page
     * @return array($params, $anchor)
     */
    private function buildUrlParams($query, $offset, $limit) {
        $params = array(
            'do' => 'admin',
            'page' => 'blogtng',
            'btng[' . $query['resultset'] . '][limit]' => $limit ,
            'btng[' . $query['resultset'] . '][offset]' => $offset
        );
        $anchor = $query['resultset'] . '_latest';

        if($query['resultset'] == 'query') {
            $params = $params + array(
                    'btng[admin]' => 'search',
                    'btng[query][filter]' => $query['filter'],
                    'btng[query][blog]'   => $query['blog'],
                    'btng[query][string]' => $query['string'],
                    'btng[query][pid]'    => $query['pid']
                );
            $anchor = '';
        }
        return array($params, $anchor);
    }

    /**
     * Display the latest comments or entries
     *
     * @param  $query  Query parameters
     * 
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_latest_items($query) {
        $resultset = $query['resultset'];

        printf("<h2 id='{$resultset}_latest'>".$this->getLang($resultset.'_latest').'</h2>', $this->getLimitParam($query));
        $this->xhtml_limit_form($query);

        if(!$this->sqlitehelper->ready()) return;

        $count = 'SELECT COUNT(pid) as count ';
        $select = 'SELECT * ';

        if($resultset == 'entry') {
            $from = 'FROM entries ';
            $where = 'WHERE blog != "" ';
            $itemdisplaycallback = 'xhtml_entry_list';
        } else {
            $from = 'FROM comments ';
            $where = '';
            $itemdisplaycallback = 'xhtml_comment_list';
        }

        $orderby = 'ORDER BY created DESC ';

        $sqlcount = $count . $from . $where;
        $sqlselect = $select . $from . $where . $orderby;

        $limit = $this->getLimitParam($query);
        $offset = $this->getOffsetParam($query);
        $sqlselect .= 'LIMIT ' . $limit;
        if($offset) {
            $sqlselect .= ' OFFSET ' . $offset;
        }

        $res = $this->sqlitehelper->getDB()->query($sqlcount);
        $count = $this->sqlitehelper->getDB()->res2single($res);

        $resid = $this->sqlitehelper->getDB()->query($sqlselect);
        if(!$resid) {
            return;
        }
        $this->xhtml_show_paginated_result($resid, $query, $itemdisplaycallback, $count, $limit);
    }

    /**
     * Displays a list of entries, as callback of xhtml_search_results()
     *
     * @param $entries Array of entries
     * @param $query   Query parameters
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    private function xhtml_entry_list($entries, $query) {
        ptln('<div class="level2">');
        ptln('<table class="inline">');

        ptln('<th>' . $this->getLang('created') . '</th>');
        ptln('<th>' . $this->getLang('author') . '</th>');
        ptln('<th>' . $this->getLang('entry') . '</th>');
        ptln('<th>' . $this->getLang('blog') . '</th>');
        ptln('<th>' . $this->getLang('commentstatus') . '</th>');
        ptln('<th>' . $this->getLang('comments') . '</th>');
        ptln('<th>' . $this->getLang('tags') . '</th>');
        ptln('<th></th>');
        foreach($entries as $entry) {
            $this->xhtml_entry_item($entry, $query);
        }
        ptln('</table>');
        ptln('</div>');
    }

    /**
     * Displays a single entry and related actions
     *
     * @param $entry Single entry
     * @param $query Query parameters
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    private function xhtml_entry_item($entry, $query) {
        global $lang;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        ptln('<td class="entry_created">' . dformat($entry['created']) . '</td>');
        ptln('<td class="entry_author">' . hsc($entry['author']) . '</td>');
        ptln('<td class="entry_title">' . html_wikilink(':'.$entry['page'], $entry['title']) . '</td>');
        ptln('<td class="entry_set_blog">' . $this->xhtml_entry_edit_form($entry, $query, 'blog') . '</th>');
        ptln('<td class="entry_set_commentstatus">' . $this->xhtml_entry_edit_form($entry, $query, 'commentstatus') . '</th>');

        $this->commenthelper->setPid($entry['pid']);

        // search comments of this entry link
        ptln('<td class="entry_comments">');
        $count = $this->commenthelper->get_count(null, true);
        if($count > 0) {
            $params = array('do' => 'admin',
                            'page' => 'blogtng',
                            'btng[admin]' => 'search',
                            'btng[query][filter]' => 'comment',
                            'btng[query][pid]' => $entry['pid']);
            ptln('<a href="' . wl($ID, $params) . '" title="' . $this->getLang('comments') . '">' . $count . '</a>');
        } else {
            ptln($count);
        }
        ptln('</td>');

        // tags filter links
        ptln('<td class="entry_tags">');
        $this->taghelper->load($entry['pid']);
        $tags = $this->taghelper->getTags();
        $count = count($tags);
        for($i = 0; $i < $count; $i++) {
            $params = array('do' => 'admin',
                            'page' => 'blogtng',
                            'btng[admin]' => 'search',
                            'btng[query][filter]' => 'tags',
                            'btng[query][string]' => $tags[$i]);
            $link = '<a href="' . wl($ID, $params) . '" title="' . $tags[$i] . '">' . $tags[$i] . '</a>';
            if($i < ($count - 1)) $link .= ', ';
            ptln($link);
        }
        ptln('</td>');

        // edit links
        ptln('<td class="entry_edit">');
        $params = array('id' => $entry['page'],
                        'do' => 'edit');
        ptln('<a href="' . wl($ID, $params) . '" class="blogtng_btn_edit" title="' . $lang['btn_secedit'] . '">' . $lang['btn_secedit'] . '</a>');
        ptln('</td>');

        ptln('</tr>');
    }

    /**
     * Displays a list of comments, as callback of xhtml_search_results()
     *
     * @param $comments List of comments
     * @param $query    Query parameters
     * 
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_comment_list($comments, $query) {
        global $lang;

        ptln('<div class="level2">');

        ptln('<form action="' . DOKU_SCRIPT . '" method="post" id="blogtng__comment_batch_edit_form">');
        ptln('<input type="hidden" name="page" value="blogtng" />');
        ptln('<input type="hidden" name="btng[comment][limit]" value="' .$this->getLimitParam($query). '" />');
        ptln('<input type="hidden" name="btng[comment][offset]" value="' .$this->getLimitParam($query). '" />');
        ptln('<input type="hidden" name="sectok" value="' .getSecurityToken(). '" />');

        ptln('<table class="inline">');

        ptln('<th id="blogtng__admin_checkall_th"></th>');
        ptln('<th>' . $this->getLang('comments') . '</th>');
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
     * @param $comment A single comment
     * 
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_comment_item($comment) {
        global $lang;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        $cmt = new blogtng_comment();
        $cmt->init($comment);
        ptln('<td class="admin_checkbox">');
            ptln('<input type="checkbox" class="comment_cid" name="btng[comments][cids][]" value="' . $comment['cid'] . '" />');
        ptln('</td>');

        ptln('<td class="comment_row">');
        ptln('<div class="comment_text" title="'.$this->getLang('comment_text').'">' . hsc($comment['text']) . '</div>');
        ptln('<div class="comment_metadata">');
            ptln('<span class="comment_created" title="'.$this->getLang('created').'">' . dformat($comment['created']) . '</span>');
            ptln('<span class="comment_ip" title="'.$this->getLang('comment_ip').'">' . hsc($comment['ip']) . '</span>');

            ptln('<span class="comment_name" title="'.$this->getLang('comment_name').'">');
                $avatar = $cmt->tpl_avatar(16,16,true);
                if($avatar) ptln('<img src="' . $avatar . '" alt="' . hsc($comment['name']) . '" class="avatar" /> ');
                if($comment['mail']){
                    ptln('<a href="mailto:' . hsc($comment['mail']) . '" class="mail" title="' . hsc($comment['mail']) . '">' . hsc($comment['name']) . '</a>');
                }else{
                    ptln(hsc($comment['name']));
                }
            ptln('</span>');

            $weburl = '';
            if($comment['web']) $weburl = '<a href="' . hsc($comment['web']) . '" title="' . hsc($comment['web']) . '">' . hsc($comment['web']) . '</a>';
            ptln('<span class="comment_web" title="'.$this->getLang('comment_web').'">'.$weburl.'</span>');

            ptln('<span class="comment_status" title="'.$this->getLang('comment_status').'">' . hsc($comment['status']) . '</span>');
            ptln('<span class="comment_source" title="'.$this->getLang('comment_source').'">' . hsc($comment['source']) . '</span>');

            $this->entryhelper->load_by_pid($comment['pid']);
            $pagelink = html_wikilink(':'.$this->entryhelper->entry['page'], $this->entryhelper->entry['title']);
            ptln('<span class="comment_entry" title="'.$this->getLang('comment_entry').'">' . $pagelink . '</span>');
        ptln('</div>');
        ptln('</td>');

        ptln('<td class="comment_edit">');
            $params = array('do'=>'admin',
                            'page'=>'blogtng',
                            'btng[comment][cid]'=>$comment['cid'],
                            'btng[admin]'=>'comment_edit');
            ptln('<a href="' . wl($ID, $params). '" class="blogtng_btn_edit" title="' . $lang['btn_edit'] . '">' . $lang['btn_secedit'] . '</a>');
        ptln('</td>');

        ptln('</tr>');
    }

    /**
     * Displays a preview of the comment
     *
     * @param array $data submitted comment properties
     */
    private function xhtml_comment_preview($data) {
        $this->entryhelper->load_by_pid($data['pid']);
        $blogname = $this->entryhelper->get_blog();

        ptln('<div id="blogtng__comment_preview">');
        ptln(p_locale_xhtml('preview'));
        ptln('<br />');
        $comment = new blogtng_comment();
        $comment->init($data);
        $comment->output($blogname);
        ptln('</div>');
    }

    /**
     * Displays the form to change the comment status of a blog entry
     *
     * @param $entry Blog entry
     * @param $query Query parameters
     * @param string $field
     * @return Doku_Form|string
     * 
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_entry_edit_form($entry, $query, $field = 'commentstatus') {
        global $lang;

        $changablefields = array('commentstatus', 'blog');
        if(!in_array($field, $changablefields)) return hsc($entry[$field]);

        $form = new Doku_Form(array('id'=>"blogtng__entry_set_{$field}_form"));
        $form->addHidden('do', 'admin');
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[entry][pid]', $entry['pid']);
        $form->addHidden('btng[entry][limit]', $this->getLimitParam($query));
        $form->addHidden('btng[entry][offset]', $this->getOffsetParam($query));

        if($field == 'commentstatus') {
            $availableoptions = array('enabled', 'disabled', 'closed');
        } else {
            $availableoptions = $this->entryhelper->get_blogs();
        }
        $form->addElement(form_makeListBoxField("btng[entry][$field]", $availableoptions, $entry[$field], ''));
        $form->addElement('<input type="submit" name="btng[admin][entry_set_'.$field.']" class="edit button" value="' . $lang['btn_update'] . '" />');

        ob_start();
        html_form("blotng__btn_entry_set_{$field}", $form);
        $form = ob_get_clean();
        return $form;
    }

    /**
     * Displays the comment edit form
     *
     * @param $comment
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    private function xhtml_comment_edit_form($comment) {
        global $lang;

        ptln('<div class="level1">');
        $form = new Doku_Form(array('id'=>'blogtng__comment_edit_form'));
        $form->startFieldset($this->getLang('act_comment_edit'));
        $form->addHidden('page', 'blogtng');
        $form->addHidden('do', 'admin');
        $form->addHidden('btng[comment][cid]', $comment['cid']);
        $form->addHidden('btng[comment][pid]', $comment['pid']);
        $form->addHidden('btng[comment][created]', $comment['created']);
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
    private function xhtml_search_form() {
        global $lang;

        ptln('<div class="level1">');

        $blogs = $this->entryhelper->get_blogs();

        $form = new Doku_Form(array('id'=>'blogtng__search_form'));
        $form->startFieldset($lang['btn_search']);
        $form->addHidden('page', 'blogtng');
        $form->addHidden('btng[admin]', 'search');

        $form->addElement(form_makeListBoxField('btng[query][blog]', $blogs, $_REQUEST['btng']['query']['blog'], $this->getLang('blog')));
        $form->addElement(form_makeListBoxField('btng[query][filter]', array('entry_title', 'entry_author', 'comment', 'comment_ip', 'tags'), $_REQUEST['btng']['query']['filter'], $this->getLang('filter')));
        $form->addElement(form_makeTextField('btng[query][string]', $_REQUEST['btng']['query']['string'],''));

        $form->addElement(form_makeButton('submit', 'admin', $lang['btn_search']));
        $form->endFieldset();
        html_form('blogtng__search_form', $form);

        ptln('</div>');
    }

    /**
     * Displays the limit selection form
     *
     * @param string $query
     * 
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function xhtml_limit_form($query='') {
        global $lang;

        $limit = $this->getLimitParam($query);
        $resultset = $query['resultset'];

        ptln('<div class="level1">');

        $form = new Doku_Form(array('id'=>'blogtng__'.$resultset.'_limit_form'));
        $form->startFieldset("");
        $form->addHidden('page', 'blogtng');
        $form->addElement(
                form_makeListBoxField("btng[{$resultset}][limit]",
                    array(5,10,15,20,25,30,40,50,100),
                    $limit,
                    $this->getLang('numhits')));
        $form->addHidden("btng[{$resultset}][offset]", $this->getOffsetParam($query));
        $form->addElement(form_makeButton('submit', 'admin', $lang['btn_update']));
        $form->endFieldset();
        html_form('blogtng__'.$resultset.'_cnt_form', $form);

        ptln('</div>');
    }

    /**
     * Get submitted value of items per page
     *
     * @param array $query   url parameters
     * @param int   $default value
     * @return int number of items per page
     *
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function getLimitParam($query, $default = 5) {
        return (int) (isset($query['limit']) ? $query['limit'] : $default);
    }

    /**
     * Get the offset of the pagination
     *
     * @param array $query    url parameters
     * @param int   $default  value
     * @return int offset number of items
     */
    public function getOffsetParam($query, $default = 0) {
        return (int) (isset($query['offset']) ? $query['offset'] : $default);
    }
}
// vim:ts=4:sw=4:et:
