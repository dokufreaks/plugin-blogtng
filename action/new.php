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

class action_plugin_blogtng_new extends DokuWiki_Action_Plugin{

    var $commenthelper = null;

    function __construct() {
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
    }

    function register(&$controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess', array());
    }

    /**
     * Handles input from the newform and redirects to the edit mode
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     * @author Gina Haeussge <osd@foosel.net>
     */
    function handle_act_preprocess(&$event, $param) {
        global $TEXT;
        global $ID;

        if($event->data != 'btngnew') return true;
        $tools =& plugin_load('helper', 'blogtng_tools');
        if(!$tools->getParam('new/title')){
            msg($this->getLang('err_notitle'),-1);
            $event->data = 'show';
            return true;
        }

        $event->preventDefault();
        $new = $tools->mkpostid($tools->getParam('new/format'),$tools->getParam('new/title'));
        if ($ID != $new) {
            send_redirect(wl($new,array('do'=>'btngnew','btng[post][blog]'=>$tools->getParam('post/blog'), 'btng[new][format]'=>$tools->getParam('new/format'), 'btng[new][title]' => $tools->getParam('new/title')),true,'&'));
            return false; //never reached
        } else {
            $TEXT = $this->_prepare_template($new, $tools->getParam('new/title'));
            $event->data = 'preview';
            return false;
        }
    }

    /**
     * Loads the template for a new blog post and does some text replacements
     *
     * @author Gina Haeussge <osd@foosel.net>
     */
    function _prepare_template($id, $title) {
        $tpl = io_readFile(DOKU_PLUGIN . 'blogtng/tpl/newentry.txt');
        $replace = array(
            '@TITLE@' => $title,
        );
        $tpl = str_replace(array_keys($replace), array_values($replace), $tpl);
        return $tpl;
    }
}
// vim:ts=4:sw=4:et:
