<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blogtng_ajax extends DokuWiki_Action_Plugin{

    var $commenthelper = null;

    function action_plugin_blogtng_comments() {
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
    }

    function register(&$controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call', array());
    }

    function handle_ajax_call(&$event, $param) {
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
            if($auth) {
                $info = $auth->getUserData($_SERVER['REMOTE_USER']);
                $comment->data['name'] = $info['name'];
                $comment->data['mail'] = $info['mail'];
            }
            // FIXME ???
            $comment->data['name'] = $_SERVER['REMOTE_USER'];
        }

        // FIXME this has to be the template of the used blog
        $comment->output('default');
    }
}
// vim:ts=4:sw=4:et:
