<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(DOKU_INC.'inc/infoutils.php');

if(!defined('BLOGTNG_DIR')) define('BLOGTNG_DIR',DOKU_PLUGIN.'blogtng/');

/**
 * Class helper_plugin_blogtng_sqlite
 */
class helper_plugin_blogtng_sqlite extends DokuWiki_Plugin {

    /** @var helper_plugin_sqlite initialized via _getDb() */
    protected $db = null;

    /**
     * Simple function to check if the database is ready to use
     */
    public function ready() {
        return (bool) $this->getDB();
    }

    /**
     * Returns the instance of helper_plugin_sqlite,
     * otherwise it creates a new instance of the helper_plugin_sqlite and stores it in this object
     *
     * @return helper_plugin_sqlite returned the loaded sqlite helper
     */
    public function getDB() {
        if($this->db === null) {
            $this->db = plugin_load('helper', 'sqlite');
            if($this->db === null) {
                msg('The BlogTNG plugin needs the <a href="https://www.dokuwiki.org/plugin:sqlite">sqlite plugin</a>. Please install and enable this plugin.', -1);
                return false;
            }
            if(!$this->db->init('blogtng', dirname(__FILE__) . '/../db/')) {
                $this->db = null;
                return false;
            }
        }
        return $this->db;
    }
}
