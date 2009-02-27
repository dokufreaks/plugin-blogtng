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

        if($this->db) return true;

        $dbfile = $conf['metadir'].'/blogtng.sqlite';
        $init   = (!@file_exists($dbfile) || ((int) @filesize($dbfile)) < 3);

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
    func+tion _initdb(){
        $sql = io_readFile(dirname(__FILE__).'/../db/db.sql',false);
        $sql = explode(';',$sql);
        foreach($sql as $line){
            @sqlite_query($this->db,"$line;",SQLITE_NUM,$err);
            if($err){
                msg($err.' - '.$line,-1);
            }
        }
    }

    /**
     * Execute a query with the given parameters.
     *
     * Takes care of escaping
     *
     * @param string $sql - the statement
     * @param arguments
     */
    function query(){
        if(!$this->_dbconnect()) return;

        $args = func_get_args();
        $sql  = array_shift($args);

        if(!$sql){
            msg('No SQL statement given',-1);
            return false;
        }

        $sqlparts = explode('?',$sql);
        $len = count($sqlparts);
        if(count($args) < $len){
            msg('Not enough arguents passed for statement');
            return false;
        }

        array_map('sqlite_escape_string',$args);
        $statement = '';

        for($i=0;$i<$len;$i++){
            $statement .= $sqlparts[$i].$args[$i];
        }

        $res = @sqlite_query($this->db,$statement,SQLITE_NUM,$err);
        if($err){
            msg($err.' - '.$statement,-1);
            return false;
        }

        return $res;
    }
}
