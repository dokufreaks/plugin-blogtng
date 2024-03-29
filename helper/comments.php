<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

use dokuwiki\Form\Form;
use dokuwiki\plugin\blogtng\entities\Comment;

/**
 * Class helper_plugin_blogtng_comments
 */
class helper_plugin_blogtng_comments extends DokuWiki_Plugin {

    /**
     * @var helper_plugin_blogtng_sqlite
     */
    private $sqlitehelper;

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
     * Select comment by cid and return it as a Comment. The
     * function returns null if the database query fails or if the query result is empty.
     *
     * @param string $cid The cid
     * @return Comment|null
     */
    public function comment_by_cid($cid) {

        $query = 'SELECT cid, pid, source, name, mail, web, avatar, created, text, status
                  FROM comments
                  WHERE cid = ?';
        $resid = $this->sqlitehelper->getDB()->query($query, $cid);
        if ($resid === false) {
            return null;
        }
        if ($this->sqlitehelper->getDB()->res2count($resid) == 0) {
            return null;
        }
        $result = $this->sqlitehelper->getDB()->res2arr($resid);

        return new Comment($result[0]);
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
     * @param Comment $comment
     */
    public function save($comment) {
        if (!$this->sqlitehelper->ready()) {
            msg('BlogTNG: no sqlite helper plugin available', -1);
            return;
        }

        if (!empty($comment->getCid())) {
            // Doing an update
            $query = 'UPDATE comments SET pid=?, source=?, name=?, mail=?,
                      web=?, avatar=?, created=?, text=?, status=?
                      WHERE cid=?';
            $this->sqlitehelper->getDB()->query($query,
                $comment->getPid(),
                $comment->getSource(),
                $comment->getName(),
                $comment->getMail(),
                $comment->getWeb(),
                $comment->getAvatar(),
                $comment->getCreated(),
                $comment->getText(),
                $comment->getStatus(),
                $comment->getCid()
            );
            return;
        }

        // Doing an insert
        /** @var helper_plugin_blogtng_entry $entry */
        $entry = plugin_load('helper', 'blogtng_entry');
        $entry->load_by_pid($comment->getPid());
        if ($entry->entry['commentstatus'] !== 'enabled') {
            return;
        }

        $query =
            'INSERT OR IGNORE INTO comments (
                pid, source, name, mail, web, avatar, created, text, status, ip
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )';
        $comment->setStatus($this->getconf('moderate_comments') ? 'hidden' : 'visible');

        if(!empty($comment->getCreated())) {
            $comment->setCreated(time());
        }

        $comment->setAvatar(''); // FIXME create avatar using a helper function

        $this->sqlitehelper->getDB()->query($query,
            $comment->getPid(),
            $comment->getSource(),
            $comment->getName(),
            $comment->getMail(),
            $comment->getWeb(),
            $comment->getAvatar(),
            $comment->getCreated(),
            $comment->getText(),
            $comment->getStatus(),
            $comment->getIp()
        );

        //retrieve cid of this comment
        $sql = "SELECT cid
                  FROM comments
                 WHERE pid = ?
                   AND created = ?
                   AND mail =?
                 LIMIT 1";
        $res = $this->sqlitehelper->getDB()->query(
            $sql,
            $comment->getPid(),
            $comment->getCreated(),
            $comment->getMail()
        );
        $cid = $this->sqlitehelper->getDB()->res2single($res);
        $comment->setCid($cid === false ? 0 : $cid);


        // handle subscriptions
        if($this->getConf('comments_subscription')) {
            if($comment->getSubscribe()) {
                $this->subscribe($comment->getPid(),$comment->getMail());
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
        global $conf, $INPUT;

        if (!$this->sqlitehelper->ready()) return;

        // get general article info
        $sql = "SELECT title, page, mail
                  FROM entries
                 WHERE pid = ?";
        $res = $this->sqlitehelper->getDB()->query($sql, $comment->getPid());
        $entry = $this->sqlitehelper->getDB()->res2row($res,0);

        // prepare mail bodies
        $atext = io_readFile($this->localFN('notifymail'));
        $stext = io_readFile($this->localFN('subscribermail'));
        $title = sprintf($this->getLang('subscr_subject'),$entry['title']);

        $repl = array(
            'TITLE'       => $entry['title'],
            'NAME'        => $comment->getName(),
            'COMMENT'     => $comment->getText(),
            'USER'        => $comment->getName(),
            'MAIL'        => $comment->getMail(),
            'DATE'        => dformat(time()),
            'BROWSER'     => $INPUT->server->str('HTTP_USER_AGENT'),
            'IPADDRESS'   => clientIP(),
            'HOSTNAME'    => gethostsbyaddrs(clientIP()),
            'URL'         => wl($entry['page'],'',true).($comment->getCid() ? '#comment_'.$comment->getCid() : ''),
            'DOKUWIKIURL' => DOKU_URL,
        );

        // notify author
        $mails = array_map('trim', explode(',', $conf['notify']));
        $mails[] = $entry['mail'];
        $mails = array_unique(array_filter($mails));
        if (count($mails) > 0) {
            $mail = new Mailer();
            $mail->bcc($mails);
            $mail->subject($title);
            $mail->setBody($atext, $repl);
            $mail->send();
        }

        // finish here when subscriptions disabled
        if(!$this->getConf('comments_subscription')) return;

        // get subscribers
        $sql = "SELECT A.mail as mail, B.key as key
                  FROM subscriptions A, optin B
                 WHERE A.mail = B.mail
                   AND B.optin = 1
                   AND A.pid = ?";
        $res = $this->sqlitehelper->getDB()->query($sql, $comment->Pid());
        $rows = $this->sqlitehelper->getDB()->res2arr($res);
        foreach($rows as $row){
            // ignore commenter herself:
            if($row['mail'] == $comment->getMail()) continue;

            // ignore email addresses already notified:
            if(in_array($row['mail'], $mails)) continue;

            $repl['UNSUBSCRIBE'] = wl($entry['page'], ['btngu' => $row['key']],true);

            $mail = new Mailer();
            $mail->to($row['mail']);
            $mail->subject($title);
            $mail->setBody($stext, $repl);
            $mail->send();
        }
    }

    /**
     * Send a mail to commenter and let her login
     *
     * @param $email
     * @param $key
     */
    public function send_optin_mail($email,$key){
        global $conf;

        $text  = io_readFile($this->localFN('optinmail'));
        $title = $this->getLang('optin_subject');

        $repl = array(
            'TITLE'       => $conf['title'],
            'URL'         => wl('',array('btngo'=>$key),true),
            'DOKUWIKIURL' => DOKU_URL,
        );

        $mail = new Mailer();
        $mail->to($email);
        $mail->subject($title);
        $mail->setBody($text, $repl);
        $mail->send();
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
     * @param string $page
     * @param string $pid
     * @param string $tplname
     */
    public function tpl_form($page, $pid, $tplname) {
        global $BLOGTNG; // set in action_plugin_blogtng_comments::handleCommentSaveAndSubscribeActions()

        /** @var Comment $comment */
        $comment = $BLOGTNG['comment'];

        $form = new Form([
            'id'=>'blogtng__comment_form',
            'action'=>wl($page).'#blogtng__comment_form',
            'data-tplname'=>$tplname
        ]);
        $form->setHiddenField('pid', $pid);
        $form->setHiddenField('id', $page);
        $form->setHiddenField('comment-source', 'comment');

        foreach(array('name', 'mail', 'web') as $field) {

            if($field == 'web' && !$this->getConf('comments_allow_web')) {
                continue;
            } else {
                $functionname = "get{$field}";
                $input = $form->addTextInput('comment-' . $field , $this->getLang('comment_'.$field))
                    ->id('blogtng__comment_' . $field)
                    ->addClass('edit block')
                    ->useInput(false)
                    ->val($comment->$functionname());
                if($BLOGTNG['comment_submit_errors'][$field]){
                    $input->addClass('error'); //old approach was overwrite block with error?
                }
            }
        }
        $form->addTagOpen('div')->addClass('blogtng__toolbar');
        $form->addTagClose('div');

        $textarea = $form->addTextarea('wikitext')
            ->id('wiki__text')
            ->addClass('edit')
            ->attr('rows','6') //previous form added automatically also: cols="80" rows="10">
            ->val($comment->getText());
        if($BLOGTNG['comment_submit_errors']['text']) {
            $textarea->addClass('error');
        }

        //add captcha if available
        /** @var helper_plugin_captcha $captcha */
        $captcha = $this->loadHelper('captcha', false);
        if ($captcha && $captcha->isEnabled()) {
            $form->addHTML($captcha->getHTML());
        }

        $form->addButton('do[comment_preview]', $this->getLang('comment_preview')) //no type submit(default)
            ->addClass('button')
            ->id('blogtng__preview_submit');
        $form->addButton('do[comment_submit]', $this->getLang('comment_submit')) //no type submit(default)
            ->addClass('button')
            ->id('blogtng__comment_submit');

        if($this->getConf('comments_subscription')){
            $form->addCheckbox('comment-subscribe', $this->getLang('comment_subscribe'))
                ->val(1)
                ->useInput(false);
        }

        //start html output
        print '<div id="blogtng__comment_form_wrap">'.DOKU_LF;
        echo $form->toHTML();

        // fallback preview. Normally previewed using AJAX. Only initiate preview if no errors.
        if(isset($BLOGTNG['comment_action']) && $BLOGTNG['comment_action'] == 'preview' && empty($BLOGTNG['comment_submit_errors'])) {
            print '<div id="blogtng__comment_preview">' . DOKU_LF;
            $comment->setCid('preview');
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
     */
    public function tpl_count($fmt_zero_comments='', $fmt_one_comments='', $fmt_comments='', $types=null) {
        if(!$this->pid) return;

        if(!$fmt_zero_comments) {
            $fmt_zero_comments = $this->getLang('0comments');
        }
        if(!$fmt_one_comments) {
            $fmt_one_comments = $this->getLang('1comments');
        }
        if(!$fmt_comments) {
            $fmt_comments = $this->getLang('Xcomments');
        }

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

        $comment = new Comment();
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
        if($conf['listwrap']) {
            echo '<ul class="blogtng_recentcomments">';
        }
        $this->tpl_recentcomments($conf['tpl'],$conf['limit'],$conf['blog'],$conf['type']);
        if($conf['listwrap']) {
            echo '</ul>';
        }
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
