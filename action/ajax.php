<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

use dokuwiki\plugin\blogtng\entities\Comment;

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
     * Callback function for event 'AJAX_CALL_UNKNOWN' to return a rendered preview of a comment
     * (which will be shown below the comment input field)
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     */
    function handle_ajax_call(Doku_Event $event, $param) {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth, $INPUT;

        if($event->data != 'blogtng__comment_preview') return;
        $event->preventDefault();
        $event->stopPropagation();

        $comment = new Comment();
        $comment->setText($INPUT->post->str('text'));
        $comment->setName($INPUT->post->str('name'));
        $comment->setMail($INPUT->post->str('mail'));
        $comment->setWeb($INPUT->post->str('web'));
        $comment->setCid('preview');
        $comment->setCreated(time());
        $comment->setStatus('visible');

        if(!$comment->getName() && $INPUT->server->str('REMOTE_USER')){
            if($auth AND $info = $auth->getUserData($INPUT->server->str('REMOTE_USER'))) {
                $comment->setName($info['name']);
                $comment->setMail($info['mail']);
            }
        }

        $comment->output($INPUT->post->str('tplname'));
    }
}
// vim:ts=4:sw=4:et:
