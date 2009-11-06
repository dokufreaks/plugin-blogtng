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
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/../INFO');
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
        $blog = (isset($_REQUEST['btngb'])) ? $_REQUEST['btngb'] : $this->entryhelper->get_blog();
        $blogs = $this->entryhelper->get_blogs();

        $event->data->insertElement($pos, form_openfieldset(array('_legend' => 'BlogTNG', 'class' => 'edit', 'id' => 'blogtng__edit')));
        $pos += 1;

        $event->data->insertElement($pos, form_makeMenuField('btngb', $blogs, $blog, 'Blog', 'blogtng__blog', 'edit'));
        $pos += 1;

        $this->taghelper->load($pid);
        $tags = (isset($_REQUEST['btngt'])) ? $_REQUEST['btngt'] : join(', ', $this->taghelper->tags);
        $event->data->insertElement($pos, form_makeTextField('btngt', $tags, 'Tags', 'blogtng__tags', 'edit'));
        $pos += 1;

        if($this->getConf('editform_set_date')) {
            if($_REQUEST['btngd']) {
                $YY = $_REQUEST['btngd']['YY'];
                $MM = $_REQUEST['btngd']['MM'];
                $DD = $_REQUEST['btngd']['DD'];
                $hh = $_REQUEST['btngd']['hh'];
                $mm = $_REQUEST['btngd']['mm'];
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

            $event->data->insertElement($pos, form_makeTextField('btngd[YY]', $YY, 'YYYY', 'blogtng__date_YY', 'edit', array('maxlength'=>4)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btngd[MM]', $MM, 'MM', 'blogtng__date_MM', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btngd[DD]', $DD, 'DD', 'blogtng__date_DD', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btngd[hh]', $hh, 'hh', 'blogtng__date_hh', 'edit', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btngd[mm]', $mm, 'mm', 'blogtng__date_mm', 'edit', array('maxlength'=>2)));
            $pos += 1;
        }

        $event->data->insertElement($pos, form_closefieldset());
    }

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

                $blog = $_REQUEST['btngb'];
                $blogs = $this->entryhelper->get_blogs();
                if (!in_array($blog, $blogs)) $blog = null;

                $pid = md5($ID);
                $this->entryhelper->load_by_pid($pid);
                $this->entryhelper->entry['blog'] = $blog;

                // allow to override created date
                if(isset($_REQUEST['btngd']) && $this->getConf('editform_set_date')) {
                    foreach(array('hh', 'mm', 'MM', 'DD') as $key) {
                        $_REQUEST['btngd'][$key] = ($_REQUEST['btngd'][$key]{0} == 0) ? $_REQUEST['btngd'][$key]{1} : $_REQUEST['btngd'][$key];
                    }
                    $time = mktime($_REQUEST['btngd']['hh'],
                                   $_REQUEST['btngd']['mm'],
                                   0,
                                   $_REQUEST['btngd']['MM'],
                                   $_REQUEST['btngd']['DD'],
                                   $_REQUEST['btngd']['YY']);
                    $this->entryhelper->entry['created'] = $time;
                }

                $this->entryhelper->save();

                $tags = $_REQUEST['btngt'];
                $this->taghelper->load($pid);
                $this->taghelper->set(explode(',', $tags));
                $this->taghelper->save();

                break;
        }
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
