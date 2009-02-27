<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <gina@foosel.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blogtng_pagedata extends DokuWiki_Action_Plugin{

    var $entry;

    function action_plugin_blogtng_pagedata() {
        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function register(&$controller) {
        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'update_data', array());
    }

    /**
     * Updates the metadata in the blogtng database.
     */
    function update_data(&$event, $params) {
        global $ID;
        global $auth;

        $data = $event->data;

        // fetch author info
        $creator = $data['current']['creator'];
        $userdata = false;
        if ($auth != null)
            $userdata = $auth->getUserData($creator);

        // fetch dates
        $date_created = $data['current']['date']['created'];
        $date_modified = $data['current']['date']['modified'];

        // prepare entry ...
        $data = array(
            'pid' => md5($ID),
            'page' => $ID,
            'title' => $data['current']['title'],
            'image' => $data['current']['relation']['firstimage'],
            'created' => $date_created,
            'lastmod' => ($date_modified === false) ? $date_created : $date_modified,
            'author' => ($userdata) ? $userdata['name'] : $creator,
        );
        $this->entryhelper->entry = $data;

        // ... and save it
        $this->entryhelper->save();
    }

}
// vim:ts=4:sw=4:et:enc=utf-8:
