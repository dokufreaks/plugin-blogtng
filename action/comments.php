<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_blogtng_comments
 */
class action_plugin_blogtng_comments extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_comments */
    var $commenthelper = null;
    /** @var helper_plugin_blogtng_tools */
    var $tools = null;

    /**
     * Constructor
     */
    function __construct() {
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
        $this->tools = plugin_load('helper', 'blogtng_tools');

    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess', array());
    }

    /**
     * Handle the preprocess event
     *
     * Takes care of handling all the post input from creating
     * comments and saves them. Also handles optin and unsubscribe
     * actions.
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return bool
     */
    function handle_act_preprocess(Doku_Event $event, $param) {
        global $INFO, $ID;

        // optin
        if (isset($_REQUEST['btngo'])) {
            $this->commenthelper->optin($_REQUEST['btngo']);
        }

        // unsubscribe
        if (isset($_REQUEST['btngu'])) {
            $this->commenthelper->unsubscribe_by_key(md5($ID), $_REQUEST['btngu']);
        }

        global $BLOGTNG;
        $BLOGTNG = array();

        // prepare data for comment form
        $comment = array();
        $comment['source'] = $this->tools->getParam('comment/source');
        $comment['name']   = (($commentname = $this->tools->getParam('comment/name'))) ? $commentname : $INFO['userinfo']['name'];
        $comment['mail']   = (($commentmail = $this->tools->getParam('comment/mail'))) ? $commentmail : $INFO['userinfo']['mail'];
        $comment['web']    = (($commentweb = $this->tools->getParam('comment/web'))) ? $commentweb : '';
        $comment['text']   = isset($_REQUEST['wikitext']) ? cleanText($_REQUEST['wikitext']) : null;
        $comment['pid']    = isset($_REQUEST['pid'])      ? $_REQUEST['pid']      : null;
        $comment['page']   = isset($_REQUEST['id'])       ? $_REQUEST['id']       : null;
        $comment['subscribe'] = isset($_REQUEST['blogtng']['subscribe']) ? $_REQUEST['blogtng']['subscribe'] : null;
        $comment['ip'] = clientIP(true);

        //add "http(s)://" to website
        if (!preg_match('/^http/',$comment['web']) && $comment['web'] != '') {
            $comment['web'] = 'http://'.$comment['web'];
        }
        $BLOGTNG['comment'] = $comment;

        $action = act_clean($event->data);
        if($action == 'comment_submit' || $action == 'comment_preview') {

            if($action == 'comment_submit') {
                $BLOGTNG['comment_action'] = 'submit';
            }
            else if($action == 'comment_preview') {
                $BLOGTNG['comment_action'] = 'preview';
            }

            // check for empty fields
            $BLOGTNG['comment_submit_errors'] = array();
            foreach(array('name', 'mail', 'text') as $field) {
                if(empty($comment[$field])) {
                    $BLOGTNG['comment_submit_errors'][$field] = true;
                }
            }

            // check CAPTCHA if available (on submit only)
            $captchaok = true;
            if($BLOGTNG['comment_action'] == 'submit'){
                /** @var helper_plugin_captcha $helper */
                $helper = null;
                if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
                if(!is_null($helper) && $helper->isEnabled()){
                    $captchaok = $helper->check();
                }
            }

            // return on errors
            if(!empty($BLOGTNG['comment_submit_errors']) || !$captchaok) {
                $event->data = 'show';
                $_SERVER['REQUEST_METHOD'] = 'get'; //hack to avoid redirect
                return false;
            }

            if($BLOGTNG['comment_action'] == 'submit') {
                // save comment and redirect FIXME cid
                $this->commenthelper->save($comment);
                $event->data = 'redirect';
                return false;
            } elseif($BLOGTNG['comment_action'] == 'preview') {
                $event->data = 'show';
                $_SERVER['REQUEST_METHOD'] = 'get'; // hack to avoid redirect
                return false;
            }
        } else {
            return true;
        }
    }
}
// vim:ts=4:sw=4:et:
