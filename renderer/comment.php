<?php
/**
 * Renderer for comments
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// we inherit from the XHTML renderer instead directly of the base renderer
require_once DOKU_INC.'inc/parser/xhtml.php';

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

    function header($text, $level, $pos) {
        $this->cdata($text);
    }

    function section_edit() {}
    function section_open($level) {}
    function section_close() {}
    function footnote_open() {}
    function footnote_close() {}

    function php($text, $wrapper='code') {
        $this->doc .= p_xhtml_cached_geshi($text, 'php', $wrapper);
    }
    function html($text, $wrapper='code') {
        $this->doc .= p_xhtml_cached_geshi($text, 'html4strict', $wrapper);
    }

    function rss($url, $params) {}

    function plugin($name, $data) {
        $comments_xhtml_renderer = array_map('trim', explode(',', $this->getConf('comments_xhtml_renderer')));
        $comments_forbid_syntax = array_map('trim', explode(',', $this->getConf('comments_forbid_syntax')));
        $plugin =& plugin_load('syntax',$name);
        if($plugin != null){
            if (in_array($name, $comments_forbid_syntax)) {
                return false;
            } elseif (in_array($name, $comments_xhtml_renderer)) {
                $plugin->render('xhtml',$this,$data);
            } else {
                $plugin->render($this->getFormat(), $this, $data);
            }
        }
    }
}

//Setup VIM: ex: et ts=4 :
