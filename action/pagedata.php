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

    function register(&$controller) {
        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'update_data', array());
    }

    /**
     * Updates the metadata in the blogtng database.
     */
    function update_data(&$event, $params) {
        global $ID;
        global $auth;

        $data = $event->result; //newly rendered data is here.

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);

        // fetch author info
        $login = $this->entryhelper->entry['login'];
        if(!$login) $login = $data['current']['user'];
        if(!$login) $login = $_SERVER['REMOTE_USER'];

        $userdata = false;
        if($login){
            if ($auth != null){
                $userdata = $auth->getUserData($login);
            }
        }

        // fetch dates
        $date_created = $data['current']['date']['created'];
        $date_modified = $data['current']['date']['modified'];

        // prepare entry ...
        $entry = array(
            'page' => $ID,
            'title' => $data['current']['title'],
            'image' => $data['current']['relation']['firstimage'],
            'created' => $date_created,
            'lastmod' => (!$date_modified) ? $date_created : $date_modified,
            'login' => $login,
            'author' => ($userdata) ? $userdata['name'] : $login,
            'mail' => ($userdata) ? $userdata['mail'] : '',
        );
        $this->entryhelper->set($entry);

        // ... and save it
        $this->entryhelper->save();
    }

}
// vim:ts=4:sw=4:et:
