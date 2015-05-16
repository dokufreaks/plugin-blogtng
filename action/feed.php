<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Class action_plugin_blogtng_feed
 */
class action_plugin_blogtng_feed extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_entry */
    var $entryhelper = null;
    /** @var helper_plugin_blogtng_tools */
    var $tools = null;

    var $defaultConf = array(
        'sortby' => 'created',
        'sortorder' => 'DESC',
    );

    function __construct() {
        $this->entryhelper = plugin_load('helper', 'blogtng_entry');
        $this->tools = plugin_load('helper', 'blogtng_tools');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('FEED_OPTS_POSTPROCESS', 'AFTER', $this, 'handle_opts_postprocess', array());
        $controller->register_hook('FEED_MODE_UNKNOWN', 'BEFORE', $this, 'handle_mode_unknown', array ());
        $controller->register_hook('FEED_ITEM_ADD', 'BEFORE', $this, 'handle_item_add', array());
    }

    /**
     * Parses blogtng specific feed parameters if the feed mode is 'blogtng'.
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return void
     */
    function handle_opts_postprocess(Doku_Event $event, $param) {
        $opt =& $event->data['opt'];
        if ($opt['feed_mode'] != 'blogtng') return;

        $opt['blog'] = $_REQUEST['blog'];
        $opt['tags'] = $_REQUEST['tags'];
        $opt['sortby'] = $_REQUEST['sortby'];
        $opt['sortorder'] = $_REQUEST['sortorder'];
    }

    /**
     * Handles the 'blogtng' feed mode and prevents the default action (recents).
     * Retrieves all blog posts as defined by blog and tags parameters, orders
     * and limits them as requested and returns them inside the event.
     *
     * @param Doku_Event   $event the event as triggered in feed.php
     * @param array        $param empty
     * @return void
     */
    function handle_mode_unknown(Doku_Event $event, $param) {
        $opt = $event->data['opt'];
        if ($opt['feed_mode'] != 'blogtng') return;

        $event->preventDefault();
        $event->data['data'] = array();
        $conf = array(
            'blog' => explode(',', $opt['blog']),
            'tags' => ($opt['tags'] ? explode(',', $opt['tags']) : null),
            'sortby' => $opt['sortby'],
            'sortorder' => $opt['sortorder'],
            'limit' => $opt['items'],
            'offset' => 0,
        );
        $this->tools->cleanConf($conf);
        $conf = array_merge($conf, $this->defaultConf);
        $posts = $this->entryhelper->get_posts($conf);
        foreach ($posts as $row) {
            $event->data['data'][] = array(
                'id' => $row['page'],
                'date' => $row['created'],
                'user' => $row['author'],
                'entry' => $row,
            );
        }
    }

    /**
     * Preprocesses a blog post as its added to the feed. Makes sure to
     * remove the first header from the text (otherwise it would be doubled)
     * and takes care of presentation as configured via template.
     *
     * @param Doku_Event $event the event as triggered in feed.php
     * @param array      $param empty
     * @return void
     */
    function handle_item_add(Doku_Event $event, $param) {
        $opt = $event->data['opt'];
        $ditem = $event->data['ditem'];
        if ($opt['feed_mode'] !== 'blogtng') return;
        if ($opt['item_content'] !== 'html') return;
        if ($opt['link_to'] !== 'current') return;

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

        $this->entryhelper->load_by_row($ditem['entry']);

        ob_start();
        $this->entryhelper->tpl_content($ditem['entry']['blog'], 'feed');
        $output = ob_get_contents();
        ob_end_clean();
        // make URLs work when canonical is not set, regexp instead of rerendering!
        global $conf;
        if(!$conf['canonical']){
            $base = preg_quote(DOKU_REL,'/');
            $output = preg_replace('/(<a href|<img src)="('.$base.')/s','$1="'.DOKU_URL,$output);
        }

        // strip first heading and replace item title
        $event->data['item']->description = preg_replace('#[^\n]*?>\s*?' . preg_quote(hsc($firstheading), '#') . '\s*?<.*\n#', '', $output, 1);
        $event->data['item']->title = $ditem['entry']['title'];

        //only supported by RSS 0.91 and RSS 2.0
        if($ditem['entry']['commentstatus'] !== 'disabled') {
            $event->data['item']->comments = $event->data['item']->link.'#the__comments';
        }
    }

    /**
     * Returns true if $entry is a valid header instruction, false otherwise.
     *
     * @author Gina Häußge <osd@foosel.net>
     *
     * @param $entry
     * @return bool
     */
    function _filterHeaders($entry) {
        // normal headers
        if (is_array($entry) && $entry[0] == 'header' && count($entry) == 3 && is_array($entry[1]) && count($entry[1]) == 3)
            return true;

        // no known header
        return false;
    }
}
// vim:ts=4:sw=4:et:
