<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

/**
 * Class action_plugin_blogtng_new
 */
class action_plugin_blogtng_new extends DokuWiki_Action_Plugin{

    /** @var helper_plugin_blogtng_comments */
    protected $commenthelper = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->commenthelper = plugin_load('helper', 'blogtng_comments');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleNewBlogFormData', array());
    }

    /**
     * Handles input from the newform and redirects to the edit mode
     *
     * @author Andreas Gohr <gohr@cosmocode.de>
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty array as passed to register_hook()
     * @return bool
     */
    public function handleNewBlogFormData(Doku_Event $event, $param) {
        global $TEXT, $INPUT;
        global $ID;

        if($event->data != 'btngnew') return true;

        /** @var helper_plugin_blogtng_tools $tools */
        $tools = plugin_load('helper', 'blogtng_tools');
        if(!$INPUT->str('new-title')){
            msg($this->getLang('err_notitle'),-1);
            $event->data = 'show';
            return true;
        }

        $newId = $tools->mkpostid($INPUT->str('new-format'), $INPUT->str('new-title'));
         if ($ID != $newId) {
             // first submission is 'post', next is 'get'.
            $urlparams = [
                'do' => 'btngnew',
                'post-blog' => $INPUT->post->str('post-blog'),
                'post-tags' => $INPUT->post->str('post-tags'),
                'post-commentstatus' => $INPUT->post->str('post-commentstatus'),
                'new-format' => $INPUT->post->str('new-format'),
                'new-title' => $INPUT->post->str('new-title')
            ];
            send_redirect(wl($newId,$urlparams,true,'&'));
            return false; //never reached
        } else {
            $TEXT = $this->prepareTemplateNewEntry($newId, $INPUT->str('new-title'));
            $event->data = 'preview';
            return false;
        }
    }

    /**
     * Loads the template for a new blog post and does some text replacements
     *
     * @author Gina Haeussge <osd@foosel.net>
     *
     * @param $id
     * @param $title
     * @return string
     */
    private function prepareTemplateNewEntry($id, $title) {
        $tpl = pageTemplate($id);
        if(!$tpl) $tpl = io_readFile(DOKU_PLUGIN . 'blogtng/tpl/newentry.txt');

        $replace = array(
            '@TITLE@' => $title,
        );
        return str_replace(array_keys($replace), array_values($replace), $tpl);
    }
}
