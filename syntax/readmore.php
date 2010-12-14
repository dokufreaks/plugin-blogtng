<?php
/**
 * Provides a syntax for defining arbitrary locations for a "readmore" Link
 * in a blog post.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_blogtng_readmore extends DokuWiki_Syntax_Plugin {

    function getType() { return 'substition'; }
    function getPType() { return 'normal'; }
    function getSort() { return 300; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~READMORE~~', $mode, 'plugin_blogtng_readmore');
    }

    function handle($match, $state, $pos, &$handler) {
        // we only return an empty array here, the only purpose is to place
        // an instruction in the instruction list
        return array();
    }

    /**
     * Renders a simple anchor in XHTML code for the readmore jump point.
     */
    function render($mode, &$renderer, $indata) {
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
