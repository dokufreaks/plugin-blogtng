<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blogtng_feed extends DokuWiki_Action_Plugin{

    var $entryhelper = null;
    var $tools = null;

    var $defaultConf = array(
        'sortby' => 'created',
        'sortorder' => 'DESC',
    );

    function action_plugin_blogtng_feed() {
        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
        $this->tools =& plugin_load('helper', 'blogtng_tools');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function register(&$controller) {
        $controller->register_hook('FEED_OPTS_POSTPROCESS', 'AFTER', $this, 'handle_opts_postprocess', array());
        $controller->register_hook('FEED_MODE_UNKNOWN', 'BEFORE', $this, 'handle_mode_unknown', array ());
        $controller->register_hook('FEED_ITEM_ADD', 'BEFORE', $this, 'handle_item_add', array());
    }

    function handle_opts_postprocess(&$event, $param) {
        $opt =& $event->data['opt'];
        if ($opt['feed_mode'] != 'blogtng') return;

        $opt['blog'] = $_REQUEST['blog'];
        $opt['sortby'] = $_REQUEST['sortby'];
        $opt['sortorder'] = $_REQUEST['sortorder'];
    }

    function handle_mode_unknown(&$event, $param) {
        $opt = $event->data['opt'];
        if ($opt['feed_mode'] != 'blogtng') return;

        $event->preventDefault();
        $event->data['data'] = array();
        $conf = array(
            'blog' => array($opt['blog']),
            'sortby' => $opt['sortby'],
            'sortorder' => $opt['sortorder'],
            'limit' => $opt['items'],
            'offset' => 0,
        );
        dbglog("CONF preclean: " . print_r($conf, true));
        $this->tools->cleanConf($conf);
        dbglog("CONF postclean: " . print_r($conf, true));
        $conf = array_merge($conf, $this->defaultConf);
        $posts = $this->entryhelper->get_posts($conf);
        foreach ($posts as $row) {
            $this->entryhelper->load_by_row($row);
            $event->data['data'][] = array(
                'id' => $row['page'],
                'title' => $row['title'],
                'pid' => $row['pid'],
            );
        }
    }

    function handle_item_add(&$event, $param) {
        $opt = $event->data['opt'];
        $ditem = $event->data['ditem'];
        if (!$opt['feed_mode'] == 'blogtng') return;
        if (!$opt['item_content'] == 'html') return;
        if (!$opt['link_to'] == 'current') return;

        // don't add drafts to the feed
        if(p_get_metadata($ditem['id'], 'type') == 'draft') {
            $event->preventDefault();
            return;
        }

        // retrieve first heading from page instructions
        $ins = p_cached_instructions(wikiFN($ditem['id']));
        $headers = array_filter($ins, array($this, '_filterHeaders'));
        $headingIns = array_shift($headers);
        $firstheading = $headingIns[1][0];

        // strip first heading and replace item title
        $event->data['item']->description = preg_replace('#[^\n]*?>\s*?' . preg_quote(hsc($firstheading), '#') . '\s*?<.*\n#', '', $event->data['item']->description, 1);
        $event->data['item']->title = $ditem['title'];

    }

    /**
     * Returns true if $entry is a valid header instruction, false otherwise.
     *
     * @author Gina Häußge <osd@foosel.net>
     */
    function _filterHeaders($entry) {
        // normal headers
        if (is_array($entry) && $entry[0] == 'header' && count($entry) == 3 && is_array($entry[1]) && count($entry[1]) == 3)
            return true;

        // no known header
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
