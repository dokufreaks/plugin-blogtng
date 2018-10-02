<?php
/**
 * Renderer for comments
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * The Renderer
 */
class renderer_plugin_blogtng_comment extends Doku_Renderer_xhtml {
    var $slideopen = false;
    var $base='';
    var $tpl='';

    /**
     * the format we produce
     */
    function getFormat(){
        return 'blogtng_comment';
    }

    function nocache() {}
    function notoc() {}
    function document_end() {}

    /**
     * Render a heading
     *
     * @param string $text  the text to display
     * @param int    $level header level
     * @param int    $pos   byte position in the original source
     */
    function header($text, $level, $pos) {
        $this->cdata($text);
    }

    function section_edit() {}

    /**
     * Open a new section
     *
     * @param int $level section level (as determined by the previous header)
     */
    function section_open($level) {}
    function section_close() {}
    function footnote_open() {}
    function footnote_close() {}

    /**
     * Execute PHP code if allowed
     *
     * @param  string $text      PHP code that is either executed or printed
     * @param  string $wrapper   html element to wrap result if $conf['phpok'] is okff
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function php($text, $wrapper='code') {
        $this->doc .= p_xhtml_cached_geshi($text, 'php', $wrapper);
    }

    /**
     * Insert HTML if allowed
     *
     * @param  string $text      html text
     * @param  string $wrapper   html element to wrap result if $conf['htmlok'] is okff
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function html($text, $wrapper='code') {
        $this->doc .= p_xhtml_cached_geshi($text, 'html4strict', $wrapper);
    }

    /**
     * Renders an RSS feed
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $url
     * @param array  $params
     */
    function rss($url, $params) {}

    /**
     * Call a syntax plugin's render function if it is allowed
     * by the actual configuration settings.
     * 
     * @param  string $name
     * @param  mixed  $data
     * @return bool
     */
    function plugin($name, $data, $state = '', $match = '') {
        $comments_xhtml_renderer = array_map('trim', explode(',', $this->getConf('comments_xhtml_renderer')));
        $comments_forbid_syntax = array_map('trim', explode(',', $this->getConf('comments_forbid_syntax')));
        /** @var DokuWiki_Syntax_Plugin $plugin */
        $plugin = plugin_load('syntax',$name);
        if($plugin != null){
            if (in_array($name, $comments_forbid_syntax)) {
                return false;
            } elseif (in_array($name, $comments_xhtml_renderer)) {
                $plugin->render('xhtml',$this,$data);
            } else {
                $plugin->render($this->getFormat(), $this, $data);
            }
        }
        return true;
    }
}

//Setup VIM: ex: et ts=4 :
