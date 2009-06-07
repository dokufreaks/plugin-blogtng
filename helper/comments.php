<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class helper_plugin_blogtng_comments extends DokuWiki_Plugin {

    var $sqlitehelper = null;

    var $comments = array();
    var $pid;

    /**
     * Constructor, loads the sqlite helper plugin
     */
    function helper_plugin_blogtng_comments() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Return some info
     */
    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    /**
     * Load comments for specified pid
     */
    function load($pid) {
        $this->pid = trim($pid);
        //$query = 'SELECT FIXME FROM comments WHERE pid = ?';

        //$resid = $this->sqlitehelper->query($query, $pid);
        //if ($resid === false) {
        //    msg('blogtng plugin: failed to load comments!', -1);
        //    $this->comments = array();
        //}
        //if (sqlite_num_rows($resid) == 0) {
        //    $this->comments = array();
        //}

        //$this->comments = $this->sqlitehelper->res2arr($resid);
    }

    function comment_by_cid($cid) {
        $query = 'SELECT pid, source, name, mail, web, avatar, created, text, status FROM comments WHERE cid = ?';
        $resid = $this->sqlitehelper->query($query, $cid);
        if ($resid === false) {
            return false;
        }
        if (sqlite_num_rows($resid) == 0) {
            return null;
        }
        $result = $this->sqlitehelper->res2arr($resid);

        $comment = new blogtng_comment();
        $comment->init($result[0]);
        return $comment;
    }

    /**
     * Get comment count
     */
    function get_count($types=null) {
        $pid = $this->pid;

        $sql = 'SELECT COUNT(pid) as val
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
        $res = $this->sqlitehelper->query($sql,$args);
        $res = $this->sqlitehelper->res2row($res,0);
        return $res['val'];
    }

    /**
     * Save comment
     *
     * FIXME escape stuff
     */
    function save($comment) {
        $query = 'INSERT INTO comments (pid, source, name, mail, web, avatar, created, text, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $comment['status']  = ($this->getconf('moderate_comments')) ? 'hidden' : 'visible';
        $comment['created'] = time();
        $comment['avatar']  = ''; // FIXME create avatar using a helper function
        $this->sqlitehelper->query($query,
            $comment['pid'],
            $comment['source'],
            $comment['name'],
            $comment['mail'],
            $comment['web'],
            $comment['avatar'],
            $comment['created'],
            $comment['text'],
            $comment['status']
        );
    }

    /**
     * Delete comment
     */
    function delete($cid) {
        $query = 'FIXME';
        $this->sqlitehelper->query($query, $cid);
    }

    /**
     * Delete all comments for an entry
     */
    function delete_all($pid) {
        $sql = "DELETE FROM comments WHERE pid = ?";
        return (bool) $this->sqlitehelper->query($sql,$pid);
    }

    /**
     * Moderate comment
     */
    function moderate($cid, $status) {
        $query = 'FIXME';
        $this->sqlitehelper->query($query, $cid, $status);
    }

    /**
     * Subscribe entry
     */
    function subscribe($pid, $mail) {
    }

    /**
     * Unsubscribe entry
     */
    function unsubscribe($pid, $mail) {
    }

    /**
     * Opt in
     */
    function opt_in($mail) {
    }

    /**
     * Opt out
     */
    function opt_out($mail) {
    }

    /**
     * Enable discussion
     */
    function enable($pid) {
    }

    /**
     * Disable discussion
     */
    function disable($pid) {
    }

    /**
     * Close discussion
     */
    function close($pid) {
    }

    /**
     * Prints the comment form
     *
     * FIXME
     *  allow comments only for registered users
     *  add toolbar
     */
    function tpl_form($page, $pid) {
        global $INFO;
        global $BLOGTNG;

        $form = new DOKU_Form('blogtng__comment_form',wl($page).'#blogtng__comment_form');
        $form->addHidden('pid', $pid);
        $form->addHidden('id', $page);
        $form->addHidden('blogtng[comment_source]', 'comment');

        foreach(array('name', 'mail', 'web') as $field) {
            $attr = ($BLOGTNG['comment_submit_errors'][$field]) ?  array('class' => 'edit error') : array();

            if(isset($_SERVER['REMOTE_USER']) && ($field == 'name' || $field == 'mail')) {
                $form->addHidden('blogtng[comment_' . $field . ']', $BLOGTNG['comment'][$field]);
            } elseif($field == 'web' && !$this->getConf('comments_allow_web')) {
                continue;
            } else {
                $form->addElement(
                        form_makeTextField(
                        'blogtng[comment_' . $field . ']',
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
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled()){
            $form->addElement($helper->getHTML());
        }

        $form->addElement(form_makeButton('submit', 'comment_preview', $this->getLang('comment_preview'), array('class' => 'button', 'id' => 'blogtng__preview_submit')));
        $form->addElement(form_makeButton('submit', 'comment_submit', $this->getLang('comment_submit'), array('class' => 'button', 'id' => 'blogtng__comment_submit')));

        if($this->getConf('comments_subscription')){
            $form->addElement(form_makeCheckboxField('blogtng[subscribe]', 0, $this->getLang('comment_subscribe')));
        }

        print '<div id="blogtng__comment_form_wrap">'.DOKU_LF;
        $form->printForm();
        if($BLOGTNG['comment_action'] == 'preview' && empty($BLOGTNG['comment_submit_errors'])) {
            print '<div id="blogtng__comment_preview">' . DOKU_LF;
            $comment = new blogtng_comment();
            $comment->data = $BLOGTNG['comment'];
            $comment->data['cid'] = 'preview';
            $comment->output('default');
            print '</div>' . DOKU_LF;
        }
        print '</div>'.DOKU_LF;
    }

    /**
     * Print the number of comments
     */
    function tpl_count($fmt_zero_comments='', $fmt_one_comment='', $fmt_comments='', $types=null) {
        if(!$this->pid) return false;

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
                printf($fmt_one_comment, $count);
                break;
            default:
                printf($fmt_comments, $count);
                break;
        }
    }

    /**
     * Print the comemnts
     */
    function tpl_comments($name,$types=null) {
        $pid = $this->pid;
        if(!$pid) return false;

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
        $res = $this->sqlitehelper->query($sql,$args);
        $res = $this->sqlitehelper->res2arr($res);

        $comment = new blogtng_comment();
        foreach($res as $row){
            $comment->init($row);
            $comment->output($name);
        }
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
    function init($row){
        $this->num++;
        $this->data = $row;

    }

    function output($name){
        $name = preg_replace('/[^a-zA-Z_\-]+/','',$name);
        $tpl = DOKU_PLUGIN . 'blogtng/tpl/' . $name . '_comments.php';
        if(file_exists($tpl)) {
            $comment = $this;
            include($tpl);
        } else {
            msg('blogtng plugin: template ' . $tpl . ' does not exist!', -1);
        }

    }

    function tpl_comment(){
        //FIXME add caching

        $inst = p_get_instructions($this->data['text']);
        echo p_render('blogtng_comment',$inst,$info);
    }

    function tpl_cid(){
        echo $this->data['cid'];
    }

    function tpl_number($link=true,$fmt='%d'){
        if($link) echo '<a href="#comment_'.$this->data['cid'].'" class="blogtng_num">';
        printf($fmt,$this->num);
        if($link) echo '</a>';
    }

    function tpl_hcard(){
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

    function tpl_name(){
        echo hsc($this->data['name']);
    }

    function tpl_type(){
        echo hsc($this->data['type']);
    }

    function tpl_mail(){
        echo hsc($this->data['mail']);
    }

    function tpl_web(){
        echo hsc($this->data['web']);
    }

    function tpl_created($fmt=''){
        global $conf;
        if(!$fmt) $fmt = $conf['dformat'];
        echo hsc(strftime($fmt,$this->data['created']));
    }

    function tpl_avatar($w=0,$h=0){
        global $conf;
        $img = '';
        if($this->data['avatar']) {
            $img = $this->data['avatar'];
            //FIXME add hook for additional methods
        } elseif ($this->data['mail']) {
            $img = 'http://gravatar.com/avatar.php'
                 . '?gravatar_id=' . md5($this->data['mail'])
                 . '&size=' . $w
                 . '&rating=' . $conf['plugin']['blogtng']['comments_gravatar_rating']
                 . '&default=' . DOKU_URL . 'lib/images/blank.gif'
                 . '&.png';
        }

        // FIXME config options for gravatar

        //use fetch for caching and resizing
        if($img){
            $img = ml($img,array('w'=>$w,'h'=>$h,'cache'=>'recache'));
        }
        print $img;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
