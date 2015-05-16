<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_blogtng_entry
 */
class action_plugin_blogtng_entry extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_entry */
    var $entryhelper = null;
    /** @var helper_plugin_blogtng_comments */
    var $commenthelper = null;

    function __construct() {
        $this->entryhelper = plugin_load('helper', 'blogtng_entry');
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_tpl_act_render', array());
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_metaheader_output', array ());
    }

    /**
     * Intercept the usual page display and replace it with a
     * blog template controlled one.
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return void|bool
     */
    function handle_tpl_act_render(Doku_Event $event, $param) {
        global $ID;
        if($event->data != 'show') return false;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        if($this->entryhelper->get_blog() == '') return true;

        // we can handle it
        $event->preventDefault();

        $this->commenthelper->setPid($pid);
        $this->entryhelper->tpl_content($this->entryhelper->entry['blog'], 'entry');
        return true;
    }

    /**
     * Add next and prev meta headers for navigating through
     * blog posts
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return void|bool
     */
    function handle_metaheader_output(Doku_Event $event, $param) {
        global $ACT, $ID;

        if ($ACT != 'show')
            return false;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        if($this->entryhelper->get_blog() == '') return true;

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
