<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class helper_plugin_blogtng_comments
 */
class helper_plugin_blogtng_comments extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_blogtng_sqlite
     */
    private $sqlitehelper = null;

    private $pid;

    /**
     * Constructor, loads the sqlite helper plugin
     */
    public function __construct() {
        $this->sqlitehelper = plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Set pid
     *
     * @param $pid
     */
    public function setPid($pid) {
        $this->pid = trim($pid);
    }

    /**
     * Select comment by cid and return it as a blogtng_comment. The
     * function returns false if the database query fails. It returns
     * null if the query result is empty.
     * 
     * @param  $cid The cid
     * @return blogtng_comment|bool|null
     */
    public function comment_by_cid($cid) {

        $query = 'SELECT cid, pid, source, name, mail, web, avatar, created, text, status FROM comments WHERE cid = ?';
        $resid = $this->sqlitehelper->getDB()->query($query, $cid);
        if ($resid === false) {
            return false;
        }
        if ($this->sqlitehelper->getDB()->res2count($resid) == 0) {
            return null;
        }
        $result = $this->sqlitehelper->getDB()->res2arr($resid);

        $comment = new blogtng_comment();
        $comment->init($result[0]);
        return $comment;
    }

    /**
     * Get comment count
     *
     * @param null $types
     * @param bool $includehidden
     * @return int
     */
    public function get_count($types=null, $includehidden=false) {
        if (!$this->sqlitehelper->ready()) return 0;

        $sql = 'SELECT COUNT(pid) as val
                  FROM comments
                 WHERE pid = ?';
        if ($includehidden === false){
            $sql .= ' AND status = \'visible\'';
        }
        $args = array();
        $args[] = $this->pid;
        if(is_array($types)){
            $qs = array();
            foreach($types as $type){
                $args[] = $type;
                $qs[]   = '?';
            }
            $sql .= ' AND type IN ('.join(',',$qs).')';
        }
        $res = $this->sqlitehelper->getDB()->query($sql,$args);
        $res = $this->sqlitehelper->getDB()->res2row($res,0);
        return (int) $res['val'];
    }

    /**
     * Save comment
     *
     * @param $comment
     */
    public function save($comment) {
        if (!$this->sqlitehelper->ready()) {
            msg('BlogTNG: no sqlite helper plugin available', -1);
            return;
        }

        if (isset($comment['cid'])) {
            // Doing an update
            $query = 'UPDATE comments SET pid=?, source=?, name=?, mail=?, ' .
                     'web=?, avatar=?, created=?, text=?, status=? WHERE cid=?';
            $this->sqlitehelper->getDB()->query($query,
                $comment['pid'],
                $comment['source'],
                $comment['name'],
                $comment['mail'],
                $comment['web'],
                $comment['avatar'],
                $comment['created'],
                $comment['text'],
                $comment['status'],
                $comment['cid']
            );
            return;
        }

        // Doing an insert
        /** @var helper_plugin_blogtng_entry $entry */
        $entry = plugin_load('helper', 'blogtng_entry');
        $entry->load_by_pid($comment['pid']);
        if ($entry->entry['commentstatus'] !== 'enabled') {
            return;
        }

        $query =
            'INSERT OR IGNORE INTO comments (
                pid, source, name, mail, web, avatar, created, text, status, ip
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )';
        $comment['status']  = ($this->getconf('moderate_comments')) ? 'hidden' : 'visible';

        if(!isset($comment['created'])) $comment['created'] = time();

        $comment['avatar']  = ''; // FIXME create avatar using a helper function

        $this->sqlitehelper->getDB()->query($query,
            $comment['pid'],
            $comment['source'],
            $comment['name'],
            $comment['mail'],
            $comment['web'],
            $comment['avatar'],
            $comment['created'],
            $comment['text'],
            $comment['status'],
            $comment['ip']
        );

        //retrieve cid of this comment
        $sql = "SELECT cid
                  FROM comments
                 WHERE pid = ?
                   AND created = ?
                   AND mail =?
                 LIMIT 1";
        $res = $this->sqlitehelper->getDB()->query($sql,
                                                   $comment['pid'],
                                                   $comment['created'],
                                                   $comment['mail']);
        $cid = $this->sqlitehelper->getDB()->res2single($res);
        $comment['cid'] = $cid === false ? 0 : $cid;


        // handle subscriptions
        if($this->getConf('comments_subscription')) {
            if($comment['subscribe']) {
                $this->subscribe($comment['pid'],$comment['mail']);
            }
            // send subscriber and notify mails
            $this->send_subscriber_mails($comment);
        }
    }

    /**
     * Delete comment
     *
     * @param $cid
     * @return bool
     */
    public function delete($cid) {
        if (!$this->sqlitehelper->ready()) return false;
        $query = 'DELETE FROM comments WHERE cid = ?';
        return (bool) $this->sqlitehelper->getDB()->query($query, $cid);
    }

    /**
     * Delete all comments for an entry
     *
     * @param $pid
     * @return bool
     */
    public function delete_all($pid) {
        if (!$this->sqlitehelper->ready()) return false;
        $sql = "DELETE FROM comments WHERE pid = ?";
        return (bool) $this->sqlitehelper->getDB()->query($sql,$pid);
    }

    /**
     * Moderate comment
     *
     * @param $cid
     * @param $status
     * @return bool
     */
    public function moderate($cid, $status) {
        if (!$this->sqlitehelper->ready()) return false;
        $query = 'UPDATE comments SET status = ? WHERE cid = ?';
        return (bool) $this->sqlitehelper->getDB()->query($query, $status, $cid);
    }

    /**
     * Send a mail about the new comment
     *
     * Mails are sent to the author of the post and
     * all subscribers that opted-in
     *
     * @param $comment
     */
    public function send_subscriber_mails($comment){
        global $conf;

        if (!$this->sqlitehelper->ready()) return;

        // get general article info
        $sql = "SELECT title, page, mail
                  FROM entries
                 WHERE pid = ?";
        $res = $this->sqlitehelper->getDB()->query($sql,$comment['pid']);
        $entry = $this->sqlitehelper->getDB()->res2row($res,0);

        // prepare mail bodies
        $atext = io_readFile($this->localFN('notifymail'));
        $stext = io_readFile($this->localFN('subscribermail'));
        $title = sprintf($this->getLang('subscr_subject'),$entry['title']);

        $repl = array(
            '@TITLE@'       => $entry['title'],
            '@NAME@'        => $comment['name'],
            '@COMMENT@'     => $comment['text'],
            '@USER@'        => $comment['name'],
            '@MAIL@'        => $comment['mail'],
            '@DATE@'        => dformat(time()),
            '@BROWSER@'     => $_SERVER['HTTP_USER_AGENT'],
            '@IPADDRESS@'   => clientIP(),
            '@HOSTNAME@'    => gethostsbyaddrs(clientIP()),
            '@URL@'         => wl($entry['page'],'',true).($comment['cid'] ? '#comment_'.$comment['cid'] : ''),
            '@DOKUWIKIURL@' => DOKU_URL,
        );

        $atext = str_replace(array_keys($repl),array_values($repl),$atext);
        $stext = str_replace(array_keys($repl),array_values($repl),$stext);

        // notify author
        $mails = array_map('trim', explode(',', $conf['notify']));
        $mails[] = $entry['mail'];
        $mails = array_unique(array_filter($mails));
        if (count($mails) > 0) {
            mail_send('', $title, $atext, $conf['mailfrom'], '', implode(',', $mails));
        }

        // finish here when subscriptions disabled
        if(!$this->getConf('comments_subscription')) return;

        // get subscribers
        $sql = "SELECT A.mail as mail, B.key as key
                  FROM subscriptions A, optin B
                 WHERE A.mail = B.mail
                   AND B.optin = 1
                   AND A.pid = ?";
        $res = $this->sqlitehelper->getDB()->query($sql,$comment['pid']);
        $rows = $this->sqlitehelper->getDB()->res2arr($res);
        foreach($rows as $row){
            // ignore commenter herself:
            if($row['mail'] == $comment['mail']) continue;
            // ignore email addresses already notified:
            if(in_array($row['mail'], $mails)) continue;
            mail_send($row['mail'], $title, str_replace('@UNSUBSCRIBE@', wl($entry['page'],array('btngu'=>$row['key']),true), $stext), $conf['mailfrom']);
        }
    }

    /**
     * Send a mail to commenter and let her login
     *
     * @param $mail
     * @param $key
     */
    public function send_optin_mail($mail,$key){
        global $conf;

        $text  = io_readFile($this->localFN('optinmail'));
        $title = sprintf($this->getLang('optin_subject'));

        $repl = array(
            '@TITLE@'       => $conf['title'],
            '@URL@'         => wl('',array('btngo'=>$key),true),
            '@DOKUWIKIURL@' => DOKU_URL,
        );
        $text = str_replace(array_keys($repl),array_values($repl),$text);

        mail_send($mail, $title, $text, $conf['mailfrom']);
    }

    /**
     * Subscribe entry
     *
     * @param string $pid  - entry to subscribe
     * @param string $mail - email of subscriber
     * @param int $optin - set to 1 for immediate optin
     */
    public function subscribe($pid, $mail, $optin = -3) {
        if (!$this->sqlitehelper->ready()) {
            msg('BlogTNG: subscribe fails. (sqlite helper plugin not available)',-1);
            return;
        }

        // add to subscription list
        $sql = "INSERT OR IGNORE INTO subscriptions
                      (pid, mail) VALUES (?,?)";
        $this->sqlitehelper->getDB()->query($sql,$pid,strtolower($mail));

        // add to optin list
        if($optin == 1){
            $sql = "INSERT OR REPLACE INTO optin
                          (mail,optin,key) VALUES (?,?,?)";
            $this->sqlitehelper->getDB()->query($sql,strtolower($mail),$optin,md5(time()));
        }else{
            $sql = "INSERT OR IGNORE INTO optin
                          (mail,optin,key) VALUES (?,?,?)";
            $this->sqlitehelper->getDB()->query($sql,strtolower($mail),$optin,md5(time()));

            // see if we need to send a optin mail
            $sql = "SELECT optin, key FROM optin WHERE mail = ?";
            $res = $this->sqlitehelper->getDB()->query($sql,strtolower($mail));
            $row = $this->sqlitehelper->getDB()->res2row($res,0);
            if($row['optin'] < 0){
                $this->send_optin_mail($mail,$row['key']);
                $sql = "UPDATE optin SET optin = optin+1 WHERE mail = ?";
                $this->sqlitehelper->getDB()->query($sql,strtolower($mail));
            }
        }


    }

    /**
     * Unsubscribe by key
     * 
     * @param $pid
     * @param $key
     */
    public function unsubscribe_by_key($pid, $key) {
        if (!$this->sqlitehelper->ready()) {
            msg('BlogTNG: unsubscribe by key fails. (sqlite helper plugin not available)',-1);
            return;
        }
        $sql = 'SELECT mail FROM optin WHERE key = ?';
        $res = $this->sqlitehelper->getDB()->query($sql, $key);
        $row = $this->sqlitehelper->getDB()->res2row($res);
        if (!$row) {
            msg($this->getLang('unsubscribe_err_key'), -1);
            return;
        }

        $this->unsubscribe($pid, $row['mail']);
    }

    /**
     * Unsubscribe entry
     *
     * @param $pid
     * @param $mail
     */
    public function unsubscribe($pid, $mail) {
        if (!$this->sqlitehelper->ready()) {
            msg($this->getlang('unsubscribe_err_other') . ' (sqlite helper plugin not available)', -1);
            return;
        }
        $sql = 'DELETE FROM subscriptions WHERE pid = ? AND mail = ?';
        $res = $this->sqlitehelper->getDB()->query($sql, $pid, $mail);
        $upd = $this->sqlitehelper->getDB()->countChanges($res);

        if ($upd) {
            msg($this->getLang('unsubscribe_ok'), 1);
        } else {
            msg($this->getlang('unsubscribe_err_other'), -1);
        }
    }

    /**
     * Opt in
     *
     * @param $key
     */
    public function optin($key) {
        if (!$this->sqlitehelper->ready()) {
            msg($this->getlang('optin_err') . ' (sqlite helper plugin not available)', -1);
            return;
        }

        $sql = 'UPDATE optin SET optin = 1 WHERE key = ?';
        $res = $this->sqlitehelper->getDB()->query($sql,$key);
        $upd = $this->sqlitehelper->getDB()->countChanges($res);

        if($upd){
            msg($this->getLang('optin_ok'),1);
        }else{
            msg($this->getLang('optin_err'),-1);
        }
    }

    /**
     * Enable discussion
     *
     * @param $pid
     */
    public function enable($pid) {
    }

    /**
     * Disable discussion
     *
     * @param $pid
     */
    public function disable($pid) {
    }

    /**
     * Close discussion
     *
     * @param $pid
     */
    public function close($pid) {
    }

    /**
     * Prints the comment form
     *
     * FIXME
     *  allow comments only for registered users
     *  add toolbar
     *
     * @param $page
     * @param $pid
     * @param $tplname
     */
    public function tpl_form($page, $pid, $tplname) {
        global $BLOGTNG;

        $form = new Doku_Form(array('id'=>'blogtng__comment_form','action'=>wl($page).'#blogtng__comment_form','data-tplname'=>$tplname));
        $form->addHidden('pid', $pid);
        $form->addHidden('id', $page);
        $form->addHidden('btng[comment][source]', 'comment');

        foreach(array('name', 'mail', 'web') as $field) {
            $attr = ($BLOGTNG['comment_submit_errors'][$field]) ?  array('class' => 'edit error') : array();

            if($field == 'web' && !$this->getConf('comments_allow_web')) {
                continue;
            } else {
                $form->addElement(
                        form_makeTextField(
                        'btng[comment][' . $field . ']',
                        $BLOGTNG['comment'][$field],
                        $this->getLang('comment_'.$field),
                        'blogtng__comment_' . $field,
                        'edit block',
                        $attr)
                );
            }
        }

        $form->addElement(form_makeOpenTag('div', array('class' => 'blogtng__toolbar')));
        $form->addElement(form_makeCloseTag('div'));

        if($BLOGTNG['comment_submit_errors']['text']) {
            $form->addElement(form_makeWikiText($BLOGTNG['comment']['text'], array('class' => 'edit error')));
        } else {
            $form->addElement(form_makeWikiText($BLOGTNG['comment']['text']));
        }

        //add captcha if available
        /** @var helper_plugin_captcha $helper */
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled()){
            $form->addElement($helper->getHTML());
        }

        $form->addElement(form_makeButton('submit', 'comment_preview', $this->getLang('comment_preview'), array('class' => 'button', 'id' => 'blogtng__preview_submit')));
        $form->addElement(form_makeButton('submit', 'comment_submit', $this->getLang('comment_submit'), array('class' => 'button', 'id' => 'blogtng__comment_submit')));

        if($this->getConf('comments_subscription')){
            $form->addElement(form_makeCheckboxField('blogtng[subscribe]', 1, $this->getLang('comment_subscribe')));
        }

        print '<div id="blogtng__comment_form_wrap">'.DOKU_LF;
        $form->printForm();
        if(isset($BLOGTNG['comment_action']) && ($BLOGTNG['comment_action'] == 'preview') && empty($BLOGTNG['comment_submit_errors'])) {
            print '<div id="blogtng__comment_preview">' . DOKU_LF;
            $comment = new blogtng_comment();
            $comment->data = $BLOGTNG['comment'];
            $comment->data['cid'] = 'preview';
            $comment->output($tplname);
            print '</div>' . DOKU_LF;
        }
        print '</div>'.DOKU_LF;
    }

    /**
     * Print the number of comments
     *
     * @param string $fmt_zero_comments - text for no comment
     * @param string $fmt_one_comments - text for 1 comment
     * @param string $fmt_comments - text for 1+ comment
     * @param array  $types - a list of wanted comment sources (empty for all)
     * @return bool
     */
    public function tpl_count($fmt_zero_comments='', $fmt_one_comments='', $fmt_comments='', $types=null) {
        if(!$this->pid) return;

        if(!$fmt_zero_comments)
            $fmt_zero_comments = $this->getLang('0comments');
        if(!$fmt_one_comments)
            $fmt_one_comments = $this->getLang('1comments');
        if(!$fmt_comments)
            $fmt_comments = $this->getLang('Xcomments');

        $count = $this->get_count($types);

        switch($count) {
            case 0:
                printf($fmt_zero_comments, $count);
                break;
            case 1:
                printf($fmt_one_comments, $count);
                break;
            default:
                printf($fmt_comments, $count);
                break;
        }
    }

    /**
     * Print the comments
     */
    public function tpl_comments($name,$types=null) {
        $pid = $this->pid;
        if(!$pid) return;

        if (!$this->sqlitehelper->ready()) return;

        $sql = 'SELECT *
                  FROM comments
                 WHERE pid = ?';
        $args = array();
        $args[] = $pid;
        if(is_array($types)){
            $qs = array();
            foreach($types as $type){
                $args[] = $type;
                $qs[]   = '?';
            }
            $sql .= ' AND type IN ('.join(',',$qs).')';
        }
        $sql .= ' ORDER BY created ASC';
        $res = $this->sqlitehelper->getDB()->query($sql,$args);
        $res = $this->sqlitehelper->getDB()->res2arr($res);

        $comment = new blogtng_comment();
        foreach($res as $row){
            $comment->init($row);
            $comment->output($name);
        }
    }

    /**
     * Displays a list of recent comments
     *
     * @param $conf
     * @return string
     */
    public function xhtml_recentcomments($conf){
        ob_start();
        if($conf['listwrap']) echo '<ul class="blogtng_recentcomments">';
        $this->tpl_recentcomments($conf['tpl'],$conf['limit'],$conf['blog'],$conf['type']);
        if($conf['listwrap']) echo '</ul>';
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Display a list of recent comments
     */
    public function tpl_recentcomments($tpl='default',$num=5,$blogs=array('default'),$types=array()){
        // check template
        $tpl = helper_plugin_blogtng_tools::getTplFile($tpl, 'recentcomments');
        if($tpl === false){
            return false;
        }

        if(!$this->sqlitehelper->ready()) return false;

        // prepare and execute query
        if(count($types)){
            $types  = $this->sqlitehelper->getDB()->quote_and_join($types,',');
            $tquery = " AND B.source IN ($types) ";
        }else{
            $tquery = "";
        }
        $blog_query = '(A.blog = '.
                       $this->sqlitehelper->getDB()->quote_and_join($blogs,
                                                           ' OR A.blog = ').')';
        $query = "SELECT A.pid as pid, page, title, cid
                    FROM entries A, comments B
                   WHERE $blog_query
                     AND A.pid = B.pid
                     $tquery
                     AND B.status = 'visible'
                     AND GETACCESSLEVEL(A.page) >= ".AUTH_READ."
                ORDER BY B.created DESC
                   LIMIT ".(int) $num;

        $res = $this->sqlitehelper->getDB()->query($query);

        if(!$this->sqlitehelper->getDB()->res2count($res)) return false; // no results found
        $res = $this->sqlitehelper->getDB()->res2arr($res);

        // print all hits using the template
        foreach($res as $row){
            /** @var helper_plugin_blogtng_entry $entry */
            $entry   = plugin_load('helper', 'blogtng_entry');
            $entry->load_by_pid($row['pid']);
            $comment = $this->comment_by_cid($row['cid']);
            include($tpl);
        }
        return true;
    }

}

/**
 * Simple wrapper class for a single comment object
 */
class blogtng_comment{
    var $data;
    var $num;

    /**
     * Resets the internal data with a given row
     */
    public function blogtng_comment(){
        $this->tools = new helper_plugin_blogtng_tools();
    }

    /**
     * @param $row array row of table 'commments'
     */
    public function init($row){
        $this->data = $row;
    }

    /**
     * Render a comment using the templates comments file.
     * 
     * @param string $name of template
     * @return bool
     */
    public function output($name){
        global $INFO;
        $name = preg_replace('/[^a-zA-Z_\-]+/','',$name);
        $tpl = $this->tools->getTplFile($name,'comments');
        if($tpl === false){
            return false;
        }

        $comment = $this;
        if($comment->data['status'] == 'visible' || ($comment->data['status'] == 'hidden' && $INFO['isadmin'])) {
            $comment->num++;
            include($tpl);
        }
        return true;
    }

    /**
     * Get translated string for @$name
     * 
     * @param   string  $name     id of the string to be retrieved
     * @return  string  string in appropriate language or english if not available
     */
    public function getLang($name){
        return $this->tools->getLang($name);
    }

    /**
     * Render the text/content of a single comment
     * 
     * @param   string  $name     id of the string to be retrieved
     * @return  string  string in appropriate language or english if not available
     */
    public function tpl_comment(){
        //FIXME add caching

        $inst = p_get_instructions($this->data['text']);
        echo p_render('blogtng_comment',$inst,$info);
    }

    /**
     * Render the cid of a single comment
     */
    public function tpl_cid(){
        echo $this->data['cid'];
    }

    /**
     * Render the number of a comment.
     * 
     * @param bool   $link  whether wrapped with link element
     * @param string $fmt   format of number
     * @param string $title null or alternative title
     */
    public function tpl_number($link = true, $fmt = '%d', $title = null) {
        if($title === null) $title = sprintf($fmt, $this->num);

        if($link) echo '<a href="#comment_' . $this->data['cid'] . '" class="blogtng_num">';
        echo $title;
        if($link) echo '</a>';
    }

    /**
     * Render the hcard/userdata of a single comment
     */
    public function tpl_hcard(){
        echo '<div class="vcard">';
        if($this->data['web']){
            echo '<a href="'.hsc($this->data['web']).'" class="fn nickname">';
            echo hsc($this->data['name']);
            echo '</a>';
        }else{
            echo '<span class="fn nickname">';
            echo hsc($this->data['name']);
            echo '</span>';
        }
        echo '</div>';
    }

    /**
     * Render the name of a single comment
     */
    public function tpl_name(){
        echo hsc($this->data['name']);
    }

    /**
     * Render the type of a single comment
     */
    public function tpl_type(){
        echo hsc($this->data['type']);
    }

    /**
     * Render the mail of a single comment
     */
    public function tpl_mail(){
        echo hsc($this->data['mail']);
    }

    /**
     * Render the web address of a single comment
     */
    public function tpl_web(){
        echo hsc($this->data['web']);
    }

    /**
     * Render the creation date of a single comment
     * 
     * @param string $fmt date format, empty string default to $conf['dformat']
     */
    public function tpl_created($fmt=''){
        echo hsc(dformat($this->data['created'],$fmt));
    }

    /**
     * Render the status of a single comment
     */
    public function tpl_status() {
        echo $this->data['status'];
    }

    /**
     * Render the avatar of a single comment
     * 
     * @param int $w avatar width
     * @param int $h avatar height
     * @param bool $return whether the url is returned or printed
     * @return void|string url of avatar
     */
    public function tpl_avatar($w=0,$h=0,$return=false){
        global $conf;
        $img = '';
        if($this->data['avatar']) {
            $img = $this->data['avatar'];
            //FIXME add hook for additional methods
        } elseif ($this->data['mail']) {
            $dfl = $conf['plugin']['blogtng']['comments_gravatar_default'];
            if(!isset($dfl) || $dfl == 'blank') $dfl = DOKU_URL . 'lib/images/blank.gif';

            $img = 'http://gravatar.com/avatar.php'
                 . '?gravatar_id=' . md5($this->data['mail'])
                 . '&size=' . $w
                 . '&rating=' . $conf['plugin']['blogtng']['comments_gravatar_rating']
                 . '&default='.rawurlencode($dfl)
                 . '&.png';
        } elseif ($this->data['web']){
            $img = 'http://getfavicon.appspot.com/'.rawurlencode($this->data['web']).'?.png';
        }


        //use fetch for caching and resizing
        if($img){
            $img = ml($img,array('w'=>$w,'h'=>$h,'cache'=>'recache'));
        }
        if($return) {
            return $img;
        } else {
            print $img;
        }
    }
}
// vim:ts=4:sw=4:et:
