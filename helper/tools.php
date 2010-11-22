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
     * Values accepted in syntax
     */
    static $data_whitelist = array(
        'sortorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

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
        $out = dformat(null, $out);
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
            $path = array_filter(explode('/',$path));
        }

        $elem = $_REQUEST['btng'];
        foreach ($path as $p) {
            if (!is_array($elem) || !isset($elem[$p])) {
                return false;
            }
            $elem = $elem[$p];
        }

        return $elem;
    }

    static public function cleanConf(&$conf) {
        if (!in_array($conf['sortorder'], self::$data_whitelist['sortorder'])) {
            unset($conf['sortorder']);
        }
        if (!in_array($conf['sortby'], self::$data_whitelist['sortby'])) {
            unset($conf['sortby']);
        }
        if (!is_int($conf['limit'])) {
            unset($conf['limit']);
        }
        if (!is_int($conf['offset'])) {
            unset($conf['offset']);
        }
    }

    static public function getTplFile($tpl, $type) {
        $res = false;
        foreach(array('/', '_') as $sep) {
            $fname = DOKU_PLUGIN . "blogtng/tpl/$tpl$sep$type.php";
            if (file_exists($fname)) {
                $res = $fname;
                break;
            }
        }

        if($res === false){
            msg("blogtng plugin: template file $type for template $tpl does not exist!", -1);
            return false;
        }

        return $res;
    }
}
