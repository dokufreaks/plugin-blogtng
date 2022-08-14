<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

use dokuwiki\plugin\blogtng\entities\Comment;

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
        global $INFO, $ID, $INPUT, $BLOGTNG;
        $BLOGTNG = [];

        // optin
        if ($INPUT->has('btngo')) {
            $this->commenthelper->optin($INPUT->str('btngo'));
        }

        // unsubscribe
        if ($INPUT->has('btngu')) {
            $this->commenthelper->unsubscribe_by_key(md5($ID), $INPUT->str('btngu'));
        }


        $comment = new Comment();

        // prepare data for comment form
        $comment->setSource($INPUT->post->str('comment-source')); //from: comment, pingback or trackback
        $name = $INPUT->post->str('comment-name');
        $comment->setName($name ? $name : $INFO['userinfo']['name']);
        $mail = $INPUT->post->str('comment-mail');
        $comment->setMail($mail ? $mail : $INFO['userinfo']['mail']);
        $web = $INPUT->post->str('comment-web');
        //add "http(s)://" to website
        if ($web != '' && !preg_match('/^http/', $web)) {
            $web = 'https://' . $web;
        }
        $comment->setWeb($web ? $web : '');
        if($INPUT->post->has('wikitext')) {
            $text = cleanText($INPUT->post->str('wikitext'));
        } else {
            $text = null;
        }
        $comment->setText($text);
        $comment->setPid($INPUT->post->has('pid') ? $INPUT->post->str('pid') : null);
//        $comment->setPage(isset($_REQUEST['id']) ? $_REQUEST['id'] : null); FIXME seems to be not used...id general
        $comment->setSubscribe($INPUT->post->has('comment-subscribe') ? 1 : null);
        $comment->setIp(clientIP(true));

        // store data for helper::tpl_form()
        $BLOGTNG['comment'] = $comment;

        $action = act_clean($event->data);
        if($action == 'comment_submit' || $action == 'comment_preview') {

            if($action == 'comment_submit') {
                $BLOGTNG['comment_action'] = 'submit';
            } else {
                $BLOGTNG['comment_action'] = 'preview';
            }

            // check for empty fields
            $BLOGTNG['comment_submit_errors'] = array();
            foreach(array('name', 'mail', 'text') as $field) {
                $functionname = "get{$field}";
                if(empty($comment->$functionname())) {
                    $BLOGTNG['comment_submit_errors'][$field] = true;
                } elseif($field == 'mail' && !mail_isvalid($comment->getMail())) {
                    $BLOGTNG['comment_submit_errors'][$field] = true;
                }
            }

            // check CAPTCHA if available (on submit only)
            $captchaok = true;
            if($BLOGTNG['comment_action'] == 'submit'){
                /** @var helper_plugin_captcha $captcha */
                $captcha = $this->loadHelper('captcha', false);
                if ($captcha && $captcha->isEnabled()) {
                    $captchaok = $captcha->check();
                }
            }

            // return to form on errors or if preview
            if(!empty($BLOGTNG['comment_submit_errors']) || !$captchaok || $BLOGTNG['comment_action'] == 'preview') {
                $event->data = 'show';
                $_SERVER['REQUEST_METHOD'] = 'get'; //hack to avoid redirect
                return false;
            }

            // successful submit: save comment and redirect FIXME cid
            $this->commenthelper->save($comment);
            $event->data = 'redirect';
            return false;
        } else {
            return true;
        }
    }
}
// vim:ts=4:sw=4:et:
