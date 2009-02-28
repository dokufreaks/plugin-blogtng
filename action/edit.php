<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blogtng_edit extends DokuWiki_Action_Plugin{

    var $entryhelper = null;

    function action_plugin_blogtng_edit() {
        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
    }

    function register(&$controller) {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_editform_output', array());
    }

    function handle_editform_output(&$event, $param) {
        $pos = $event->data->findElementByAttribute('type','submit');
        if(!$pos) return; // no submit button found, source view

        // fIXME fetch templates
        //$blog = $this->get_blog_by_pid($pid);
        //$blogs= $this->get_blogs();
        $blog = 'blog2';
        $blogs = array('blog1', 'blog2', 'blog3');
        $event->data->insertElement($pos, form_makeMenuField('blog', $blogs, $blog, 'Blog', 'blogtng__template', 'edit'));

        // FIXME fetch tags
        //$tags = $this->get_tags_by_pid($pid);
        $tags = 'tag1, tag2, tag3';
        $event->data->insertElement($pos, form_makeTextField('tags', $tags, 'Tags', 'blogtng__tags', 'edit'));
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
