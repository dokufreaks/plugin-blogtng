<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

class helper_plugin_blogtng_tools extends DokuWiki_Plugin {


    /**
     * return some info
     */
    function getInfo(){
        return confToHash(BLOGTNG_DIR.'INFO');
    }


    /**
     * Return a page id based on the given format and title.
     *
     * @param $format string the format of the id to generate
     * @param $title  string the title of the page to create
     * @return string a page id
     */
    static public function mkpostid($format,$title){
        global $conf;

        $replace = array(
            '%{title}' => str_replace(':',$conf['sepchar'],$title),
            '%{user}' => $_SERVER['REMOTE_USER'],
        );

        $out = $format;
        $out = str_replace(array_keys($replace), array_values($replace), $out);
        $out = strftime($out);
        return cleanID($out);
    }

    /**
     * Return the blogtng request parameter corresponding to the given path.
     *
     * @param $path string a / separated path to the parameter to return
     * @return mixed returns the value of the referenced parameter, or false if something went wrong while retrieving it
     */
    static public function getParam($path) {
        if (!isset($_REQUEST['btng'])) return false;
        if (!is_array($path)) {
            $path = array_filter(split('/',$path));
        }

        $elem = $_REQUEST['btng'];
        foreach ($path as $p) {
            dbglog('PATH: ' . $p . ', elem: ' . print_r($elem, true));
            if (is_array($elem) && isset($elem[$p])) {
                $elem = $elem[$p];
            } else {
                return false;
            }
        }

        return $elem;
    }


}
