<?php
/**
 * Syntax Component Blog
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

/**
 * Makes all the blog entries available via syntax commands
 */
class syntax_plugin_blogtng_blog extends DokuWiki_Syntax_Plugin {

    /**
     * Default configuration for all setups
     */
    var $config = array(
        'sortorder' => 'DESC',
        'sortby'    => 'created',
        'tpl'       => 'default',
        'limit'     => 5,
        'offset'    => 0,
        'blog'      => null,
        'tags'      => null,
        'page'      => false,
    );

    var $sqlitehelper = null;

    /**
     * Types we accept in our syntax
     */
    var $type_whitelist = array('list', 'pagination', 'related');

    var $data_whitelist = array(
        'sortyorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

    // default plugin functions...
    function syntax_plugin_blogtng_blog() {
        global $ID;
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
        $this->config['target'] = $ID;
    }
    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }
    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 300; }

    /**
     * Register the <blog *></blog> syntax
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<blog ?[^>]*>.*?</blog>', $mode, 'plugin_blogtng_blog');
    }

    /**
     * Parse the type and configuration data from the syntax
     */
    function handle($match, $state, $pos, &$handler) {
        $match = substr(trim($match), 5, -7); // strip '<blog' and '</blog>'
        list($type,$conf) = explode('>',$match,2);
        $type = trim($type);
        $conf = trim($conf);
        $conf = linesToHash(explode("\n", $conf));
        if(!$type) $type = 'list';

        // check type
        if(!in_array($type,$this->type_whitelist)){
            msg('Unknown blog syntax type "'.hsc($type).'" using "list" instead',-1);
            $type = 'list';
        }

        // handle multi keys
        $conf['blog'] = array_map('trim', explode(',', $conf['blog']));
        $conf['tags'] = array_map('trim', explode(',', $conf['tags']));

        // merge with default config
        $conf = array_merge($this->config, $conf);

        return array('type'=>$type, 'conf'=>$conf);
    }

    /**
     * Render Output
     */
    function render($mode, &$renderer, $data) {
        if($mode != 'xhtml') return false;

        // add additional data from request parameters
        if(isset($_REQUEST['btngs'])){
            $data['conf']['offset'] = (int) $_REQUEST['btngs'];  // start offset
        }


        // dispatch to the correct type handler
        switch($data['type']){
            case 'related':
                $renderer->doc .= $this->_related($data['conf']);
                break;
            case 'pagination':
                $renderer->info['cache'] = false;
                $renderer->doc .= $this->_pagination($data['conf']);
                break;
            default:
                $renderer->info['cache'] = false;
                $renderer->doc .= $this->_list($data['conf']);
        }


        return true;
    }

    /**
     * Parse options given in the syntax
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

    /**
     * List matching blog entries
     *
     * Creates the needed SQL query from the given config data, executes
     * it and calles the *_list template for each entry in the result set
     *
     * @fixme move SQL creation to its own function?
     */
    function _list($conf){

        $sortkey = ($conf['sortby'] == 'random') ? 'Random()' : $conf['sortby'];
        $blog_query = 'blog = '.
                      $this->sqlitehelper->quote_and_join($conf['blog'],
                                                          ' OR blog = ');

        $query = 'SELECT pid, page, title, blog, image, created,
                         lastmod, login, author, email
                    FROM entries
                   WHERE '.$blog_query.'
                ORDER BY '.$sortkey.' '.$conf['sortorder'].
                 ' LIMIT '.$conf['limit'].
                ' OFFSET '.$conf['offset'];
        $resid = $this->sqlitehelper->query($query);
        if (!$resid) return '';

        ob_start();
        $entryhelper =& plugin_load('helper', 'blogtng_entry');
        $count = sqlite_num_rows($resid);
        for ($i = 0; $i < $count; $i++) {
            $entryhelper->load_by_res($resid, $i);
            $entryhelper->tpl_content($conf['tpl'], 'list');
        }
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Display pagination links for the configured list of entries
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     */
    function _pagination($conf){
        $sortkey = ($conf['sortby'] == 'random') ? 'Random()' : $conf['sortby'];
        $blog_query = 'blog = '.
                      $this->sqlitehelper->quote_and_join($conf['blog'],
                                                          ' OR blog = ');

        // get the number of all matching entries
        $query = 'SELECT COUNT(pid) as cnt
                    FROM entries
                   WHERE '.$blog_query;
        $resid = $this->sqlitehelper->query($query);
        if (!$resid) return;
        $count = $this->sqlitehelper->res2row($resid,0);
        $count = (int) $count['cnt'];

        if($count <= $conf['limit']) return '';

        // we now prepare an array of pages to show
        $pages = array();

        // calculate page boundaries
        $max = ceil($count/$conf['limit']);
        $cur = floor($conf['offset']/$conf['limit'])+1;

        $pages[] = 1;     // first page always
        $pages[] = $max;  // last page always
        $pages[] = $cur;  // current always

        if($max > 1){                // if enough pages
            $pages[] = 2;            // second and ..
            $pages[] = $max-1;       // one before last
        }

        // three around current
        if($cur-1 > 0) $pages[] = $cur-1;
        if($cur-2 > 0) $pages[] = $cur-2;
        if($cur-3 > 0) $pages[] = $cur-3;
        if($cur+1 < $max) $pages[] = $cur+1;
        if($cur+2 < $max) $pages[] = $cur+2;
        if($cur+3 < $max) $pages[] = $cur+3;

        sort($pages);
        $pages = array_unique($pages);

        // we're done - build the output
        $out = '';
        $out .= '<div class="blogtng_pagination">';
        if($cur > 1){
            $out .= '<a href="'.wl($conf['target'],
                                   array('btngs'=>$conf['limit']*($cur-2))).
                             '" class="prev">'.$this->getLang('prev').'</a> ';
        }
        $last = 0;
        foreach($pages as $page){
            if($page - $last > 1){
                $out .= ' <span class="sep">...</span> ';
            }
            if($page == $cur){
                $out .= '<span class="cur">'.$page.'</span> ';
            }else{
                $out .= '<a href="'.wl($conf['target'],
                                    array('btngs'=>$conf['limit']*($page-1))).
                                 '">'.$page.'</a> ';
            }
            $last = $page;
        }
        if($cur < $max){
            $out .= '<a href="'.wl($conf['target'],
                                   array('btngs'=>$conf['limit']*($cur))).
                             '" class="next">'.$this->getLang('next').'</a> ';
        }
        $out .= '</div>';

        return $out;
    }

    function _related($conf){
        ob_start();
        $entryhelper =& plugin_load('helper', 'blogtng_entry');
        $entryhelper->tpl_related($conf['limit'],$conf['blog'],$conf['page'],$conf['tags']);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

}
// vim:ts=4:sw=4:et:enc=utf-8:
