<?php
/**
 * Syntax Component Blog
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * Covers all <blog *> syntax commands
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
        'tags'      => array(),
        'page'      => false,
        'cache'     => false,
        'title'     => '',
        'format'    => ':blog:%Y:%m:%{title}', #FIXME
        'listwrap'  => 0, //default depends on syntax type
    );

    var $entryhelper  = null;
    var $tools = null;
    var $commenthelper  = null;

    /**
     * Types we accept in our syntax
     */
    var $type_whitelist = array('list', 'pagination', 'related', 'recentcomments', 'newform', 'tagcloud');

    /**
     * Values accepted in syntax
     */
    var $data_whitelist = array(
        'sortyorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

    // default plugin functions...
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
        $conf['blog'] = array_filter(array_map('trim', explode(',', $conf['blog'])));
        $conf['tags'] = array_filter(array_map('trim', explode(',', $conf['tags'])));
        $conf['type'] = array_filter(array_map('trim', explode(',', $conf['type'])));

        if(!count($conf['blog'])) $conf['blog'] = array('default');

        // higher default limit for tag cloud
        if($type == 'tagcloud' && !$conf['limit']) {
            $conf['limit'] = 25;
        }

        // default to listwrap for recent comments
        if($type == 'recentcomments' && !isset($conf['listwrap'])){
            $conf['listwrap'] = 1;
        }

        // reversed listwrap syntax
        if($conf['nolistwrap']) {
            $conf['listwrap'] = 0;
            unset($conf['nolistwrap']);
        }

        // merge with default config
        $conf = array_merge($this->config, $conf);

        return array('type'=>$type, 'conf'=>$conf);
    }

    /**
     * Render Output
     */
    function render($mode, &$renderer, $data) {
        if($mode != 'xhtml') return false;

        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
        $this->tools =& plugin_load('helper', 'blogtng_tools');

        // set target if not set yet
        global $ID;
        if(!isset($data['conf']['target'])) $data['conf']['target'] = $ID;

        // add additional data from request parameters
        if($start = $this->tools->getParam('pagination/start')){  // start offset
            $data['conf']['offset'] = (int) $start;
        }

        if($tags = $this->tools->getParam('post/tags')){  // tags
            $data['conf']['tags'] = array_merge(
                                       $data['conf']['tags'],
                                       explode(',',$tags));
        }
        $data['conf']['tags'] = array_map('trim',$data['conf']['tags']);
        $data['conf']['tags'] = array_unique($data['conf']['tags']);
        $data['conf']['tags'] = array_filter($data['conf']['tags']);

        // dispatch to the correct type handler
        $renderer->info['cache'] = (bool)$data['conf']['cache'];
        switch($data['type']){
            case 'related':
                $renderer->doc .= $this->entryhelper->xhtml_related($data['conf']);
                break;
            case 'pagination':
                $renderer->doc .= $this->entryhelper->xhtml_pagination($data['conf']);
                break;
            case 'newform':
                $renderer->info['cache'] = false; //never cache this
                $renderer->doc .= $this->entryhelper->xhtml_newform($data['conf']);
                break;
            case 'recentcomments':
                // FIXME to cache or not to cache?
                $this->commenthelper =& plugin_load('helper', 'blogtng_comments');
                $renderer->doc .= $this->commenthelper->xhtml_recentcomments($data['conf']);
                break;
            case 'tagcloud':
                $renderer->inf['cache'] = false; // never cache this
                $this->taghelper =& plugin_load('helper', 'blogtng_tags');
                $renderer->doc .= $this->taghelper->xhtml_tagcloud($data['conf']);
                break;
            default:
                $renderer->doc .= $this->entryhelper->xhtml_list($data['conf'], $renderer);
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
}
// vim:ts=4:sw=4:et:
