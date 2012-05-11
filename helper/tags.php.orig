<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <gina@foosel.net>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_TAB')) define('DOKU_TAB', "\t");

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class helper_plugin_blogtng_tags extends DokuWiki_Plugin {

    var $sqlitehelper = null;

    var $tags = array();

    var $pid = null;

    /**
     * Constructor, loads the sqlite helper plugin
     */
    function helper_plugin_blogtng_tags() {
        $this->sqlitehelper =& plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Count tags for specified pid
     */
    function count($pid) {
        $pid = trim($pid);
        $query = 'SELECT COUNT(tag) AS tagcount FROM tags WHERE pid = ?';

        $resid = $this->sqlitehelper->query($query, $pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load tags!', -1);
        }

        $tagcount = $this->sqlitehelper->res2row($resid, 0);
        return $tagcount['tagcount'];
    }

    /**
     * Load tags for specified pid
     */
    function load($pid) {
        $this->pid = trim($pid);
        $query = 'SELECT tag FROM tags WHERE pid = ? ORDER BY tag ASC';

        $resid = $this->sqlitehelper->query($query, $this->pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load tags!', -1);
            $this->tags = array();
        }
        if (sqlite_num_rows($resid) == 0) {
            $this->tags = array();
        }

        $tags_from_db = $this->sqlitehelper->res2arr($resid);
        $tags = array();
        foreach ($tags_from_db as $tag_from_db) {
            array_push($tags, $tag_from_db['tag']);
        }
        $this->tags = $tags;
    }

    /**
     * Load tags for a specified blog
     */
    function load_by_blog($blogs) {
        $query = 'SELECT DISTINCT tag, A.pid as pid FROM tags A LEFT JOIN entries B ON B.blog IN ("' . implode('","', $blogs) . '")';
        $resid = $this->sqlitehelper->query($query);
        if($resid) {
            return $this->sqlitehelper->res2arr($resid);
        }
    }

    /**
     * Save tags
     */
    function save() {
        //FIXME $sqlite undefined
        $query = 'BEGIN TRANSACTION';
        if (!$this->sqlitehelper->query($query)) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return;
        }
        $query = 'DELETE FROM tags WHERE pid = ?';
        if (!$this->sqlitehelper->query($query, $this->pid)) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return;
        }
        foreach ($this->tags as $tag) {
            $query = 'INSERT INTO tags (pid, tag) VALUES (?, ?)';
            if (!$this->sqlitehelper->query($query, $this->pid, $tag)) {
                $sqlite->query('ROLLBACK TRANSACTION');
                return;
            }
        }
        $query = 'END TRANSACTION';
        if (!$this->sqlitehelper->query($query)) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return;
        }

        global $ID;
        // FIXME This should probably happen in metadata rendering
        p_set_metadata($ID, array('subject' => $this->tags), false, true);

    }

    function set($tags) {
        $this->tags = array_unique(array_filter(array_map('trim', $tags)));
    }

    function join_tag_query($tagquery) {
        if (!$tagquery) {
            return null;
        }

        $tags = array_map('trim', explode(' ', $tagquery));
        $tag_clauses = array(
            'OR' => array(),
            'AND' => array(),
            'NOT' => array(),
        );
        foreach ($tags as $tag) {
            if ($tag{0} == '+') {
                array_push($tag_clauses['AND'], 'tag = \'' . sqlite_escape_string(substr($tag, 1)) . '\'');
            } else if ($tag{0} == '-') {
                array_push($tag_clauses['NOT'], 'tag != \'' . sqlite_escape_string(substr($tag, 1)) . '\'');
            } else {
                array_push($tag_clauses['OR'], 'tag = \'' . sqlite_escape_string($tag) . '\'');
            }
        }
        $tag_clauses = array_map('array_unique', $tag_clauses);

        $where = '';
        if ($tag_clauses['OR']) {
            $where .= '('.join(' OR ', $tag_clauses['OR']).')';
        }
        if ($tag_clauses['AND']) {
            $where .= (!empty($where) ? ' AND ' : '').join(' AND ', $tag_clauses['AND']);
        }
        if ($tag_clauses['NOT']) {
            $where .= (!empty($where) ? ' AND ' : '').join(' AND ', $tag_clauses['NOT']);
        }
        return $where;
    }

    function tpl_tags($target){
        $count = count($this->tags);
        $prepared = array();
        foreach ($this->tags as $tag) {
            array_push($prepared, DOKU_TAB.'<li><div class="li"><a href="'.wl($target,array('btng[post][tags]'=>$tag)).'" class="tag">'.hsc($tag).'</a></div></li>');
        }
        $html = '<ul class="blogtng_tags">'.DOKU_LF.join(DOKU_LF, $prepared).'</ul>'.DOKU_LF;
        echo $html;
    }

    function tpl_tagstring($target, $separator) {
        echo join($separator, array_map(array($this, '_format_tag_link'), $this->tags, array_fill(0, count($this->tags), $target)));
    }

    /**
     * Displays a tag cloud
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function xhtml_tagcloud($conf) {
        $tags = $this->load_by_blog($conf['blog']);
        if(!$tags) return;
        $cloud = array();
        foreach($tags as $tag) {
            if(!isset($cloud[$tag['tag']])) {
                $cloud[$tag['tag']] = 1;
            } else {
                $cloud[$tag['tag']]++;
            }
            //$cloud[$tag['tag']][] = $tag['pid'];
        }
        asort($cloud);
        $cloud = array_slice(array_reverse($cloud), 0, $conf['limit']);
        $this->_cloud_weight($cloud, min($cloud), max($cloud), 5);
        ksort($cloud);
        $output = "";
        foreach($cloud as $tag => $weight) {
            $output .= '<a href="' . wl($conf['target'], array('btng[post][tags]'=>$tag))
                    . '" class="tag cloud_weight' . $weight
                    . '" title="' . $tag . '">' . $tag . "</a>\n";
        }
        return $output;
    }

    /**
     * Happily stolen (and slightly modified) from
     *
     * http://www.splitbrain.org/blog/2007-01/03-tagging_splitbrain
     */
    function _cloud_weight(&$tags,$min,$max,$levels){
        // calculate tresholds
        $tresholds = array();
        $tresholds[0]= $min; // lowest treshold should always be min
        for($i=1; $i<=$levels; $i++){
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min;
        }

        // assign weights
        foreach($tags as $tag => $cnt){
            foreach($tresholds as $tresh => $val){
                if($cnt <= $val){
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }
    }

    function _format_tag_link($tag, $target) {
        return '<a href="'.wl($target,array('btng[post][tags]'=>$tag)).'" class="tag">'.hsc($tag).'</a>';
    }
}
// vim:ts=4:sw=4:et:
