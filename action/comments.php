<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blogtng_comments extends DokuWiki_Action_Plugin{

    var $commenthelper = null;
    var $tools = null;

    function action_plugin_blogtng_comments() {
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
        $this->tools =& plugin_load('helper', 'blogtng_tools');
    }

    function register(&$controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess', array());
    }

    /**
     * Handle the preprocess event
     *
     * Takes care of handling all the post input from creating
     * comments and saves them. Also handles optin and unsubscribe
     * actions.
     */
    function handle_act_preprocess(&$event, $param) {
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
        $comment['text']   = isset($_REQUEST['wikitext']) ? $_REQUEST['wikitext'] : null; // FIXME clean text
        $comment['pid']    = isset($_REQUEST['pid'])      ? $_REQUEST['pid']      : null;
        $comment['page']   = isset($_REQUEST['id'])       ? $_REQUEST['id']       : null;
        $comment['subscribe'] = isset($_REQUEST['blogtng']['subscribe']) ? $_REQUEST['blogtng']['subscribe'] : null;
        $comment['ip'] = clientIP(true);

        $BLOGTNG['comment'] = $comment;

        if(is_array($event->data) && (isset($event->data['comment_submit']) || isset($event->data['comment_preview']))) {

            if(isset($event->data['comment_submit']))  $BLOGTNG['comment_action'] = 'submit';
            if(isset($event->data['comment_preview'])) $BLOGTNG['comment_action'] = 'preview';


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
                $helper = null;
                if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
                if(!is_null($helper) && $helper->isEnabled()){
                    $captchaok = $helper->check();
                }
            }

            // return on errors
            if(!empty($BLOGTNG['comment_submit_errors']) || !$captchaok) {
                $event->data = 'show';
                $_SERVER['REQUEST_METHOD'] = 'get'; //FIXME hack to avoid redirect
                return false;
            }

            if($BLOGTNG['comment_action'] == 'submit') {
                // save comment and redirect FIXME cid
                $this->commenthelper->save($comment);
                act_redirect($comment['page'], 'show');
            } elseif($BLOGTNG['comment_action'] == 'preview') {
                $event->data = 'show';
                $_SERVER['REQUEST_METHOD'] = 'get'; //FIXME hack to avoid redirect
                return false;
            }
        } else {
            return true;
        }
    }
}
// vim:ts=4:sw=4:et:
