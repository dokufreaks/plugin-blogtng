<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <gina@foosel.net>
 */

/**
 * Class action_plugin_blogtng_pagedata
 */
class action_plugin_blogtng_pagedata extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_entry */
    private $entryhelper;

    public function __construct() {
        $this->entryhelper = plugin_load('helper', 'blogtng_entry');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'updateMetadataBlog', array());
    }

    /**
     * Updates the metadata in the blogtng database.
     *
     * @param Doku_Event $event
     * @param $params
     */
    public function updateMetadataBlog(Doku_Event $event, $params) {
        global $ID, $INPUT;
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $data = $event->result; //newly rendered data is here.

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);

        //only refreshing for blog entries
        if($this->entryhelper->entry['blog']) {

            // fetch author info
            $login = $this->entryhelper->entry['login'];
            if(!$login) $login = $data['current']['user'];
            if(!$login) $login = $INPUT->server->str('REMOTE_USER');

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
                'author' => $userdata ? $userdata['name'] : $login,
                'mail' => $userdata ? $userdata['mail'] : '',
            );
            $this->entryhelper->set($entry);

            // ... and save it
            $this->entryhelper->save();
        }

        // unset old persistent tag data
        if (isset($data['persistent']['subject'])) {
            // persistent metadata is copied to the current metadata, clean current metadata
            // if it hasn't been changed in the renderer
            if ($data['persistent']['subject'] == $data['current']['subject'])
                $event->result['current']['subject'] = [];
            unset($event->result['persistent']['subject']);
        }

        // save blogtng tags to the metadata of the page
        $taghelper = $this->entryhelper->getTagHelper();
        if (isset($data['current']['subject'])) {
            $event->result['current']['subject'] = array_unique(array_merge((array)$data['current']['subject'], $taghelper->getTags()));
        } else {
            $event->result['current']['subject'] = $taghelper->getTags();
        }
    }

}
