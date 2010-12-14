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

class action_plugin_blogtng_entry extends DokuWiki_Action_Plugin{

    var $entryhelper = null;
    var $commenthelper = null;

    function action_plugin_blogtng_entry() {
        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
        $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
    }

    function register(&$controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_tpl_act_render', array());
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_metaheader_output', array ());
    }

    /**
     * Intercept the usual page display and replace it with a
     * blog template controlled one.
     */
    function handle_tpl_act_render(&$event, $param) {
        global $ID;
        if($event->data != 'show') return;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        if($this->entryhelper->get_blog($pid) == '') return true;

        // we can handle it
        $event->preventDefault();

        $this->commenthelper->load($pid);
        $this->entryhelper->tpl_content($this->entryhelper->entry['blog'], 'entry');
    }

    /**
     * Add next and prev meta headers for navigating through
     * blog posts
     */
    function handle_metaheader_output(&$event, $param) {
        global $ACT, $ID;

        if ($ACT != 'show')
            return;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        if($this->entryhelper->get_blog($pid) == '') return true;

        $relatedentries = $this->entryhelper->getAdjacentLinks($ID);
        if (isset ($relatedentries['prev'])) {
            $event->data['link'][] = array (
                'rel' => 'prev',
                'href' => wl($relatedentries['prev']['page'], '')
            );
        }
        if (isset ($relatedentries['next'])) {
            $event->data['link'][] = array (
                'rel' => 'next',
                'href' => wl($relatedentries['next']['page'], '')
            );
        }

        return true;
    }
}
// vim:ts=4:sw=4:et:
