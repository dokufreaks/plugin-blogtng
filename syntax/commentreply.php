<?php
/**
 * Provides a syntax for referring/replying to comments in own comments.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <gina@foosel.net>
 */

if (!defined('DOKU_INC')) die();

/**
 * Class syntax_plugin_blogtng_commentreply
 */
class syntax_plugin_blogtng_commentreply extends DokuWiki_Syntax_Plugin {

    /**
     * Syntax Type
     *
     * @return string
     */
    function getType() { return 'substition'; }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    function getSort() { return 300; }

    /**
     * Register the comment reply syntax
     * 
     * @param string $mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('@#\d+:', $mode, 'plugin_blogtng_commentreply');
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
        $cid = substr($match, 2, -1);
        return array($cid);
    }

    /**
     * Renders a simple anchor in XHTML code for the readmore jump point.
     *
     * @param string          $mode     output format being rendered
     * @param Doku_Renderer   $renderer the current renderer object
     * @param array           $indata   data created by handler()
     * @return  boolean                 rendered correctly? (however, returned value is not used at the moment)
     */
    function render($mode, Doku_Renderer $renderer, $indata) {
        if ($mode == 'blogtng_comment') {
            list($cid) = $indata;
            /** @var helper_plugin_blogtng_comments $commenthelper */
            $commenthelper = plugin_load('helper', 'blogtng_comments');
            $comment = $commenthelper->comment_by_cid($cid);
            if (!is_object($comment)) return false; // comment does not exist, cid is invalid

            ob_start();
            echo '@<a href="#comment_'.$cid.'" class="wikilink1 blogtng_reply">';
            $comment->tpl_name();
            echo '</a>:';
            $output = ob_get_clean();

            $renderer->doc .= $output;
            return true;
        }

        // unsupported mode
        return false;
    }
}

// vim:ts=4:sw=4:et:
