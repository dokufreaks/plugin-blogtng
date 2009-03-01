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
     */
    function save($pid, $comment) {
        $query = 'FIXME';
        $this->sqlitehelper->query($query, $pid);
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
     *  localization
     *  allow comments only for registered users
     *  add toolbar
     */
    function tpl_form($page, $pid) {
        global $INFO;

        $comment['text'] = ($_REQUEST['blogtng_comment_text']) ? hsc($_REQUEST['blogtng_comment_text']) : '';
        $comment['url']  = ($_REQUEST['blogtng_comment_url'])  ? hsc($_REQUEST['blogtng_comment_url'])  : '';

        if(isset($_SERVER['REMOTE_USER'])) {
            $comment['name'] = $INFO['userinfo']['name'];
            $comment['mail'] = $INFO['userinfo']['mail'];
        } else {
            $comment['name'] = ($_REQUEST['blogtng_comment_name']) ? hsc($_REQUEST['blogtng_comment_name']) : '';
            $comment['mail'] = ($_REQUEST['blogtng_comment_mail']) ? hsc($_REQUEST['blogtng_comment_mail']) : '';
        }

        $form = new DOKU_Form('blogtng__comment_form');
        $form->addHidden('pid', $pid);
        $form->addHidden('id', $page);
        $form->addHidden('blogtng[comment_source]', 'comment');

        if(isset($_SERVER['REMOTE_USER'])) {
            $form->addHidden('blogtng[comment_name]', $comment['name']);
            $form->addHidden('blogtng[bomment_mail]', $comment['mail']);
        } else {
            $form->addElement(form_makeTextField('blogtng[comment_name]', $comment['name'], 'Name', 'blogtng__comment_name', 'edit block'));
            $form->addElement(form_makeTextField('blogtng[comment_mail]', $comment['mail'], 'Mail', 'blogtng__comment_mail', 'edit block'));
        }

        $form->addElement(form_makeTextField('blogtng[comment_url]', $comment['url'], 'URL', 'blogtng__comment_url', 'edit block'));
        $form->addElement(form_makeOpenTag('div', array('class' => 'blogtng__toolbar')));
        $form->addElement(form_makeCloseTag('div'));
        $form->addElement(form_makeWikiText($comment['text']));
        $form->addElement(form_makeButton('submit', 'comment_preview', 'preview', array('class' => 'button', 'id' => 'blogtng__comment_preview')));
        $form->addElement(form_makeButton('submit', 'comment_submit', 'comment', array('class' => 'button', 'id' => 'blogtng__comment_submit')));
        $form->addElement(form_makeCheckboxField('blogtng[subscribe]', 0, 'subscribe'));

        print '<div class="blogtng_commentform">' . DOKU_LF;
        $form->printForm();
        print '</div>' . DOKU_LF;
    }

    /**
     * Print the number of comments
     */
    function tpl_count($fmt_zero_comments='', $fmt_one_comment='', $fmt_comments='', $types=null) {
        if(!$this->pid) return false;
        $count = $this->get_count($types);

        //FIXME add localized defaults

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
        //FIXME implement renderer and handle references

        echo hsc($this->data['text']);
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


}
// vim:ts=4:sw=4:et:enc=utf-8:
