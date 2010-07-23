<?php

/**
 * Trackback server for use with the DokuWiki Linkback Plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../../') . '/');

if (!defined('NL'))
    define('NL', "\n");

require_once (DOKU_INC . 'inc/init.php');
require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/events.php');
require_once (DOKU_INC . 'inc/pluginutils.php');
require_once (DOKU_INC . 'inc/HTTPClient.php');

class TrackbackServer {

    /**
     * Construct helper and process request.
     */
    function TrackbackServer() {
        $this->tools =& plugin_load('helper', 'blogtng_linkback');
        $this->_process();
    }

    /**
     * Process trackback request.
     */
    function _process() {
        // get ID
        global $ID;
        $ID = substr($_SERVER['PATH_INFO'], 1);
        $sourceUri = $_REQUEST['url'];

        if (is_null($this->tools) || !$this->tools->linkbackAllowed()) {
            $this->_printTrackbackError('Trackbacks disabled.');
            return;
        }

        // No POST request? Quit
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->_printTrackbackError('Trackback was not received via HTTP POST.');
            return;
        }

        // Given URL is not an url? Quit
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $sourceUri)) {
            $this->_printTrackbackError('Given trackback URL is not an URL.');
            return;
        }

        // Source does not exist? Quit
        $http = new DokuHTTPClient;
        $page = $http->get($sourceUri);
        if ($page === false) {
            $this->_printTrackbackError('Linked page cannot be reached');
            return;
        }

        if (!$this->tools->saveLinkback('trackback', strip_tags($_REQUEST['title']),
                                        $sourceUri, strip_tags($_REQUEST['excerpt']), $ID)) {
            $this->_printTrackbackError('Trackback already received.');
            return;
        }
        $this->_printTrackbackSuccess();
    }

    /**
     * Print trackback success xml.
     */
    function _printTrackbackSuccess() {
        echo '<?xml version="1.0" encoding="iso-8859-1"?>' . NL .
        '<response>' . NL .
        '<error>0</error>' . NL .
        '</response>';
    }

    /**
     * Print trackback error xml.
     */
    function _printTrackbackError($reason = '') {
        echo '<?xml version="1.0" encoding="iso-8859-1"?>' . NL .
        '<response>' . NL .
        '<error>1</error>' . NL .
        '<message>' . $reason . '</message>' . NL .
        '</response>';
    }
}

$server = new TrackbackServer();
