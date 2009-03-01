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
        global $BLOGTNG;

        $form = new DOKU_Form('blogtng__comment_form');
        $form->addHidden('pid', $pid);
        $form->addHidden('id', $page);
        $form->addHidden('blogtng[comment_source]', 'comment');

        foreach(array('name', 'mail', 'web') as $field) {
            $attr = ($BLOGTNG['comment_submit_errors'][$field]) ?  array('class' => 'edit error') : array();

            if(isset($_SERVER['REMOTE_USER']) && ($field == 'name' || $field == 'mail')) {
                $form->addHidden('blogtng[comment_' . $field . ']', $BLOGTNG['comment'][$field]);
            } elseif($field == 'web' && !$this->getConf('allow_web')) {
                continue;
            } else {
                $form->addElement(
                        form_makeTextField(
                        'blogtng[comment_' . $field . ']',
                        $BLOGTNG['comment'][$field], 
                        $field, // FIXME localize
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
    function tpl_comments($types=null) {

        for($i=0;$i<$this->count;$i++) {
            $html = '<div class="blogtng_comment">' . DOKU_LF
                  . '</div>' . DOKU_LF;
            print $html;
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
