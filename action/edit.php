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

    var $preact = null;

    function action_plugin_blogtng_edit() {
        $this->entryhelper =& plugin_load('helper', 'blogtng_entry');
        $this->taghelper =& plugin_load('helper', 'blogtng_tags');
        $this->tools =& plugin_load('helper', 'blogtng_tools');
    }

    function register(&$controller) {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_editform_output', array());
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess', array('before'));
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'AFTER', $this, 'handle_action_act_preprocess', array('after'));
    }

    /**
     * Adds additional fields of used by the BlogTNG plugin to the editor.
     */
    function handle_editform_output(&$event, $param) {
        global $ID;

        $pos = $event->data->findElementByAttribute('type','submit');
        if(!$pos) return; // no submit button found, source view
        $pos -= 1;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        $blog = $this->tools->getParam('post/blog');
        if (!$blog) $blog = $this->entryhelper->get_blog();
        $blogs = $this->entryhelper->get_blogs();

        $event->data->insertElement($pos, form_openfieldset(array('_legend' => 'BlogTNG', 'class' => 'edit', 'id' => 'blogtng__edit')));
        $pos += 1;

        $event->data->insertElement($pos, form_makeMenuField('btng[post][blog]', $blogs, $blog, 'Blog', 'blogtng__blog', 'edit'));
        $pos += 1;

        $this->taghelper->load($pid);
        $allowed_tags = $this->_get_allowed_tags();
        $tags = $this->_get_post_tags();
        if (!$tags) $tags = $this->taghelper->tags;
        if (count($allowed_tags) > 0) {
            $event->data->insertElement($pos++, form_makeOpenTag('div'));
            foreach($this->_get_allowed_tags() as $val) {
                $data = array('style' => 'margin-top: 0.3em;');
                if (in_array($val, $tags)) $data['checked'] = 'checked';
                $event->data->insertElement($pos++, form_makeCheckboxField('btng[post][tags][]', $val, $val, '', '', $data));
            }
            $event->data->insertElement($pos++, form_makeCloseTag('div'));
        } else {
            $event->data->insertElement($pos, form_makeTextField('btng[post][tags]', join(', ', $tags), 'Tags', 'blogtng__tags', 'edit'));
            $pos += 1;
        }

        if($this->getConf('editform_set_date')) {
            $postdate = $this->tools->getParam('post/date');
            if($postdate) {
                $YY = $postdate['YY'];
                $MM = $postdate['MM'];
                $DD = $postdate['DD'];
                $hh = $postdate['hh'];
                $mm = $postdate['mm'];
            } else {
                $created = $this->entryhelper->entry['created'];
                if($created) {
                    $YY = strftime('%Y', $created);
                    $MM = strftime('%m', $created);
                    $DD = strftime('%d', $created);
                    $hh = strftime('%H', $created);
                    $mm = strftime('%M', $created);
                } else {
                    $time = mktime();
                    $YY = strftime('%Y', $time);
                    $MM = strftime('%m', $time);
                    $DD = strftime('%d', $time);
                    $hh = strftime('%H', $time);
                    $mm = strftime('%M', $time);
                }
            }

            $event->data->insertElement($pos, form_makeTextField('btng[post][date][YY]', $YY, 'YYYY', 'blogtng__date_YY', 'edit', array('maxlength'=>4)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][MM]', $MM, 'MM', 'blogtng__date_MM', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][DD]', $DD, 'DD', 'blogtng__date_DD', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][hh]', $hh, 'hh', 'blogtng__date_hh', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][mm]', $mm, 'mm', 'blogtng__date_mm', 'edit', array('maxlength'=>2)));
            $pos += 1;
        }

        $event->data->insertElement($pos, form_makeMenuField('btng[post][commentstatus]', array('enabled', 'closed', 'disabled'), $this->entryhelper->entry['commentstatus'], $this->getLang('commentstatus'), 'blogtng__commentstatus', 'edit'));
        $pos += 1;

        $event->data->insertElement($pos, form_closefieldset());
    }

    /**
     * Save the blog related meta data of a page to the sqlite DB
     */
    function handle_action_act_preprocess(&$event, $param) {
        list($type) = $param;
        switch($type) {
            case 'before':
                if (is_array($event->data)) {
                    list($this->preact) = array_keys($event->data);
                } else {
                    $this->preact = $event->data;
                }
                break;

            case 'after':
                global $ID;
                global $ACT;

                if ($this->preact != 'save' || $event->data != 'show') {
                    return;
                }

                // does the page still exist? might be a deletion
                if(!page_exists($ID)) return;

                $blog = $this->tools->getParam('post/blog');
                $blogs = $this->entryhelper->get_blogs();
                if (!in_array($blog, $blogs)) $blog = null;

                $pid = md5($ID);

                $this->entryhelper->load_by_pid($pid);
                $this->entryhelper->entry['blog'] = $blog;
                $this->entryhelper->entry['commentstatus'] = $this->tools->getParam('post/commentstatus');

                if (empty($this->entryhelper->entry['page'])) {

                    $this->entryhelper->entry['page'] = $ID;

                }

                // allow to override created date
                if($this->tools->getParam('post/date') && $this->getConf('editform_set_date')) {
                    foreach(array('hh', 'mm', 'MM', 'DD') as $key) {
                        $_REQUEST['btng']['post']['date'][$key] = ($_REQUEST['btng']['post']['date'][$key]{0} == 0) ? $_REQUEST['btng']['post']['date'][$key]{1} : $_REQUEST['btng']['post']['date'][$key];
                    }
                    $time = mktime($this->tools->getParam('post/date/hh'),
                                   $this->tools->getParam('post/date/mm'),
                                   0,
                                   $this->tools->getParam('post/date/MM'),
                                   $this->tools->getParam('post/date/DD'),
                                   $this->tools->getParam('post/date/YY'));
                    $this->entryhelper->entry['created'] = $time;
                }

                $this->entryhelper->save();

                $tags = $this->_get_post_tags();
                $allowed_tags = $this->_get_allowed_tags();
                if (count($allowed_tags) > 0) {
                    foreach($tags as $n => $tag) {
                        if (!in_array($tag, $allowed_tags)) {
                            unset($tags[$n]);
                        }
                    }
                }
                $this->taghelper->load($pid);
                $this->taghelper->set($tags);
                $this->taghelper->save();

                break;
        }
    }

    function _split_tags($tags) {
        return array_filter(preg_split('/\s*,\s*/', $tags));
    }

    function _get_allowed_tags() {
        return $this->_split_tags($this->getConf('tags'));
    }

    function _get_post_tags() {
        $tags = $this->tools->getParam('post/tags');
        if (!is_array($tags)) {
            $tags = $this->_split_tags($tags);
        }
        return $tags;
    }
}

// vim:ts=4:sw=4:et:
