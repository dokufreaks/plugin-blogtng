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
     * Return a page using the given format and title
     */
    static public function mkpostid($format,$title){
        global $conf;

        $title = str_replace(':',$conf['sepchar'],cleanID($title));
        $out = $format;
        $out = str_replace('%{title}',$title,$out);
        $out = str_replace('%{user}',$_SERVER['REMOTE_USER'],$out);
        $out = strftime($out);
        return $out;
    }


}
