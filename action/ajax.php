<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_blogtng_ajax
 */
class action_plugin_blogtng_ajax extends DokuWiki_Action_Plugin{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call', array());
    }

    /**
     * Callback function for event 'AJAX_CALL_UNKNOWN'.
     * 
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     */
    function handle_ajax_call(Doku_Event $event, $param) {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        if($event->data != 'blogtng__comment_preview') return;
        $event->preventDefault();
        $event->stopPropagation();

        require_once DOKU_PLUGIN . 'blogtng/helper/comments.php';
        $comment = new blogtng_comment();

        $comment->data['text']    = $_REQUEST['text'];
        $comment->data['name']    = $_REQUEST['name'];
        $comment->data['mail']    = $_REQUEST['mail'];
        $comment->data['web']     = isset($_REQUEST['web']) ? $_REQUEST['web'] : '';
        $comment->data['cid']     = 'preview';
        $comment->data['created'] = time();
        $comment->data['status']  = 'visible';

        if(!$comment->data['name'] && $_SERVER['REMOTE_USER']){
            if($auth AND $info = $auth->getUserData($_SERVER['REMOTE_USER'])) {
                $comment->data['name'] = $info['name'];
                $comment->data['mail'] = $info['mail'];
            }
        }

        $comment->output($_REQUEST['tplname']);
    }
}
// vim:ts=4:sw=4:et:
