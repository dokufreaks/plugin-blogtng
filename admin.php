<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

use dokuwiki\plugin\blogtng\entities\Comment;

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
     * @var string action in admin to perform
     */
    private $admin;
    /**
     * @var int number of comments per page of the comment list
     */
    private $commentLimit;
    /**
     * @var int number of entries per page of the entry list
     */
    private $entryLimit;

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
        global $INPUT;
        if($INPUT->has('admin')) {
            $this->admin = $INPUT->extract('admin')->str('admin'); //can be post or get
        } else {
            $this->admin = '';
        }
        //skip actions when no valid security token given
        $noSecTokenNeeded = ['search', 'comment_edit', 'comment_preview', null];
        if(!in_array($this->admin, $noSecTokenNeeded) && !checkSecurityToken()) {
            $this->admin = '';
        }

        //get some preferences
        $this->commentLimit = (int) get_doku_pref('blogtng_comment_limit', 15);
        $this->entryLimit = (int) get_doku_pref('blogtng_entry_limit', 5);


        // handle actions
        switch($this->admin) {

            case 'comment_save':
                // FIXME error handling?
                $comment = $this->getPostedComment();

                $this->commenthelper->save($comment);
                msg($this->getLang('msg_comment_save'), 1);
                break;

            case 'comment_delete':
                // FIXME error handling
                $this->commenthelper->delete($INPUT->post->str('comment-cid'));
                msg($this->getLang('msg_comment_delete'), 1);
                break;

            case 'comment_batch_edit':
                $batch = $INPUT->post->str('comment_batch_edit');
                $cids  = $INPUT->post->arr('comments-cids');
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
                //back to default view
                $this->admin = '';
                break;

            case 'entry_set_blog':
                // FIXME errors?
                $pid = $INPUT->post->str('entry-pid');
                $blog = $INPUT->post->str('entry-blog');
                if($pid) {
                    $blogs = $this->entryhelper->getAllBlogs();
                    if(in_array($blog, $blogs)) {
                        $this->entryhelper->load_by_pid($pid);
                        $this->entryhelper->entry['blog'] = $blog;
                        $this->entryhelper->save();
                    }
                }
                msg($this->getLang('msg_entry_blog_change'), 1);
                break;

            case 'entry_set_commentstatus':
                $pid = $INPUT->post->str('entry-pid');
                $status = $INPUT->post->str('entry-commentstatus');
                if($pid) {
                    if(in_array($status, ['disabled', 'enabled', 'closed'])) {
                        $this->entryhelper->load_by_pid($pid);
                        $this->entryhelper->entry['commentstatus'] = $status;
                        $this->entryhelper->save();
                    }
                }
                msg($this->getLang('msg_comment_status_change'), 1);
                break;

            case 'comment_edit':
            case 'comment_preview':
            case 'search':
            default:
                // do nothing - show dashboard

                //update preferences
                if ($INPUT->has('comment-limit')) {
                    $this->commentLimit = $INPUT->int('comment-limit');
                    set_doku_pref('blogtng_comment_limit', $this->commentLimit);
                }

                if ($INPUT->has('entry-limit')) {
                    $this->entryLimit = $INPUT->int('entry-limit');
                    set_doku_pref('blogtng_entry_limit', $this->entryLimit);
                }
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
        global $ID, $INPUT;

        ptln('<h1>'.$this->getLang('menu').'</h1>');

        ptln('<div id="blogtng__admin">');

        // display link back to dashboard
        if($this->admin) {
            ptln('<div class="level1">');
            $params = ['do'=>'admin', 'page'=>'blogtng'];
            ptln('<p><a href="' . wl($ID, $params) . '" title="' . $this->getLang('dashboard') . '">&larr; ' . $this->getLang('dashboard') . '</a></p>');
            ptln('</div>');

        }

        switch($this->admin) {
            case 'search':
                // display search form
                $this->htmlSearchForm();

                ptln('<h2>' . $this->getLang('searchresults') . '</h2>');

                $query = $INPUT->arr('query');
                $query['resultset'] = 'query';
                $query['limit'] = 20;
                $query['offset'] = $INPUT->int('query-offset');
                $this->htmlSearchResults($query);
                break;

            case 'comment_edit':
            case 'comment_preview':
                if($this->admin == 'comment_edit') {
                    $comment = $this->commenthelper->comment_by_cid($INPUT->get->str('comment-cid'));
                    if($comment !== null) {
                        $this->htmlCommentEditForm($comment);
                    }
                }
                if($this->admin == 'comment_preview') {
                    $comment = $this->getPostedComment();

                    $this->htmlCommentEditForm($comment);
                    $this->htmlCommentPreview($comment);
                }
                break;

            default:
                // display search form
                $this->htmlSearchForm();

                // print latest 'x' comments/entries
                $query['limit'] = $this->commentLimit;
                $query['offset'] = $INPUT->int('comment-offset');
                $query['resultset'] = 'comment';
                $this->htmlLatestItems($query);

                $query['limit'] = $this->entryLimit;
                $query['offset'] = $INPUT->int('entry-offset');
                $query['resultset']  = 'entry';
                $this->htmlLatestItems($query);
                break;
        }

        ptln('</div>');
    }

    /**
     * Displays a list of comments or entries for a given search term
     *
     * @param array $query url parameters for query
     */
    private function htmlSearchResults($query) {
        if(!$this->sqlitehelper->ready()) return;

        $db = $this->sqlitehelper->getDB();

        switch($query['filter']) {
            case 'entry_title':
            case 'entry_author':
                $select  = 'SELECT * ';
                $from    = 'FROM entries ';
                $orderby = 'ORDER BY created DESC ';
                $itemdisplaycallback = 'htmlEntryList';
                break;
            case 'comment':
            case 'comment_ip':
                $select = 'SELECT cid, comments.pid as pid, ip, source, name, comments.mail as mail, web, avatar, comments.created as created, text, status ';
                $from   = 'FROM comments LEFT JOIN entries ON comments.pid = entries.pid ';
                $orderby = 'ORDER BY comments.created DESC ';
                $itemdisplaycallback = 'htmlCommentList';
            break;
            case 'tags':
                $select = 'SELECT DISTINCT entries.pid as pid, page, title, blog, image, created, lastmod, author, login, mail ';
                $from   = 'FROM entries  LEFT JOIN tags ON entries.pid = tags.pid ';
                $orderby = 'ORDER BY created DESC ';
                $itemdisplaycallback = 'htmlEntryList';
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


        $sqlselect .= ' LIMIT '.$query['limit'];
        if($query['offset'] > 0) {
            $sqlselect .= ' OFFSET '.$query['offset'];
        }

        $res = $db->query($sqlcount);
        $count = $db->res2single($res);

        $resid = $db->query($sqlselect);
        if($resid) {
            $this->htmlShowPaginatedResult($resid, $query, $itemdisplaycallback, $count, $query['limit']);
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
    private function htmlShowPaginatedResult($resid, $query, $itemdisplaycallback, $count, $limit) {
        global $lang;
        if(!$resid) return;

        $currentpage   =  floor($query['offset'] / $limit) + 1;

        $items = $this->sqlitehelper->getDB()->res2arr($resid);

        if($items) {
            ptln('<div class="level2"><p><strong>' . $this->getLang('numhits') . ':</strong> ' . $count .'</p></div>');

            // show pagination only when enough items
            if($count > $limit) {
                $this->htmlPagination($query, $currentpage, $count, $limit);
            }
            // show list of items using callback
            call_user_func(array($this, $itemdisplaycallback), $items, $query);

        } else {
            ptln('<div class="level2">');
            ptln($lang['nothingfound']);
            ptln('</div>');
        }

        // show pagination only when enough items
        if($count > $limit) {
            $this->htmlPagination($query, $currentpage, $count, $limit);
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
    private function htmlPagination($query, $currentpage, $maximum, $limit) {
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
            $this->htmlPaginationurl($query, ($currentpage - 2) * $limit, '&laquo;', $currentpage - 1);
        }

        $last = 0;
        foreach($pages as $page) {
            if($page - $last > 1) {
                ptln('<span class="sep">...</span>');
            }
            if($page == $currentpage) {
                ptln('<span class="cur">' . $page . '</span>');
            } else {
                $this->htmlPaginationurl($query, ($page - 1) * $limit, $page, $page);
            }
            $last = $page;
        }

        if($currentpage < $lastpage) {
            $this->htmlPaginationurl($query, $currentpage * $limit, '&raquo;', $currentpage + 1);
        }

        ptln('</p></div>');
    }

    /**
     * Print a pagination link.
     *
     * @param array   $query   Query parameters
     * @param int     $offset  number of previous items
     * @param string  $text    text of url
     * @param string  $title   title of url
     * @internal param string $anchor url anchor
     */
    private function htmlPaginationurl($query, $offset, $text, $title) {
        global $ID;
        list($params, $anchor) = $this->buildUrlParams($query, $offset);
        ptln("<a href='".wl($ID, $params).'#'.$anchor."' title='$title'>$text</a>");
    }

    /**
     * Build URL parameters.
     *
     * @param array  $query   Query parameters
     * @param int    $offset  number of previous items
     * @return array($params, $anchor)
     */
    private function buildUrlParams($query, $offset) {
        $params = [
            'do' => 'admin',
            'page' => 'blogtng',
            $query['resultset'].'-offset' => $offset
        ];
        $anchor = $query['resultset'] . '_latest';

        if($query['resultset'] == 'query') {
            $params = $params + [
                    'admin' => 'search',
                    'query[filter]' => $query['filter'],
                    'query[blog]'   => $query['blog'],
                    'query[string]' => $query['string'],
                    'query[pid]'    => $query['pid']
                ];
            $anchor = '';
        }
        return [$params, $anchor];
    }

    /**
     * Display the latest comments or entries
     *
     * @param array $query parameters
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function htmlLatestItems($query) {
        $resultset = $query['resultset']; //query,comment,entry

        printf("<h2 id='{$resultset}_latest'>".$this->getLang($resultset.'_latest').'</h2>', $query['limit']);
        $this->htmlLimitForm($query);

        if(!$this->sqlitehelper->ready()) return;

        $count = 'SELECT COUNT(pid) as count ';
        $select = 'SELECT * ';

        if($resultset == 'entry') {
            $from = 'FROM entries ';
            $where = 'WHERE blog != "" ';
            $itemdisplaycallback = 'htmlEntryList';
        } else {
            $from = 'FROM comments ';
            $where = '';
            $itemdisplaycallback = 'htmlCommentList';
        }

        $orderby = 'ORDER BY created DESC ';

        $sqlcount = $count . $from . $where;
        $sqlselect = $select . $from . $where . $orderby;

        $limit = $query['limit'];
        $sqlselect .= 'LIMIT ' . $limit;
        if($query['offset']) {
            $sqlselect .= ' OFFSET ' . $query['offset'];
        }

        $res = $this->sqlitehelper->getDB()->query($sqlcount);
        $count = $this->sqlitehelper->getDB()->res2single($res);

        $resid = $this->sqlitehelper->getDB()->query($sqlselect);
        if(!$resid) {
            return;
        }
        $this->htmlShowPaginatedResult($resid, $query, $itemdisplaycallback, $count, $limit);
    }

    /**
     * Displays a list of entries, as callback of htmlSearchResults()
     *
     * @param array $entries of entries
     * @param array $query parameters
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    private function htmlEntryList($entries, $query) {
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
            $this->htmlEntryItem($entry, $query);
        }
        ptln('</table>');
        ptln('</div>');
    }

    /**
     * Displays a single entry and related actions
     *
     * @param array $entry Single entry
     * @param array $query Query parameters
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    private function htmlEntryItem($entry, $query) {
        global $lang;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        ptln('<td class="entry_created">' . dformat($entry['created']) . '</td>');
        ptln('<td class="entry_author">' . hsc($entry['author']) . '</td>');
        ptln('<td class="entry_title">' . html_wikilink(':'.$entry['page'], $entry['title']) . '</td>');
        ptln('<td class="entry_set_blog">' . $this->htmlEntryEditForm($entry, $query, 'blog') . '</th>');
        ptln('<td class="entry_set_commentstatus">' . $this->htmlEntryEditForm($entry, $query, 'commentstatus') . '</th>');

        $this->commenthelper->setPid($entry['pid']);

        // search comments of this entry link
        ptln('<td class="entry_comments">');
        $count = $this->commenthelper->get_count(null, true);
        if($count > 0) {
            $params = array('do' => 'admin',
                            'page' => 'blogtng',
                            'admin' => 'search',
                            'query[filter]' => 'comment',
                            'query[pid]' => $entry['pid']);
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
                            'admin' => 'search',
                            'query[filter]' => 'tags',
                            'query[string]' => $tags[$i]);
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
     * Displays a list of comments, as callback of htmlSearchResults()
     *
     * @param array[] $comments List of comments
     * @param array $query    Query parameters
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function htmlCommentList($comments, $query) {
        global $lang;

        ptln('<div class="level2">');

        ptln('<form action="' . DOKU_SCRIPT . '" method="post" id="blogtng__comment_batch_edit_form">');
        ptln('<input type="hidden" name="page" value="blogtng" />');
        ptln('<input type="hidden" name="comment-offset" value="' .$query['offset']. '" />');
        ptln('<input type="hidden" name="sectok" value="' .getSecurityToken(). '" />');
        ptln('<input type="hidden" name="admin" value="comment_batch_edit" />');

        ptln('<table class="inline">');

        ptln('<th id="blogtng__admin_checkall_th"></th>');
        ptln('<th>' . $this->getLang('comments') . '</th>');
        ptln('<th></th>');

        foreach($comments as $comment) {
            $this->htmlCommentItem(new Comment($comment));
        }

        ptln('</table>');

        ptln('<select name="comment_batch_edit">');
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
     * @param Comment $comment A single comment
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function htmlCommentItem($comment) {
        global $lang;
        global $ID;

        static $class = 'odd';
        ptln('<tr class="' . $class . '">');
        $class = ($class == 'odd') ? 'even' : 'odd';

        $cmt = new Comment($comment);
        ptln('<td class="admin_checkbox">');
            ptln('<input type="checkbox" class="comment_cid" name="comments-cids[]" value="' . $comment->getCid() . '" />');
        ptln('</td>');

        ptln('<td class="comment_row">');
        ptln('<div class="comment_text" title="'.$this->getLang('comment_text').'">' . hsc($comment->getText()) . '</div>');
        ptln('<div class="comment_metadata">');
            ptln('<span class="comment_created" title="'.$this->getLang('created').'">' . dformat($comment->getCreated()) . '</span>');
            ptln('<span class="comment_ip" title="'.$this->getLang('comment_ip').'">' . hsc($comment->getIp()) . '</span>');

            ptln('<span class="comment_name" title="'.$this->getLang('comment_name').'">');
                $avatar = $cmt->tpl_avatar(16,16,true);
                if($avatar) ptln('<img src="' . $avatar . '" alt="' . hsc($comment->getName()) . '" class="avatar" /> ');
                if($comment->getMail()){
                    ptln('<a href="mailto:' . hsc($comment->getMail()) . '" class="mail" title="' . hsc($comment->getMail()) . '">' . hsc($comment->getName()) . '</a>');
                }else{
                    ptln(hsc($comment->getName()));
                }
            ptln('</span>');

            $weburl = '';
            if($comment->getWeb()) {
                $weburl = '<a href="' . hsc($comment->getWeb()) . '" title="' . hsc($comment->getWeb()) . '">' . hsc($comment->getWeb()) . '</a>';
            }
            ptln('<span class="comment_web" title="'.$this->getLang('comment_web').'">'.$weburl.'</span>');

            ptln('<span class="comment_status" title="'.$this->getLang('comment_status').'">' . hsc($comment->getStatus()) . '</span>');
            ptln('<span class="comment_source" title="'.$this->getLang('comment_source').'">' . hsc($comment->getSource()) . '</span>');

            $this->entryhelper->load_by_pid($comment->getPid());
            $pagelink = html_wikilink(':'.$this->entryhelper->entry['page'], $this->entryhelper->entry['title']);
            ptln('<span class="comment_entry" title="'.$this->getLang('comment_entry').'">' . $pagelink . '</span>');
        ptln('</div>');
        ptln('</td>');

        ptln('<td class="comment_edit">');
            $params = array('do'=>'admin',
                            'page'=>'blogtng',
                            'comment-cid'=>$comment->getCid(),
                            'admin'=>'comment_edit');
            ptln('<a href="' . wl($ID, $params). '" class="blogtng_btn_edit" title="' . $lang['btn_edit'] . '">' . $lang['btn_secedit'] . '</a>');
        ptln('</td>');

        ptln('</tr>');
    }

    /**
     * Displays a preview of the comment
     *
     * @param Comment $comment submitted comment
     */
    private function htmlCommentPreview($comment) {
        $this->entryhelper->load_by_pid($comment->getPid());
        $blogname = $this->entryhelper->get_blog();

        ptln('<div id="blogtng__comment_preview">');
        ptln(p_locale_xhtml('preview'));
        ptln('<br />');
        $comment->output($blogname);
        ptln('</div>');
    }

    /**
     * Displays the form to change the comment status of a blog entry
     *
     * @param array $entry Blog entry
     * @param array $query Query parameters
     * @param string $field 'blog' or 'commentstatus'
     * @return false|string
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function htmlEntryEditForm($entry, $query, $field = 'commentstatus') {
        global $lang;

        $changablefields = ['commentstatus', 'blog'];
        if(!in_array($field, $changablefields)) return hsc($entry[$field]);

        $form = new Doku_Form(['id'=>"blogtng__entry_set_{$field}_form"]);
        $form->addHidden('do', 'admin');
        $form->addHidden('page', 'blogtng');
        $form->addHidden('entry-pid', $entry['pid']);
        $form->addHidden('entry-offset', $query['offset']);

        if($field == 'commentstatus') {
            $availableoptions = ['enabled', 'disabled', 'closed'];
        } else {
            $availableoptions = $this->entryhelper->getAllBlogs();
        }
        $form->addElement(form_makeListBoxField("entry-$field", $availableoptions, $entry[$field], ''));
        $form->addElement('<input type="submit" name="admin[entry_set_'.$field.']" class="edit button" value="' . $lang['btn_update'] . '" />');

        ob_start();
        html_form("blotng__btn_entry_set_$field", $form);
        return ob_get_clean();
    }

    /**
     * Displays the comment edit form
     *
     * @param Comment $comment
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    private function htmlCommentEditForm($comment) {
        global $lang;

        ptln('<div class="level1">');
        $form = new Doku_Form(['id'=>'blogtng__comment_edit_form']);
        $form->startFieldset($this->getLang('act_comment_edit'));
        $form->addHidden('page', 'blogtng');
        $form->addHidden('do', 'admin');
        $form->addHidden('comment-cid', $comment->getCid());
        $form->addHidden('comment-pid', $comment->getPid());
        $form->addHidden('comment-created', $comment->getCreated());
        $form->addElement(form_makeListBoxField('comment-status', ['visible', 'hidden'], $comment->getStatus(), $this->getLang('comment_status')));
        $form->addElement('<br />');
        $form->addElement(form_makeListBoxField('comment-source', ['comment', 'trackback', 'pingback'], $comment->getSource(), $this->getLang('comment_source')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('comment-name', $comment->getName(), $this->getLang('comment_name')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('comment-mail', $comment->getMail(), $this->getLang('comment_mail')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('comment-web', $comment->getWeb(), $this->getLang('comment_web')));
        $form->addElement('<br />');
        $form->addElement(form_makeTextField('comment-avatar', $comment->getAvatar(), $this->getLang('comment_avatar')));
        $form->addElement('<br />');
        $form->addElement('<textarea class="edit" name="comment-text" rows="10" cols="80">' . $comment->getText() . '</textarea>');
        $form->addElement('<input type="submit" name="admin[comment_save]" class="edit button" value="' . $lang['btn_save'] . '" />');
        $form->addElement('<input type="submit" name="admin[comment_preview]" class="edit button" value="' . $lang['btn_preview'] . '" />');
        $form->addElement('<input type="submit" name="admin[comment_delete]" class="edit button" value="' . $lang['btn_delete'] . '" />');
        $form->endFieldset();
        html_form('blogtng__edit_comment', $form);
        ptln('</div>');
    }

    /**
     * Displays the search form
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    private function htmlSearchForm() {
        global $lang, $INPUT;

        ptln('<div class="level1">');

        $blogs = $this->entryhelper->getAllBlogs();

        $form = new Doku_Form(array('id'=>'blogtng__search_form'));
        $form->startFieldset($lang['btn_search']);
        $form->addHidden('page', 'blogtng');
        $form->addHidden('admin', 'search');

        $query = $INPUT->arr('query');
        $form->addElement(form_makeListBoxField('query[blog]', $blogs, $query['blog'], $this->getLang('blog')));
        $form->addElement(form_makeListBoxField('query[filter]', ['entry_title', 'entry_author', 'comment', 'comment_ip', 'tags'], $query['filter'], $this->getLang('filter')));
        $form->addElement(form_makeTextField('query[string]', $query['string'],''));

        $form->addElement(form_makeButton('submit', 'admin', $lang['btn_search']));
        $form->endFieldset();
        html_form('blogtng__search_form', $form);

        ptln('</div>');
    }

    /**
     * Displays the limit selection form
     *
     * @param array $query Query parameters
     *
     * @author hArpanet <dokuwiki-blogtng@harpanet.com>
     */
    private function htmlLimitForm($query) {
        global $lang;

        $resultset = $query['resultset'];

        ptln('<div class="level1">');

        $form = new Doku_Form(['id'=>'blogtng__'.$resultset.'_limit_form']);
        $form->startFieldset("");
        $form->addHidden('page', 'blogtng');
        $form->addElement(
                form_makeListBoxField($resultset . '-limit',
                    [5,10,15,20,25,30,40,50,100],
                    $query['limit'],
                    $this->getLang('numhits')));
        $form->addHidden($resultset . '-offset', $query['offset']);
        $form->addElement(form_makeButton('submit', 'admin', $lang['btn_update']));
        $form->endFieldset();
        html_form('blogtng__'.$resultset.'_cnt_form', $form);

        ptln('</div>');
    }

    /**
     * Create a comment with the posted from data
     *
     * @return Comment
     */
    private function getPostedComment()
    {
        global $INPUT;

        $comment = new Comment();
        $comment->setCid($INPUT->post->str('comment-cid'));
        $comment->setPid($INPUT->post->str('comment-pid'));
        $comment->setCreated($INPUT->post->str('comment-created'));
        $comment->setStatus($INPUT->post->str('comment-status'));
        $comment->setSource($INPUT->post->str('comment-source'));
        $comment->setName($INPUT->post->str('comment-name'));
        $comment->setMail($INPUT->post->str('comment-mail'));
        $comment->setWeb($INPUT->post->str('comment-web'));
        $comment->setAvatar($INPUT->post->str('comment-avatar'));
        $comment->setText($INPUT->post->str('comment-text'));
        return $comment;
    }
}
