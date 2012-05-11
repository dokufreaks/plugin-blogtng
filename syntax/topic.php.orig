<?php
/**
 * Syntax Component Blog
 *
 * @todo this seems to be obsoleted by <blog list>?
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_blogtng_topic extends DokuWiki_Syntax_Plugin {

    var $config = array(
        'sortorder' => 'DESC',
        'sortby'    => 'created',
        'tpl'       => 'default',
        'limit'     => 5,
        'offset'    => 0,
        'tagquery'  => null
    );

    var $sqlitehelper = null;
    var $taghelper = null;

    var $data_whitelist = array(
        'sortyorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

    function syntax_plugin_blogtng_topic() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
        $this->taghelper =& plugin_load('helper', 'blogtng_tags');
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 300; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{topic>.*?}}', $mode, 'plugin_blogtng_topic');
    }

    function handle($match, $state, $pos, &$handler) {
        $match = substr(trim($match), 8, -2);
        $conf['tagquery'] = $match;

        $data = array_merge($this->config, $conf);
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
            $renderer->info['cache'] = false;
            $renderer->doc .= $this->_list($data);
        }
    }

    /**
     * Parse Options
     */
    function _parse_opt($opt) {
        switch(true) {
            case (in_array($opt, $this->data_whitelist['sortorder'])):
                $this->config['sortorder'] = strtoupper($opt);
                break;
            case (in_array($opt, $this->data_whitelist['sortby'])):
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
        $sortkey = ($data['sortby'] == 'random') ? 'Random()' : $data['sortby'];
        $tag_query = $this->taghelper->join_tag_query($data['tagquery']);

        $query = 'SELECT pid, page, title, blog, image, created,
                         lastmod, login, author, mail
                    FROM entries
                   WHERE pid IN (SELECT pid FROM tags WHERE '.$tag_query.')
                ORDER BY '.$sortkey.' '.$data['sortorder'].
                 ' LIMIT '.$data['limit'].
                ' OFFSET '.$data['offset'];
        $resid = $this->sqlitehelper->query($query);
        if (!$resid) return;

        ob_start();
        $entryhelper =& plugin_load('helper', 'blogtng_entry');
        $count = sqlite_num_rows($resid);
        for ($i = 0; $i < $count; $i++) {
            $entryhelper->load_by_res($resid, $i);
            $entryhelper->tpl_content($data['tpl'], 'list');
        }
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }
}
// vim:ts=4:sw=4:et:
