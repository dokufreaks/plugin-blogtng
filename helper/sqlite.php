<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');


class helper_plugin_blogtng_sqlite extends DokuWiki_Plugin {

    var $db = null;

    /**
     * constructor
     */
    function helper_plugin_blogtng_sqlite(){
        if (!extension_loaded('sqlite')) {
            $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
            @dl($prefix . 'sqlite.' . PHP_SHLIB_SUFFIX);
        }

        if(!function_exists('sqlite_open')){
            msg('blogtng plugin: SQLite support missing in this PHP install - plugin will not work',-1);
        }
    }

    /**
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/../INFO');
    }


    /**
     * Open the database
     */
    function _dbconnect(){
        global $conf;

        $dbfile = $conf['metadir'].'/blogplugin.sqlite';
        $init   = (!@file_exists($dbfile) || !@filesize($dbfile));

        $error='';
        $this->db = sqlite_open($dbfile, 0666, $error);
        if(!$this->db){
            msg("blogtng plugin: failed to open SQLite database ($error)",-1);
            return false;
        }

        if($init) $this->_initdb();
        return true;
    }


    /**
     * create the needed tables
     */
    function _initdb(){
/*
        sqlite_query($this->db,'CREATE TABLE pages (pid INTEGER PRIMARY KEY, page, title);');
        sqlite_query($this->db,'CREATE UNIQUE INDEX idx_page ON pages(page);');
        sqlite_query($this->db,'CREATE TABLE data (eid INTEGER PRIMARY KEY, pid INTEGER, key, value);');
        sqlite_query($this->db,'CREATE INDEX idx_key ON data(key);');
*/
    }
}
