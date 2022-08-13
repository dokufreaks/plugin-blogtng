<?php

namespace dokuwiki\plugin\blogtng\entities;

use helper_plugin_blogtng_tools;

/**
 * Simple wrapper class for a single comment object
 */
class Comment{

    /** @var array */
    var $data;
    /** @var int */
    var $num;
    /**
     * @var helper_plugin_blogtng_tools
     */
    private $tools;

    /**
     * Resets the internal data with a given row
     */
    public function __construct(){
        $this->tools = new helper_plugin_blogtng_tools();
    }

    /**
     * @param array $row row of table 'comments'
     */
    public function init($row){
        $this->data = $row;
    }

    /**
     * Render a comment using the templates comments file.
     *
     * @param string $name of template
     * @return bool
     */
    public function output($name){
        global $INFO;
        $name = preg_replace('/[^a-zA-Z_\-]+/','',$name);
        $tpl = $this->tools->getTplFile($name,'comments');
        if($tpl === false){
            return false;
        }

        $comment = $this;
        if($comment->data['status'] == 'visible' || ($comment->data['status'] == 'hidden' && $INFO['isadmin'])) {
            $comment->num++;
            include($tpl);
        }
        return true;
    }

    /**
     * Get translated string for @$name
     *
     * @param   string  $name     id of the string to be retrieved
     * @return  string  string in appropriate language or english if not available
     */
    public function getLang($name){
        return $this->tools->getLang($name);
    }

    /**
     * Render the text/content of a single comment
     */
    public function tpl_comment(){
        //FIXME add caching

        $inst = p_get_instructions($this->data['text']);
        echo p_render('blogtng_comment',$inst,$info);
    }

    /**
     * Render the cid of a single comment
     */
    public function tpl_cid(){
        echo $this->data['cid'];
    }

    /**
     * Render the number of a comment.
     *
     * @param bool   $link  whether wrapped with link element
     * @param string $fmt   format of number
     * @param string $title null or alternative title
     */
    public function tpl_number($link = true, $fmt = '%d', $title = null) {
        if($title === null) $title = sprintf($fmt, $this->num);

        if($link) echo '<a href="#comment_' . $this->data['cid'] . '" class="blogtng_num">';
        echo $title;
        if($link) echo '</a>';
    }

    /**
     * Render the hcard/userdata of a single comment
     */
    public function tpl_hcard(){
        echo '<div class="vcard">';
        if($this->data['web']){
            echo '<a href="'.hsc($this->data['web']).'" class="fn nickname">';
            echo hsc($this->data['name']);
            echo '</a>';
        }else{
            echo '<span class="fn nickname">';
            echo hsc($this->data['name']);
            echo '</span>';
        }
        echo '</div>';
    }

    /**
     * Render the name of a single comment
     */
    public function tpl_name(){
        echo hsc($this->data['name']);
    }

    /**
     * Render the type of a single comment
     */
    public function tpl_type(){
        echo hsc($this->data['type']);
    }

    /**
     * Render the mail of a single comment
     */
    public function tpl_mail(){
        echo hsc($this->data['mail']);
    }

    /**
     * Render the web address of a single comment
     */
    public function tpl_web(){
        echo hsc($this->data['web']);
    }

    /**
     * Render the creation date of a single comment
     *
     * @param string $fmt date format, empty string default to $conf['dformat']
     */
    public function tpl_created($fmt=''){
        echo hsc(dformat($this->data['created'],$fmt));
    }

    /**
     * Render the status of a single comment
     */
    public function tpl_status() {
        echo $this->data['status'];
    }

    /**
     * Render the avatar of a single comment
     *
     * @param int $w avatar width
     * @param int $h avatar height
     * @param bool $return whether the url is returned or printed
     * @return void|string url of avatar
     */
    public function tpl_avatar($w=0,$h=0,$return=false){
        global $conf;
        $img = '';
        if($this->data['avatar']) {
            $img = $this->data['avatar'];
            //FIXME add hook for additional methods
        } elseif ($this->data['mail']) {
            $dfl = $conf['plugin']['blogtng']['comments_gravatar_default'];
            if(!isset($dfl) || $dfl == 'blank') $dfl = DOKU_URL . 'lib/images/blank.gif';

            $img = 'https://gravatar.com/avatar.php'
                . '?gravatar_id=' . md5($this->data['mail'])
                . '&size=' . $w
                . '&rating=' . $conf['plugin']['blogtng']['comments_gravatar_rating']
                . '&default='.rawurlencode($dfl)
                . '&.png';
        } elseif ($this->data['web']){
            $img = 'https://getfavicon.appspot.com/'.rawurlencode($this->data['web']).'?.png';
        }


        //use fetch for caching and resizing
        if($img){
            $img = ml($img,array('w'=>$w,'h'=>$h,'cache'=>'recache'));
        }
        if($return) {
            return $img;
        } else {
            print $img;
        }
    }
}
