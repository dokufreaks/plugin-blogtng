<?php

namespace dokuwiki\plugin\blogtng\entities;

use helper_plugin_blogtng_tools;

/**
 * Simple wrapper class for a single comment object
 */
class Comment{

    /** @var array @deprecated 2022-08-13 */
    public $data;
    /** @var int */
    private $num;
    /**
     * @var helper_plugin_blogtng_tools
     */
    private $tools;

    //data, corresponds to comment entry in database
    /** @var string */
    private $cid;
    /** @var string page id */
    private $pid;
    /** @var string */
    private $source;
    /** @var string */
    private $status;
    /** @var string */
    private $text;
    /** @var string */
    private $name;
    /** @var string */
    private $web;
    /** @var string */
    private $mail;
    /** @var string */
    private $type;
    /** @var string */
    private $created;
    /** @var string */
    private $avatar;
    /** @var string */
    private $ip;

    //extra temporary stored data
    /** @var string */
    private $subscribe;

    /**
     * Resets the internal data with a given row
     *
     * @param array $row associated array as returned from database row of table 'comments'
     */
    public function __construct($row = null){
        $this->tools = new helper_plugin_blogtng_tools();
        if(is_array($row)) {
            $this->init($row);
        }
    }

    /**
     * @param array $row row of table 'comments'
     */
    public function init($row){
        foreach ($row as $key => $item) {
            if(property_exists($this, $key)) {
                $this->{$key} = $item;
            }
        }
        //old data row, for backward compatibility in existing templates
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
        if($comment->status == 'visible' || ($comment->status == 'hidden' && $INFO['isadmin'])) {
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

        $inst = p_get_instructions($this->text);
        echo p_render('blogtng_comment',$inst,$info);
    }

    /**
     * Render the cid of a single comment
     */
    public function tpl_cid(){
        echo $this->cid;
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

        if($link) echo '<a href="#comment_' . $this->cid . '" class="blogtng_num">';
        echo $title;
        if($link) echo '</a>';
    }

    /**
     * Render the hcard/userdata of a single comment
     */
    public function tpl_hcard(){
        echo '<div class="vcard">';
        if($this->web){
            echo '<a href="'.hsc($this->web).'" class="fn nickname">';
            echo hsc($this->name);
            echo '</a>';
        }else{
            echo '<span class="fn nickname">';
            echo hsc($this->name);
            echo '</span>';
        }
        echo '</div>';
    }

    /**
     * Render the name of a single comment
     */
    public function tpl_name(){
        echo hsc($this->name);
    }

    /**
     * Render the type of a single comment
     */
    public function tpl_type(){
        echo hsc($this->type);
    }

    /**
     * Render the mail of a single comment
     */
    public function tpl_mail(){
        echo hsc($this->mail);
    }

    /**
     * Render the web address of a single comment
     */
    public function tpl_web(){
        echo hsc($this->web);
    }

    /**
     * Render the creation date of a single comment
     *
     * @param string $fmt date format, empty string default to $conf['dformat']
     */
    public function tpl_created($fmt=''){
        echo hsc(dformat($this->created,$fmt));
    }

    /**
     * Render the status of a single comment
     */
    public function tpl_status() {
        echo $this->status;
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
        if($this->avatar) {
            $img = $this->avatar;
            //FIXME add hook for additional methods
        } elseif ($this->mail) {
            $dfl = $conf['plugin']['blogtng']['comments_gravatar_default'];
            if(!isset($dfl) || $dfl == 'blank') $dfl = DOKU_URL . 'lib/images/blank.gif';

            $img = 'https://gravatar.com/avatar.php'
                . '?gravatar_id=' . md5($this->mail)
                . '&size=' . $w
                . '&rating=' . $conf['plugin']['blogtng']['comments_gravatar_rating']
                . '&default='.rawurlencode($dfl)
                . '&.png';
        } elseif ($this->web){
            $img = 'https://getfavicon.appspot.com/'.rawurlencode($this->web).'?.png';
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

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @return string
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * @param string $cid
     */
    public function setCid($cid)
    {
        $this->cid = $cid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getWeb()
    {
        return $this->web;
    }

    /**
     * @param string $web
     */
    public function setWeb($web)
    {
        $this->web = $web;
    }

    /**
     * @return string
     */
    public function getMail()
    {
        return $this->mail;
    }

    /**
     * @param string $mail
     */
    public function setMail($mail)
    {
        $this->mail = $mail;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param string $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return string
     */
    public function getAvatar()
    {
        return $this->avatar;
    }

    /**
     * @param string $avatar
     */
    public function setAvatar($avatar)
    {
        $this->avatar = $avatar;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return string
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param string $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @return string
     */
    public function getSubscribe()
    {
        return $this->subscribe;
    }

    /**
     * @param string $subscribe
     */
    public function setSubscribe($subscribe)
    {
        $this->subscribe = $subscribe;
    }
}
