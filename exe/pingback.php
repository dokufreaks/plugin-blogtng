<?php

/**
 * Pingback server for use with the DokuWiki Linkback Plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

use IXR\Server\Server;
use IXR\Message\Error;
use dokuwiki\HTTP\DokuHTTPClient;

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../../') . '/');

require_once (DOKU_INC . 'inc/init.php');

// Pingback Faultcodes
const PINGBACK_ERROR_GENERIC = 0;
const PINGBACK_ERROR_SOURCEURI_DOES_NOT_EXIST = 16;
const PINGBACK_ERROR_SOURCEURI_DOES_NOT_CONTAIN_LINK = 17;
const PINGBACK_ERROR_TARGETURI_DOES_NOT_EXIST = 32;
const PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED = 33;
const PINGBACK_ERROR_PINGBACK_ALREADY_MADE = 48;
const PINGBACK_ERROR_ACCESS_DENIED = 49;
const PINGBACK_ERROR_NO_UPSTREAM = 50;

/**
 * Class PingbackServer
 */
class PingbackServer extends Server {

    /** @var  helper_plugin_blogtng_linkback */
    var $tools;

    /**
     * Register service and construct helper
     */
    function __construct() {
        $this->tools = plugin_load('helper', 'blogtng_linkback');
        parent::__construct(array('pingback.ping' => 'this:ping'));
    }

    /**
     * Send a HTTP get request to @$sourceUri and extract linkback
     * data for @$targetUri from the response.
     *
     * @param $sourceUri
     * @param $targetUri
     * @return Error|void
     */
    function ping($sourceUri, $targetUri) {
        global $ID;
        $ID = substr($_SERVER['PATH_INFO'], 1);

        if (is_null($this->tools) || !$this->tools->linkbackAllowed()) {
            return new Error(PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED, '');
        }

        // Given URLs are no urls? Quit
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $sourceUri))
            return new Error(PINGBACK_ERROR_GENERIC, '');
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $targetUri))
            return new Error(PINGBACK_ERROR_GENERIC, '');

        // Source URL does not exist? Quit
        $http = new DokuHTTPClient;
        $page = $http->get($sourceUri);
        if ($page === false)
            return new Error(PINGBACK_ERROR_SOURCEURI_DOES_NOT_EXIST, '');

        // Target URL does not match with request? Quit
        if ($targetUri != wl($ID, '', true))
            return new Error(PINGBACK_ERROR_GENERIC, '');

        // Retrieve data from source
        $linkback = $this->_getTrackbackData($sourceUri, $targetUri, $page);

        // Source URL does not contain link to target? Quit
        if (!$linkback)
            return new Error(PINGBACK_ERROR_SOURCEURI_DOES_NOT_CONTAIN_LINK, '');

        if (!$this->tools->saveLinkback('pingback', $linkback['title'],
                                        $sourceUri, $linkback['excerpt'], $ID)) {
            return new Error(PINGBACK_ERROR_PINGBACK_ALREADY_MADE, '');
        }
    }

    /**
     * Constructs linkback data and checks if source contains a link to target and a title.
     *
     * @param $sourceUri
     * @param $targetUri
     * @param $page
     * @return array|bool
     */
    function _getTrackbackData($sourceUri, $targetUri, $page) {
        $linkback = array ();

        $searchurl = preg_quote($targetUri, '!');
        $regex = '!<a[^>]+?href="' . $searchurl . '"[^>]*?>(.*?)</a>!is';
        $regex2 = '!\s(' . $searchurl . ')\s!is';
        if (!preg_match($regex, $page, $match) && !preg_match($regex2, $page, $match)) {
            // FIXME internal pings
            if ((strstr($targetUri, DOKU_URL) == $targetUri)) {
                $ID = substr($_SERVER['PATH_INFO'], 1);
                $searchurl = preg_quote(wl($ID, '', false), '!');

                $regex = '!<a[^>]+?href="' . $searchurl . '"[^>]*?>(.*?)</a>!is';
                $regex2 = '!\s(' . $searchurl . ')\s!is';
                if (!preg_match($regex, $page, $match) && !preg_match($regex2, $page, $match))
                    return false;
            } else {
                return false;
            }
        }
        $linkback['excerpt'] = '[…] ' . strip_tags($match[1]) . ' […]';

        $regex = '!<title.*?>(.*?)</title>!is';
        if (!preg_match($regex, $page, $match))
            return false;
        $linkback['title'] = strip_tags($match[1]);

        return $linkback;
    }
}

$server = new PingbackServer();
