<?php
/**
 * Syntax Component Blog
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

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
        'format'    => ':blog:%Y:%m:%{title}',
        'listwrap'  => 0, //default depends on syntax type
    );

    /** @var helper_plugin_blogtng_entry */
    var $entryhelper  = null;
    /** @var helper_plugin_blogtng_tools */
    var $tools = null;
    /** @var helper_plugin_blogtng_comments */
    var $commenthelper  = null;
    /** @var helper_plugin_blogtng_tags */
    var $taghelper;

    /**
     * Types we accept in our syntax
     */
    var $type_whitelist = array('list', 'pagination', 'related', 'recentcomments', 'newform', 'tagcloud', 'tagsearch');

    /**
     * Values accepted in syntax
     */
    var $data_whitelist = array(
        'sortyorder' => array('asc', 'desc'),
        'sortby' => array('created', 'lastmod', 'title', 'page', 'random'),
    );

    // default plugin functions...
    /**
     * Syntax Type
     *
     * @return string
     */
    function getType() { return 'substition'; }

    /**
     * Paragraph Type
     *
     * @return string
     */
    function getPType() { return 'block'; }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    function getSort() { return 300; }

    /**
     * Register the <blog *></blog> syntax
     *
     * @param string $mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<blog ?[^>]*>.*?</blog>', $mode, 'plugin_blogtng_blog');
    }

    /**
     * Parse the type and configuration data from the syntax
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
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

        if (($type != 'tagsearch') && (!count($conf['blog']))) {

            $conf['blog'] = array('default');

        }

        // higher default limit for tag cloud
        if($type == 'tagcloud' && !$conf['limit']) {
            $conf['limit'] = 25;
        }

        // default to listwrap for recent comments
        if(($type == 'recentcomments' || $type == 'tagsearch') && !isset($conf['listwrap'])){
            $conf['listwrap'] = 1;
        }

        // reversed listwrap syntax
        if($conf['nolistwrap']) {
            $conf['listwrap'] = 0;
            unset($conf['nolistwrap']);
        }
        // reversed nolist to listwrap syntax (backward compatibility)
        if($conf['nolist']) {
            $conf['listwrap'] = 0;
            unset($conf['nolist']);
        }

        // merge with default config
        $conf = array_merge($this->config, $conf);

        return array('type'=>$type, 'conf'=>$conf);
    }

    /**
     * Handles the actual output creation.
     *
     * @param string          $mode     output format being rendered
     * @param Doku_Renderer   $renderer the current renderer object
     * @param array           $data     data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;

        $this->loadHelpers();

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
                $renderer->doc .= $this->commenthelper->xhtml_recentcomments($data['conf']);
                break;
            case 'tagcloud':
                $renderer->info['cache'] = false; // never cache this
                $renderer->doc .= $this->taghelper->xhtml_tagcloud($data['conf']);
                break;
            case 'tagsearch':
                $renderer->doc .= $this->entryhelper->xhtml_tagsearch($data['conf'], $renderer);
                break;
            default:
                $renderer->doc .= $this->entryhelper->xhtml_list($data['conf'], $renderer);
        }

        return true;
    }

    /**
     * Parse options given in the syntax
     *
     * @param $opt
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
     * Load required helper plugins.
     */
    private function loadHelpers() {
        $this->entryhelper = plugin_load('helper', 'blogtng_entry');
        $this->tools = plugin_load('helper', 'blogtng_tools');
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
        $this->taghelper = plugin_load('helper', 'blogtng_tags');
    }
}
// vim:ts=4:sw=4:et:
