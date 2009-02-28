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
    var $count = 10;

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
        $pid = trim($pid);
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
    function get_count($pid) {
        $pid = trim($pid);
        $query = 'FIXME';
        $this->count = $this->sqlitehelper->query($query, $pid);
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
     * Delete all comments
     */
    function delete_all($pid) {
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
     */
    function tpl_form($pid) {
        $comment_text = ($_REQUEST['comment_text']) ? $_REQUEST['comment_text'] : '';
        $form = new DOKU_Form('blogtng__comments_form');
        $form->addElement(startFieldset('FIXME'));
        $form->addHidden('pid', $pid);
        $form->addElement(form_textField('comment_text', $comment_text, '', 'blogtng__comment_text', 'edit'));
        $form->addElement(form_makeButton('preview', 'preview', '', array('class' => 'button edit', 'id' => 'blogtng__comment_preview')));
        $form->addElement(form_makeButton('submit', 'comment', '', array('class' => 'button edit', 'id' => 'blogtng__comment_submit')));
        $form->addElement(closeFieldset());
        $form->printForm();
    }

    /**
     * Print the number of comments
     */
    function tpl_count($fmt_zero_comments, $fmt_one_comment, $fmt_comments) {
        switch($this->count) {
            case 0:
                print sprintf($fmt_zero_comments, $this->count);
                break;
            case 1:
                print sprintf($fmt_one_comment, $this->count);
                break;
            default:
                print sprintf($fmt_comments, $this->count);
                break;
        }
    }

    /**
     * Print the comemnts
     */
    function tpl_comments($author_url='email') {
        for($i=0;$i<$this->count;$i++) {
            $html = '<div class="blogtng_comment">' . DOKU_LF
                  . '</div>' . DOKU_LF;
            print $html;
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
