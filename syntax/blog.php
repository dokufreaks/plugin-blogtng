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
        'tpl'       => 'full',
        'limit'     => 5,
        'offset'    => 0,
        'blog'        => null
    );

    var $sqlitehelper = null;

    var $data_whitelist = array(
        'sortyorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

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
        $this->Lexer->addSpecialPattern('<blog>.*?</blog>', $mode, 'plugin_blogtng_blog');
    }

    function handle($match, $state, $pos, &$handler) {
        $match = substr(trim($match), 6, -7);
        $conf = linesToHash(explode("\n", $match));

        $blogs = array_map('trim', explode(' ', $conf['blog']));
        $conf['blog'] = $blogs;

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
        $blog_query = $this->_join_blog_query($data['blog']);

        $query = 'SELECT pid, page, title, blog, image, created,
                         lastmod, login, author, email
                    FROM entries
                   WHERE '.$blog_query.'
                ORDER BY '.$sortkey.' '.$data['sortorder'].
                 ' LIMIT '.$data['limit'].
                ' OFFSET '.$data['offset'];
        $resid = $this->sqlitehelper->query($query);
        if (!$resid) return;

        ob_start();
        $entry =& plugin_load('helper', 'blogtng_entry');
        $count = sqlite_num_rows($resid);
        for ($i = 0; $i < $count; $i++) {
            $entry->load_by_res($resid, $i);
            // handle template stuff here...
            include(DOKU_PLUGIN . 'blogtng/tpl/'.$data['tpl'].'_list.php');
        }
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    function _join_blog_query($blogs) {
        $parts = array();
        foreach ($blogs as $blog) {
            array_push($parts, 'blog = \''.sqlite_escape_string($blog).'\'');
        }
        return join(' OR ', $parts);
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
