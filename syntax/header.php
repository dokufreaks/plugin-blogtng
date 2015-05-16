<?php
/**
 * Provides a header instruction which renders a permalink to the blog post
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <gina@foosel.net>
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC')) die();

/**
 * Class syntax_plugin_blogtng_header
 */
class syntax_plugin_blogtng_header extends DokuWiki_Syntax_Plugin {

    /**
     * Syntax Type
     *
     * @return string
     */
    function getType() {
        return 'formatting';
    }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    function getSort() {
        return 50;
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        // this is a syntax plugin that doesn't offer any syntax, so there's nothing to handle by the parser
    }

    /**
     * Renders a permalink header.
     *
     * Code heavily copied from the header renderer from inc/parser/xhtml.php, just
     * added an href parameter to the anchor tag linking to the wikilink.
     *
     * @param   $mode     string              output format being rendered
     * @param   $renderer Doku_Renderer reference to the current renderer object
     * @param   $indata   array               data created by handler()
     * @return  boolean                       rendered correctly?
     */
    function render($mode, Doku_Renderer $renderer, $indata) {
        global $ID;
        list($text, $level) = $indata;

        if ($mode == 'xhtml') {
            /** @var $renderer Doku_Renderer_xhtml */
            $hid = $renderer->_headerToLink($text,true);

            //only add items within configured levels
            $renderer->toc_additem($hid, $text, $level);

            // write the header
            $renderer->doc .= DOKU_LF.'<h'.$level.'><a name="'.$hid.'" id="'.$hid.'" href="'.wl($ID).'">';
            $renderer->doc .= $renderer->_xmlEntities($text);
            $renderer->doc .= "</a></h$level>".DOKU_LF;

            return true;
        }

        // unsupported $mode
        return false;
    }
}

// vim:ts=4:sw=4:et:
