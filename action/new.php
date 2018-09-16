<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_blogtng_new
 */
class action_plugin_blogtng_new extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_comments */
    var $commenthelper = null;

    /**
     * Constructor
     */
    function __construct() {
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
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
     * Handles input from the newform and redirects to the edit mode
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return bool
     */
    function handle_act_preprocess(Doku_Event $event, $param) {
        global $TEXT;
        global $ID;

        if($event->data != 'btngnew') return true;
        /** @var helper_plugin_blogtng_tools $tools */
        $tools = plugin_load('helper', 'blogtng_tools');
        if(!$tools->getParam('new/title')){
            msg($this->getLang('err_notitle'),-1);
            $event->data = 'show';
            return true;
        }

        $new = $tools->mkpostid($tools->getParam('new/format'),$tools->getParam('new/title'));
        if ($ID != $new) {
            $urlparams = array(
                'do' => 'btngnew',
                'btng[post][blog]' => $tools->getParam('post/blog'),
                'btng[post][tags]' => $tools->getParam('post/tags'),
                'btng[post][commentstatus]' => $tools->getParam('post/commentstatus'),
                'btng[new][format]' => $tools->getParam('new/format'),
                'btng[new][title]' => $tools->getParam('new/title')
            );
            send_redirect(wl($new,$urlparams,true,'&'));
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
     *
     * @param $id
     * @param $title
     * @return bool|mixed|string
     */
    function _prepare_template($id, $title) {
        $tpl = pageTemplate($id);
        if(!$tpl) $tpl = io_readFile(DOKU_PLUGIN . 'blogtng/tpl/newentry.txt');

        $replace = array(
            '@TITLE@' => $title,
        );
        $tpl = str_replace(array_keys($replace), array_values($replace), $tpl);
        return $tpl;
    }
}
// vim:ts=4:sw=4:et:
