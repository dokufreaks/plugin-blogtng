<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/infoutils.php');

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

class helper_plugin_blogtng_sqlite3 extends DokuWiki_Plugin {

    var $db = null;
    var $rows = null;

    /**
     * constructor
     */
    function helper_plugin_blogtng_sqlite3(){
        if (!extension_loaded('sqlite3')) {
            $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
            if(function_exists('dl')) @dl($prefix . 'sqlite3.' . PHP_SHLIB_SUFFIX);
        }

        if(!class_exists('SQLite3')){
            msg('blogtng plugin: SQLite3 support missing in this PHP install - plugin will not work',-1);
        }
    }

    /**
     * Open the database
     */
    function _dbconnect(){
        global $conf;

        if($this->db) return true;

        $dbfile = $conf['metadir'].'/blogtng.sqlite3';
        $init   = (!@file_exists($dbfile) || ((int) @filesize($dbfile)) < 3);

        $error='';
        //$this->db = sqlite_open($dbfile, 0666, $error);
        $this->db = new SQLite3($dbfile);
        
        if(!$this->db){
            msg("blogtng plugin: failed to open SQLite database ($error)",-1);
            return false;
        }

        if($init) $this->_runupdatefile(BLOGTNG_DIR.'db/db.sql',1);
        $this->_updatedb();
        return true;
    }

    /**
     * Return the current Database Version
     */
    function _currentDBversion(){
        $sql = "SELECT val FROM opts WHERE opt = 'dbversion';";
        $res = $this->query($sql);
        if(!$res) return false;
        $row = $this->res2row($res,0);
        return (int) $row['val'];
    }

    /**
     * Update the database if needed
     */
    function _updatedb(){
        $current = $this->_currentDBversion();
        if(!$current){
            msg('blogtng: no DB version found. DB probably broken.',-1);
            return false;
        }
        $latest  = (int) trim(io_readFile(BLOGTNG_DIR.'db/latest.version'));

        // all up to date?
        if($current >= $latest) return true;
        for($i=$current+1; $i<=$latest; $i++){
            $file = sprintf(BLOGTNG_DIR.'db/update%04d.sql',$i);
            if(file_exists($file)){
                if(!$this->_runupdatefile($file,$i)){
                    msg('blogtgng: Database upgrade failed for Version '.$i, -1);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Updates the database structure using the given file to
     * the given version.
     */
    function _runupdatefile($file,$version){
        $sql  = io_readFile($file,false);

        $sql = explode(";",$sql);
        array_unshift($sql,'BEGIN TRANSACTION');
        array_push($sql,"UPDATE opts SET val = $version WHERE opt = 'dbversion'");
        array_push($sql,"COMMIT TRANSACTION");

        foreach($sql as $s){
            $s = preg_replace('!^\s*--.*$!m', '', $s);
            $s = trim($s);
            if(!$s) continue;
            //$res = sqlite_query($this->db,"$s;");
            $res = $this->db->query("$s;");
            if ($res === false) {
                //sqlite_query($this->db, 'ROLLBACK TRANSACTION');
                $this->query('ROLLBACK TRANSACTION');
                return false;
            }
        }

        return ($version == $this->_currentDBversion());
    }

    /**
     * Execute a query with the given parameters.
     *
     * Takes care of escaping
     *
     * @param string $sql - the statement
     * @param arguments...
     */
    function query(){
        if(!$this->_dbconnect()) return false;

        // get function arguments
        $args = func_get_args();
        $sql  = trim(array_shift($args));

        if(!$sql){
            msg('No SQL statement given',-1);
            return false;
        }

        if(count($args) > 0 && is_array($args[0])) $args = $args[0];
        $argc = count($args);

        // check number of arguments
        if($argc < substr_count($sql,'?')){
            msg('Not enough arguments passed for statement. '.
                'Expected '.substr_count($sql,'?').' got '.
                $argc.' - '.hsc($sql),-1);
            return false;
        }

        // explode at wildcard, then join again
        $parts = explode('?',$sql,$argc+1);
        $args  = array_map(array($this,'quote_string'),$args);
        $sql   = '';

        while( ($part = array_shift($parts)) !== null ){
            $sql .= $part;
            $sql .= array_shift($args);
        }

        // execute query
        $err = '';
        //$res = @sqlite_query($this->db,$sql,SQLITE_ASSOC,$err);
        $this->rows = null;
        $res = $this->db->query($sql);
        /*
        if($err){
            msg($err.' - '.hsc($sql),-1);
            return false;
        }elseif(!$res){
            msg(sqlite_error_string(sqlite_last_error($this->db)).
                ' - '.hsc($sql),-1);
            return false;
        }
        */
        if (!$res){
            msg($this->db->lastErrorMsg().' - '.hsc($sql), -1);
            return false;
        }

        return $res;
    }

    /**
     * Returns a complete result set as array
     */
    function res2arr($res){
        $data = array();

        if (!@$res->reset())
            return $data;

        while(($row = $res->fetchArray()) !== false){
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Return the wanted row from a given result set as
     * associative array
     */
    function res2row($res,$rownum=0){
        if (!@$res->reset())
            return false;
        
        $row = array();
        for ($i = 0; $i <= $rownum; $i++)
        {
            $row = $res->fetchArray();
            if ($row === false)
            {
                return false;
            }
        }
        
        return $row;
    }


    /**
     * Return count of elements in result set
     * @param $res
     * @return int
     */
    function resRowCount($res){
        $data = $this->res2arr($res);
        return count($data);
    }


    /**
     * Return count of changes after last update
     * @return mixed
     */
    function changes(){
        return $this->db->changes();
    }


    /**
     * Join the given values and quote them for SQL insertion
     */
    function quote_and_join($vals,$sep=',') {
        $vals = array_map(array('helper_plugin_blogtng_sqlite3','quote_string'),$vals);
        return join($sep,$vals);
    }

    /**
     * Run sqlite_escape_string() on the given string and surround it
     * with quotes
     */
    function quote_string($string){
        if(!$this->_dbconnect()) return false;
        return "'".$this->db->escapeString($string)."'";
    }

}
