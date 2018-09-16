<?php
/**
 * Provides a syntax for defining arbitrary locations for a "readmore" Link
 * in a blog post.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC')) die();

/**
 * Class syntax_plugin_blogtng_readmore
 */
class syntax_plugin_blogtng_readmore extends DokuWiki_Syntax_Plugin {

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
    function getPType() { return 'normal'; }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    function getSort() { return 300; }

    /**
     * Register the ~~READMORE~~ syntax
     * 
     * @param string $mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~READMORE~~', $mode, 'plugin_blogtng_readmore');
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
        // we only return an empty array here, the only purpose is to place
        // an instruction in the instruction list
        return array();
    }

    /**
     * Renders a simple anchor in XHTML code for the readmore jump point.
     *
     * @param string $mode
     * @param Doku_Renderer $renderer
     * @param array $indata
     * @return bool
     */
    function render($mode, Doku_Renderer $renderer, $indata) {
        if ($mode == 'xhtml') {
            global $ID;
            // render a simple anchor
            $renderer->doc .= '<a name="readmore_'.str_replace(':', '_', $ID).'"></a>';
            return true;
        }

        // unsupported mode
        return false;
    }
}

// vim:ts=4:sw=4:et:
