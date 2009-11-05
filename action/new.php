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

    function action_plugin_blogtng_comments() {
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function register(&$controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess', array());
    }

    function handle_act_preprocess(&$event, $param) {
        global $TEXT;
        global $ID;

        if($event->data != 'btngnew') return true;
        if(!$_REQUEST['btngnt']){
            msg($this->getLang('err_notitle'),-1);
            $event->data = 'show';
            return true;
        }

        $event->preventDefault();
        $tools =& plugin_load('helper', 'blogtng_tools');
        $ID = $tools->mkpostid($_REQUEST['btngnf'],$_REQUEST['btngnt']);
        $TEXT = $this->_prepare_template($ID, $_REQUEST['btngnt']);
        $event->data = 'preview';
    }

    function _prepare_template($id, $title) {
        $tpl = io_readFile(DOKU_PLUGIN . 'blogtng/tpl/newentry.txt');
        $replace = array(
            '@ID@' => $id,
            '@TITLE@' => $title,
        );
        $tpl = str_replace(array_keys($replace), array_values($replace), $tpl);
        return $tpl;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
