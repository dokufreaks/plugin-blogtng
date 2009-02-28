<?php
/**
 * Syntax Component Blog
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_blogtng_blog extends DokuWiki_Syntax_Plugin {

    var $config = array( 
        'sortorder' => 'DESC',
        'sortby'    => 'created',
        'tpl'       => 'default',
        'limit'     => 5,
        'offset'    => 0,
        'ns'        => null
    );

    var $sqlitehelper = null;

    function syntax_plugin_blogtng_blog() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
    }
    /**
     * return some info
     */
    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 300; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{blog>.*?\}\}', $mode, 'plugin_blogtng_blog');
    }

    function handle($match, $state, $pos, &$handler) {
        $match = substr($match, 7, -2); 
        $opts = explode(' ', $match);
        $ns = array_shift($opts);
        array_map(array($this, '_parse_opt'), $opts);
        $data = array_merge($this->config, array('ns' => $ns));
        return $data;
    }

    /**
     * Render Output
     */
    function render($mode, &$renderer, $data) {

        // do cool stuff here
        if (plugin_isdisabled('blogtng')) return; // FIXME do nothing and scream
        //$this->helper =& plugin_load('helper', 'blogtng_FIXME'));

        if($mode == 'xhtml') {
            $this->_list($data);
        }
    }

    /**
     * Parse Options
     */
    function _parse_opt($opt) {
        switch(true) {
            case ($opt == 'asc' || $opt == 'desc'):
                // FIXME validate input against whitelist!
                $this->config['sortorder'] = strtoupper($opt);
                break;
            case ($opt == 'bydate' || $opt == 'bypage'):
                // FIXME validate input against whitelist!
                $this->config['sortby'] = substr($opt, 2);
                break;
            case (preg_match('/^\d$/', $opt)):
                $this->config['limit'] = $opt;
                break;
            case (preg_match('/^\+(\d+)$/', $opt, $match)):
                $this->config['order'] = $match[1];
                break;
            case (preg_match('/^tpl(\w+)$/', $opt, $match)):
                $this->config['tpl'] = $match[1];
                break;
            default;
                continue;
        }
    }

    function _list($data){
        $query = 'SELECT pid, page, title, image, created, lastmod, login, author, email FROM articles WHERE page LIKE ? ORDER BY '.$this->config['sortby'].' '.$this->config['sortorder'].' LIMIT '.$this->config['limit'].' OFFSET '.$this->config['offset'];
        $resid = $this->sqlitehelper->query($query, $data['ns'] . '%');
        if (!$resid) return;

        $entry =& plugin_load('helper', 'blogtng_entry');
        $count = sqlite_num_rows($resid);
        for ($i = 0; $i < $count; $i++) {
            $entry->load($resid, $i);
            // handle template stuff here...
            dbg($entry->entry);
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
