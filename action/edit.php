<?php

use dokuwiki\Form\Form;

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */

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
        //create extra form fields below DokuWiki's edit window
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'extraFieldsBelowEditform_old', array());
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'extraFieldsBelowEditform', array());
        //try to save the submitted extra form data
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'saveSubmittedFormData', array('before'));
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'AFTER', $this, 'saveSubmittedFormData', array('after'));
    }

    /**
     * Adds additional fields of used by the BlogTNG plugin to the editor.
     *
     * @param Doku_Event $event
     * @param $param
     *
     * @deprecated 2022-07-31
     */
    function extraFieldsBelowEditform_old(Doku_Event $event, $param) {
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
        $blogs = $this->entryhelper->getAllBlogs();

        $event->data->insertElement($pos, form_openfieldset(array('_legend' => 'BlogTNG', 'class' => 'edit', 'id' => 'blogtng__edit')));
        $pos += 1;

        $event->data->insertElement($pos, form_makeMenuField('btng[post][blog]', $blogs, $blog, 'Blog', 'blogtng__blog', 'edit'));
        $pos += 1;

        $this->taghelper->load($pid);

        $tags = $this->_get_post_tags();
        if ($tags === false) $tags = $this->taghelper->getTags();
        if (!$tags && $isNotExistingBlog) $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($this->getConf('default_tags'));

        $allowed_tags = $this->getAllowedTags();
        if (count($allowed_tags) > 0) {
            $event->data->insertElement($pos++, form_makeOpenTag('div', array('class' => 'blogtng__tags_checkboxes')));
            foreach($this->getAllowedTags() as $val) {
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
     * Adds additional fields of used by the BlogTNG plugin to the editor.
     *
     * @param Doku_Event $event
     * @param $param
     */
    function extraFieldsBelowEditform(Doku_Event $event, $param) {
        global $ID, $INFO, $INPUT;
        /** @var Form $form */
        $form = $event->data;
        $pos = $form->findPositionByAttribute('type','submit');
        if(!$pos) {
            return; // no submit button found, source view
        }
        $pos -= 1;

        $pid = md5($ID);
        $this->entryhelper->load_by_pid($pid);
        $isNotExistingBlog = $this->entryhelper->entry['blog'] === null;

        $form->addFieldsetOpen('BlogTNG', $pos++)
            ->id('blogtng__edit')
            ->addClass('edit');

        $blog = $this->entryhelper->get_blog();
        $blog = $INPUT->post->str('post-blog', $blog);
        if (!$blog && !$INFO['exists']) {
            $blog = $this->getConf('default_blog');
        }
        $blogs = $this->entryhelper->getAllBlogs();

        $form->addDropdown('post-blog', $blogs, 'Blog', $pos++)
            ->id('blogtng__blog')
            ->addClass('edit')
            ->val($blog);


        $this->taghelper->load($pid);
        $tags = $this->getPostedTags();
        if (empty($tags)) {
            $tags = $this->taghelper->getTags();
        }
        if (!$tags && $isNotExistingBlog) {
            $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($this->getConf('default_tags'));
        }

        $allowedTags = $this->getAllowedTags();
        if (count($allowedTags) > 0) {
            $form->addTagOpen('div', $pos++)
                ->addClass('blogtng__tags_checkboxes');
            foreach($this->getAllowedTags() as $val) {
                $checkbox = $form->addCheckbox('post-tags[]', $val, $pos++)
                    ->val($val);
                if(in_array($val, $tags)) {
                    $checkbox->attr('checked', 'checked');
                }
            }
            $form->addTagClose('div', $pos++);
        } else {
            $form->addTextInput('post-tags', 'Tags', $pos++)
                ->id('blogtng__tags')
                ->addClass('edit')
                ->val(join(', ', $tags));
        }

        $commentstatus = $this->entryhelper->entry['commentstatus'];
        $commentstatus = $INPUT->post->str('post-commentstatus', $commentstatus);
        if(!$commentstatus) {
            $commentstatus = $this->getConf('default_commentstatus');
        }

        $form->addDropdown('post-commentstatus', array('enabled', 'closed', 'disabled'), $this->getLang('commentstatus'), $pos++)
            ->id('blogtng__commentstatus')
            ->addClass('edit')
            ->val($commentstatus);

        if($this->getConf('editform_set_date')) {
            if ($INPUT->post->has('post-date')) {
                $date = $INPUT->post->arr('post-date');
                $YY = (int) $date['YY'];
                $MM = (int) $date['MM'];
                $DD = (int) $date['DD'];
                $hh = (int) $date['hh'];
                $mm = (int) $date['mm'];
            } else {
                $created = $this->entryhelper->entry['created'];
                if($created) {
                    $YY = date('Y', $created); //strftime('%Y', $created);
                    $MM = date('m', $created);//strftime('%m', $created);
                    $DD = date('d', $created);//strftime('%d', $created);
                    $hh = date('H', $created);//strftime('%H', $created);
                    $mm = date('i', $created);//strftime('%M', $created);
                } else {
                    $time = time();
                    $YY = date('Y', $time);//strftime('%Y', $time);
                    $MM = date('m', $time);//strftime('%m', $time);
                    $DD = date('d', $time);//strftime('%d', $time);
                    $hh = date('H', $time);//strftime('%H', $time);
                    $mm = date('i', $time);//strftime('%M', $time);
                }
            }

            $form->addTextInput('post-date[YY]', 'YYYY', $pos++)
                ->id('blogtng__date_YY')->addClass('edit btng__date_YY')
                ->val($YY)->attr('maxlength', 4);
            $form->addTextInput('post-date[MM]', 'MM', $pos++)
                ->id('blogtng__date_MM')->addClass('edit btng__date')
                ->val($MM)->attr('maxlength', 2);
            $form->addTextInput('post-date[DD]', 'DD', $pos++)
                ->id('blogtng__date_DD')->addClass('edit btng__date')
                ->val($DD)->attr('maxlength', 2);
            $form->addTextInput('post-date[hh]', 'hh', $pos++)
                ->id('blogtng__date_hh')->addClass('edit btng__date')
                ->val($hh)->attr('maxlength', 2);
            $form->addTextInput('post-date[mm]', 'mm', $pos++)
                ->id('blogtng__date_mm')->addClass('edit btng__date')
                ->val($mm)->attr('maxlength', 2);
        }

        $form->addFieldsetClose($pos);
    }

    /**
     * Save the blog related meta data of a page to the sqlite DB
     *
     * @param Doku_Event $event
     * @param $param
     */
    function saveSubmittedFormData(Doku_Event $event, $param) {
        global $INPUT;
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

            $isNew = $INPUT->post->has('post-blog'); //deprecated 2022-07-31, temporary workaround

            if($isNew) {
                $blog = $INPUT->post->str('post-blog');
            } else {
                //deprecated 2022-07-31
                $blog = $this->tools->getParam('post/blog');
            }
            $blogs = $this->entryhelper->getAllBlogs();
            if (!in_array($blog, $blogs)) {
                $blog = null;
            }

            if($blog === null) {
                $this->entryhelper->poke();
            } else {
                $pid = md5($ID);

                $this->entryhelper->load_by_pid($pid);

                $entry = $this->collectInfoForEntry();
                $this->entryhelper->set($entry);

                $this->entryhelper->entry['blog'] = $blog;
                if($isNew) {
                    $this->entryhelper->entry['commentstatus'] = $INPUT->post->str('post-commentstatus');
                } else {
                    //deprecated 2022-07-31
                    $this->entryhelper->entry['commentstatus'] = $this->tools->getParam('post/commentstatus');
                }


                if (empty($this->entryhelper->entry['page'])) {
                    $this->entryhelper->entry['page'] = $ID;
                }

                // allow to override created date
                if($this->getConf('editform_set_date')) {
                    if($isNew && $INPUT->post->has('post-date')) {
                        $date = $INPUT->post->arr('post-date');
                        $time = mktime(
                            (int) $date['hh'],
                            (int) $date['mm'],
                            0,
                            (int) $date['MM'],
                            (int) $date['DD'],
                            (int) $date['YY']
                        );
                        $this->entryhelper->entry['created'] = $time;
                    }
                    //deprecated 2022-07-31
                    if(!$isNew && $this->tools->getParam('post/date')) {
                        foreach(array('hh', 'mm', 'MM', 'DD') as $key) {
                            $_REQUEST['btng']['post']['date'][$key] = ($_REQUEST['btng']['post']['date'][$key][0] == 0) ? $_REQUEST['btng']['post']['date'][$key][1] : $_REQUEST['btng']['post']['date'][$key];
                        }
                        $time = mktime($this->tools->getParam('post/date/hh'),
                            $this->tools->getParam('post/date/mm'),
                            0,
                            $this->tools->getParam('post/date/MM'),
                            $this->tools->getParam('post/date/DD'),
                            $this->tools->getParam('post/date/YY'));
                        $this->entryhelper->entry['created'] = $time;
                    }
                }


                $this->entryhelper->save();

                if($isNew) {
                    $tags = $this->getPostedTags();
                } else {
                    $tags = $this->_get_post_tags();
                    if ($tags === false) $tags = array();
                }

                $allowed_tags = $this->getAllowedTags();
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
     * Return the configured allowed tags as an array, if empty all tags are allowed
     *
     * @return array
     */
    private function getAllowedTags() {
        return helper_plugin_blogtng_tools::filterExplodeCSVinput($this->getConf('tags'));
    }

    /**
     * Return the tags received in the current $_REQUEST
     * @deprecated 2022-07-31
     * @return array|false
     */
    private function _get_post_tags() {
        $tags = $this->tools->getParam('post/tags');
        if ($tags === false) return false;
        if (!is_array($tags)) {
            $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($tags);
        }
        return $tags;
    }

    /**
     * Return the tags received in the current request
     *
     * @return array
     */
    private function getPostedTags() {
        global $INPUT;
        if(count($this->getAllowedTags()) > 0) {
            $tags = $INPUT->post->arr('post-tags');
        } else {
            $tags = $INPUT->post->str('post-tags');
            $tags = helper_plugin_blogtng_tools::filterExplodeCSVinput($tags);
        }
        return $tags;
    }

    /**
     * Gather general info for a blogentry
     *
     * @return array
     */
    protected function collectInfoForEntry() {
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
        return array(
            'page' => $ID,
            'title' => p_get_metadata($ID, 'title'),
            'image' => p_get_metadata($ID, 'relation firstimage'),
            'created' => $date_created,
            'lastmod' => (!$date_modified) ? $date_created : $date_modified,
            'login' => $login,
            'author' => ($userdata) ? $userdata['name'] : $login,
            'mail' => ($userdata) ? $userdata['mail'] : '',
        );
    }
}
