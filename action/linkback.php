<?php

/**
 * Receive component of the DokuWiki Linkback action plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'action.php');

require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/template.php');

if (!defined('NL'))
    define('NL', "\n");

class action_plugin_blogtng_linkback extends DokuWiki_Action_Plugin {
    private $run = false;

    /**
     * Register the eventhandlers.
     */
    function register(& $controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'check', array ());
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_act_render', array ());
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_metaheader_output', array ());
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, 'handle_headers_send', array ());
    }

    function check(&$event, $params) {
        $helper = plugin_load('helper', 'blogtng_linkback');
        if (!$helper->linkbackAllowed()) {
            return;
        }
        $this->run = true;
    }

    /**
     * Handler for the TPL_ACT_RENDER event
     */
    function handle_act_render(& $event, $params) {
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
     */
    function handle_metaheader_output(& $event, $params) {
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
     */
    function handle_headers_send(& $event, $params) {
        if (!$this->run) return;
        global $ID;

        // Add pingback header
        $event->data[] = 'X-Pingback: ' . DOKU_URL . 'lib/plugins/blogtng/exe/pingback.php/' . $ID;
        return true;
    }
}
