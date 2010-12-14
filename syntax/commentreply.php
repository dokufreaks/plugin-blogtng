<?php
/**
 * Provides a syntax for referring/replying to comments in own comments.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <gina@foosel.net>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_blogtng_commentreply extends DokuWiki_Syntax_Plugin {

    function getType() { return 'substition'; }
    function getSort() { return 300; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('@#\d+:', $mode, 'plugin_blogtng_commentreply');
    }

    function handle($match, $state, $pos, &$handler) {
        $cid = substr($match, 2, -1);
        return array($cid);
    }

    /**
     * Renders a simple anchor in XHTML code for the readmore jump point.
     */
    function render($mode, &$renderer, $indata) {
        if ($mode == 'blogtng_comment') {
            list($cid) = $indata;
            $commenthelper =& plugin_load('helper', 'blogtng_comments');
            $comment = $commenthelper->comment_by_cid($cid);

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
