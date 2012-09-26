<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * TODO Database admin functions (create, update, drop, etc.)
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/infoutils.php');

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

class helper_plugin_blogtng_database extends DokuWiki_Plugin {

    var $db = null;

    /**
     * constructor
     */
    function helper_plugin_blogtng_database() {

        // Check, if PDO extension is loaded or try to load it otherwise

        if (!extension_loaded('pdo')) {
            $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
            if(function_exists('dl')) @dl($prefix . 'pdo.' . PHP_SHLIB_SUFFIX);
        }

        if (!extension_loaded('pdo')) {
            msg('blogtng plugin: PDO support missing in this PHP install -' .
            'plugin will not work',-1);
        }

    }

    /**
     * Open the database
     */
    function _dbconnect(){

        if($this->db) return true;

        try {

            $this->db = new PDO(
                $this->getConf('db_dsn'),
                $this->getConf('db_username'),
                $this->getConf('db_password')
            );

        } catch (PDOException $e) {

            msg(
                'blogtng plugin: Cannot connect to database (' .
                $e->getMessage() .
                ')', -1
            );
            return false;

        }

        if ($this->updateNeeded()) {

            msg(
                'blogtng plugin: Database update needed!',
                -1
            );
            $this->db = null;
            return false;

        }

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

    function updateNeeded() {
        $current = $this->_currentDBversion();
        if(!$current){
            msg('blogtng: no DB version found. DB probably broken.',-1);
            return false;
        }
        $latest  = (int) trim(io_readFile(BLOGTNG_DIR.'db/latest.version'));

        return $current <> $latest;
    }

    /**
     * Update the database if needed
     */
    function _updatedb(){

        if (!$this->updateNeeded()) {
            return true;
        }

        $current = $this->_currentDBversion();
        if(!$current){
            msg('blogtng: no DB version found. DB probably broken.',-1);
            return false;
        }
        $latest  = (int) trim(io_readFile(BLOGTNG_DIR.'db/latest.version'));

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
        array_push($sql,"UPDATE opts SET val = $version WHERE opt = 'dbversion'");

        $this->db->beginTransaction();

        foreach($sql as $s){
            $s = preg_replace('!^\s*--.*$!m', '', $s);
            $s = trim($s);
            if(!$s) continue;
            $res = $this->query("$s;");
            if ($res === false) {
                $this->db->rollBack();
                return false;
            }
        }

        $this->db->commit();

        return ($version == $this->_currentDBversion());
    }

    /**
     * Execute a query with the given parameters.
     *
     * Takes care of escaping
     *
     * @internal param string $sql - the statement
     * @internal param $arguments ...
     * @return PDOStatement Statement object or FALSE
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
        $res = $this->db->query($sql);

        if ($res === false) {

            msg(
                sprintf(
                    "Failed to execute query (%s/%s): %s",
                    $this->db->errorCode(),
                    $this->db->errorInfo()[1],
                    $this->db->errorInfo()[2]
                ),
                -1
            );

            return false;

        } elseif ($res->errorCode() == '00000') {

            msg(
                sprintf(
                    "Failed to execute query (%s/%s): %s",
                    $res->errorCode(),
                    $res->errorInfo()[1],
                    $res->errorInfo()[2]
                ),
                -1
            );

        }

        return $res;

    }

    function rowCount($res) {
        return $res->rowCount();
    }

    /**
     * Returns a complete result set as array
     */
    function res2arr($res){
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return the wanted row from a given result set as
     * associative array
     */
    function res2row($res,$rownum=0){
        return $res->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT, $rownum);
    }

    /**
     * Join the given values and quote them for SQL insertion
     */
    function quote_and_join($vals,$sep=',') {
        $vals = array_map(
            array('helper_plugin_blogtng_database','quote_string'),
            $vals
        );
        return join($sep,$vals);
    }

    /**
     * Run sqlite_escape_string() on the given string and surround it
     * with quotes
     */
    function quote_string($string){
        if(!$this->_dbconnect()) return false;
        return "'".$this->db->quote($string)."'";
    }

    /**
     * Start a database transaction
     * @return bool Success
     */

    function beginTransaction() {
        if(!$this->_dbconnect()) return false;

        return $this->db->beginTransaction();
    }

    /**
     * Commit a running database transaction
     * @return bool Success
     */

    function commitTransaction() {
        if(!$this->_dbconnect()) return false;

        return $this->db->commit();
    }

    /**
     * Rollback a database transaction
     * @return bool Success
     */

    function rollbackTransaction() {
        if(!$this->_dbconnect()) return false;

        return $this->db->rollBack();
    }

}