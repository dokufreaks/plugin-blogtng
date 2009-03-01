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
     * return some info
     */
    function getInfo(){
        return confToHash(dirname(__FILE__).'/../INFO');
    }

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
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
