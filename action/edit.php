<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class action_plugin_blogtng_edit extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_entry */
    var $entryhelper = null;
    /** @var  helper_plugin_blogtng_tags */
    var $taghelper;
    /** @var  helper_plugin_blogtng_tools */
    var $tools;

    var $preact = null;

    /**
     * Constructor
     */
    function __construct() {
        $this->entryhelper = plugin_load('helper', 'blogtng_entry');
        $this->taghelper = plugin_load('helper', 'blogtng_tags');
        $this->tools = plugin_load('helper', 'blogtng_tools');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_editform_output', array());
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess', array('before'));
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'AFTER', $this, 'handle_action_act_preprocess', array('after'));
    }

    /**
     * Adds additional fields of used by the BlogTNG plugin to the editor.
     *
     * @param Doku_Event $event
     * @param $param
     */
    function handle_editform_output(Doku_Event $event, $param) {
        global $ID, $INFO;

        $pos = $event->data->findElementByAttribute('type','submit');
        if(!$pos) return; // no submit button found, source view
        $pos -= 1;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        $isNotExistingBlog = $this->entryhelper->entry['blog'] === null;

        $blog = $this->tools->getParam('post/blog');
        if (!$blog) $blog = $this->entryhelper->get_blog();
        if (!$blog && !$INFO['exists']) $blog = $this->getConf('default_blog');
        $blogs = $this->entryhelper->get_blogs();

        $event->data->insertElement($pos, form_openfieldset(array('_legend' => 'BlogTNG', 'class' => 'edit', 'id' => 'blogtng__edit')));
        $pos += 1;

        $event->data->insertElement($pos, form_makeMenuField('btng[post][blog]', $blogs, $blog, 'Blog', 'blogtng__blog', 'edit'));
        $pos += 1;

        $this->taghelper->load($pid);

        $tags = $this->_get_post_tags();
        if ($tags === false) $tags = $this->taghelper->getTags();
        if (!$tags && $isNotExistingBlog) $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($this->getConf('default_tags'));

        $allowed_tags = $this->_get_allowed_tags();
        if (count($allowed_tags) > 0) {
            $event->data->insertElement($pos++, form_makeOpenTag('div', array('class' => 'blogtng__tags_checkboxes')));
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

        $commentstatus = $this->tools->getParam('post/commentstatus');
        if(!$commentstatus) $commentstatus = $this->entryhelper->entry['commentstatus'];
        if(!$commentstatus) $commentstatus = $this->getConf('default_commentstatus');


        $event->data->insertElement($pos, form_makeMenuField('btng[post][commentstatus]', array('enabled', 'closed', 'disabled'), $commentstatus, $this->getLang('commentstatus'), 'blogtng__commentstatus', 'edit'));
        $pos += 1;

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
                    $time = time();
                    $YY = strftime('%Y', $time);
                    $MM = strftime('%m', $time);
                    $DD = strftime('%d', $time);
                    $hh = strftime('%H', $time);
                    $mm = strftime('%M', $time);
                }
            }

            $event->data->insertElement($pos, form_makeTextField('btng[post][date][YY]', $YY, 'YYYY', 'blogtng__date_YY', 'edit btng__date_YY', array('maxlength'=>4)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][MM]', $MM, 'MM', 'blogtng__date_MM', 'edit btng__date', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][DD]', $DD, 'DD', 'blogtng__date_DD', 'edit btng__date', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][hh]', $hh, 'hh', 'blogtng__date_hh', 'edit btng__date', array('maxlength'=>2)));
            $pos += 1;
            $event->data->insertElement($pos, form_makeTextField('btng[post][date][mm]', $mm, 'mm', 'blogtng__date_mm', 'edit btng__date', array('maxlength'=>2)));
            $pos += 1;
        }

        $event->data->insertElement($pos, form_closefieldset());
    }

    /**
     * Save the blog related meta data of a page to the sqlite DB
     *
     * @param Doku_Event $event
     * @param $param
     */
    function handle_action_act_preprocess(Doku_Event $event, $param) {
        list($type) = $param;

        if (is_array($event->data)) {
            list($act) = array_keys($event->data);
        } else {
            $act = $event->data;
        }

        if ($type === 'before' && $act === 'save') {
            $this->preact = 'save';
        // Before Greebo, intercept the after type where $act is 'show'.
        // In Greebo and later, the before type is triggered again but with action 'draftdel'.
        } else if ($this->preact === 'save' && (($act === 'show' && $type === 'after') || ($act === 'draftdel' && $type === 'before'))) {
            global $ID;

            // does the page still exist? might be a deletion
            if(!page_exists($ID)) return;

            $blog = $this->tools->getParam('post/blog');
            $blogs = $this->entryhelper->get_blogs();
            if (!in_array($blog, $blogs)) $blog = null;

            if($blog === null) {
                $this->entryhelper->poke();
            } else {
                $pid = md5($ID);

                $this->entryhelper->load_by_pid($pid);

                $entry = $this->_collectInfoForEntry();
                $this->entryhelper->set($entry);

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
                if ($tags === false) $tags = array();
                $allowed_tags = $this->_get_allowed_tags();
                if (count($allowed_tags) > 0) {
                    foreach($tags as $n => $tag) {
                        if (!in_array($tag, $allowed_tags)) {
                            unset($tags[$n]);
                        }
                    }
                }
                $this->taghelper->load($pid);
                $this->taghelper->setTags($tags);
                $this->taghelper->save();
            }
        }
    }

    /**
     * Return the configured allowed tags as an array
     * 
     * @return array
     */
    private function _get_allowed_tags() {
        return helper_plugin_blogtng_tools::filterExplodeCSVinput($this->getConf('tags'));
    }

    /**
     * Return the tags received in the current $_REQUEST
     * 
     * @return array|mixed
     */
    private function _get_post_tags() {
        $tags = $this->tools->getParam('post/tags');
        if ($tags === false) return $tags;
        if (!is_array($tags)) {
            $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($tags);
        }
        return $tags;
    }

    /**
     * Gather general info for a blogentry
     *
     * @return array
     */
    protected function _collectInfoForEntry() {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $ID;

        // fetch author info
        $login = $this->entryhelper->entry['login'];
        if(!$login) $login = p_get_metadata($ID, 'user');
        if(!$login) $login = $_SERVER['REMOTE_USER'];

        $userdata = false;
        if($login) {
            if($auth != null) {
                $userdata = $auth->getUserData($login);
            }
        }

        // fetch dates
        $date_created = p_get_metadata($ID, 'date created');
        $date_modified = p_get_metadata($ID, 'date modified');

        // prepare entry ...
        $entry = array(
            'page' => $ID,
            'title' => p_get_metadata($ID, 'title'),
            'image' => p_get_metadata($ID, 'relation firstimage'),
            'created' => $date_created,
            'lastmod' => (!$date_modified) ? $date_created : $date_modified,
            'login' => $login,
            'author' => ($userdata) ? $userdata['name'] : $login,
            'mail' => ($userdata) ? $userdata['mail'] : '',
        );
        return $entry;
    }
}

// vim:ts=4:sw=4:et:
