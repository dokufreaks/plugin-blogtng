<?php

/**
 * Receive component of the DokuWiki Linkback action plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/template.php');

if (!defined('NL')) define('NL', "\n");

/**
 * Class action_plugin_blogtng_linkback
 */
class action_plugin_blogtng_linkback extends DokuWiki_Action_Plugin {
    private $run = false;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'check', array ());
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_act_render', array ());
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_metaheader_output', array ());
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'handle_headers_send', array ());
    }

    /**
     * Set $this->run to true if linkback is allowed.
     * 
     * @param Doku_Event $event  event object by reference
     * @param array      $params  empty array as passed to register_hook()
     */
    function check(Doku_Event $event, $params) {
        /** @var helper_plugin_blogtng_linkback $helper */
        $helper = plugin_load('helper', 'blogtng_linkback');
        if (!$helper->linkbackAllowed()) {
            return;
        }
        $this->run = true;
    }

    /**
     * Handler for the TPL_ACT_RENDER event
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $params  empty array as passed to register_hook()
     */
    function handle_act_render(Doku_Event $event, $params) {
        if (!$this->run) return;

        // Action not 'show'? Quit
        if ($event->data != 'show')
            return;

        global $ID;
        // insert RDF definition of trackback into output
        echo '<!--<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . NL .
        'xmlns:dc="http://purl.org/dc/elements/1.1/"' . NL .
        'xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">' . NL .
        '<rdf:Description' . NL .
        'rdf:about="' . wl($ID, '', true) . '"' . NL .
        'dc:identifier="' . wl($ID, '', true) . '"' . NL .
        'dc:title="' . tpl_pagetitle($ID, true) . '"' . NL .
        'trackback:ping="' . DOKU_URL . 'lib/plugins/blogtng/exe/trackback.php/' . $ID . '" />' . NL .
        '</rdf:RDF>-->';
    }

    /**
     * Handler for the TPL_METAHEADER_OUTPUT event
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $params  empty array as passed to register_hook()
     * @return void|bool
     */
    function handle_metaheader_output(Doku_Event $event, $params) {
        if (!$this->run) return;
        global $ID;

        // Add pingback metaheader
        $event->data['link'][] = array (
            'rel' => 'pingback',
            'href' => DOKU_URL . 'lib/plugins/blogtng/exe/pingback.php/' . $ID
        );
        return true;
    }

    /**
     * Handler for the ACTION_HEADERS_SEND event
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $params  empty array as passed to register_hook()
     * @return void|bool
     */
    function handle_headers_send(Doku_Event $event, $params) {
        if (!$this->run) return;
        global $ID;

        // Add pingback header
        $event->data[] = 'X-Pingback: ' . DOKU_URL . 'lib/plugins/blogtng/exe/pingback.php/' . $ID;
        return true;
    }
}
