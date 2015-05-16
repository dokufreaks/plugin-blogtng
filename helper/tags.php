<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <gina@foosel.net>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Delivers tag related functions
 */
class helper_plugin_blogtng_tags extends DokuWiki_Plugin {

    /** @var helper_plugin_blogtng_sqlite */
    private $sqlitehelper = null;

    private $tags = array();

    private $pid = null;

    /**
     * Constructor, loads the sqlite helper plugin
     */
    public function __construct() {
        $this->sqlitehelper = plugin_load('helper', 'blogtng_sqlite');
    }

    /**
     * Set pid of page
     *
     * @param string $pid
     */
    public function setPid($pid) {
        $this->pid = trim($pid);
    }

    /**
     * Set tags, filtered and unique
     *
     * @param array $tags
     */
    public function setTags($tags) {
        $this->tags = array_unique(array_filter(array_map('trim', $tags)));
    }

    /**
     * Return the tag array
     *
     * @return array tags
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * Load tags for specified pid
     *
     * @param $pid
     * @return bool
     */
    public function load($pid) {
        $this->setPid($pid);

        if(!$this->sqlitehelper->ready()) {
            msg('blogtng plugin: failed to load sqlite helper plugin!', -1);
            $this->tags = array();
            return false;
        }
        $query = 'SELECT tag
                    FROM tags
                   WHERE pid = ?
                ORDER BY tag ASC';
        $resid = $this->sqlitehelper->getDB()->query($query, $this->pid);
        if ($resid === false) {
            msg('blogtng plugin: failed to load tags!', -1);
            $this->tags = array();
            return false;
        }
        if ($this->sqlitehelper->getDB()->res2count($resid) == 0) {
            $this->tags = array();
            return true;
        }

        $tags_from_db = $this->sqlitehelper->getDB()->res2arr($resid);
        $tags = array();
        foreach ($tags_from_db as $tag_from_db) {
            array_push($tags, $tag_from_db['tag']);
        }
        $this->tags = $tags;
        return true;
    }

    /**
     * Count tags for specified pid
     *
     * @param $pid
     * @return int
     */
    public function count($pid) {
        if(!$this->sqlitehelper->ready()) {
            msg('BlogTNG plugin: failed to load tags. (sqlite helper plugin not available)', -1);
            return 0;
        }

        $pid = trim($pid);
        $query = 'SELECT COUNT(tag) AS tagcount
                    FROM tags
                   WHERE pid = ?';

        $resid = $this->sqlitehelper->getDB()->query($query, $pid);
        if ($resid === false) {
            msg('BlogTNG plugin: failed to load tags!', -1);
            return 0;
        }

        $tagcount = $this->sqlitehelper->getDB()->res2row($resid, 0);
        return (int)$tagcount['tagcount'];
    }

    /**
     * Load tags for a specified blog
     *
     * @param $blogs
     * @return array|bool
     */
    public function load_by_blog($blogs) {
        if(!$this->sqlitehelper->ready()) return false;

        $query = 'SELECT tags.tag AS tag, tags.pid AS pid
                    FROM tags, entries
                   WHERE tags.pid = entries.pid
                     AND entries.blog IN ("' . implode('","', $blogs) . '")
                     AND GETACCESSLEVEL(page) >= '.AUTH_READ;

        $resid = $this->sqlitehelper->getDB()->query($query);
        if($resid) {
            return $this->sqlitehelper->getDB()->res2arr($resid);
        }
        return false;
    }

    /**
     * Save tags
     */
    public function save() {
        if (!$this->sqlitehelper->ready()) return;

        $query = 'BEGIN TRANSACTION';
        if (!$this->sqlitehelper->getDB()->query($query)) {
            $this->sqlitehelper->getDB()->query('ROLLBACK TRANSACTION');
            return;
        }
        $query = 'DELETE FROM tags WHERE pid = ?';
        if (!$this->sqlitehelper->getDB()->query($query, $this->pid)) {
            $this->sqlitehelper->getDB()->query('ROLLBACK TRANSACTION');
            return;
        }
        foreach ($this->tags as $tag) {
            $query = 'INSERT INTO tags (pid, tag) VALUES (?, ?)';
            if (!$this->sqlitehelper->getDB()->query($query, $this->pid, $tag)) {
                $this->sqlitehelper->getDB()->query('ROLLBACK TRANSACTION');
                return;
            }
        }
        $query = 'END TRANSACTION';
        if (!$this->sqlitehelper->getDB()->query($query)) {
            $this->sqlitehelper->getDB()->query('ROLLBACK TRANSACTION');
            return;
        }
    }

    /**
     * Parses query string to a where clause for use in a query
     * in the query string:
     *  - tags are space separated
     *  - prefix + is AND
     *  - prefix - is NOT
     *  - no prefix is OR
     *
     * @param string $tagquery query string to be parsed
     * @return null|string
     */
    public function parse_tag_query($tagquery) {
        if (!$tagquery) {
            return null;
        }
        if(!$this->sqlitehelper->ready()) return null;

        $tags = array_map('trim', explode(' ', $tagquery));
        $tag_clauses = array(
            'OR' => array(),
            'AND' => array(),
            'NOT' => array(),
        );
        foreach ($tags as $tag) {
            if ($tag{0} == '+') {
                array_push(
                    $tag_clauses['AND'],
                    'tag = '. $this->sqlitehelper->getDB()->quote_string(substr($tag, 1))
                );
            } else if ($tag{0} == '-') {
                array_push(
                    $tag_clauses['NOT'],
                    'tag != '.$this->sqlitehelper->getDB()->quote_string(substr($tag, 1))
                );
            } else {
                array_push(
                    $tag_clauses['OR'],
                    'tag = '.$this->sqlitehelper->getDB()->quote_string($tag)
                );
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

    /**
     * Print a list of tags
     *
     * @param string $target - tag links will point to this page, tag is passed as parameter
     */
    public function tpl_tags($target){
        $prepared = array();
        foreach ($this->tags as $tag) {
            array_push($prepared, DOKU_TAB.'<li><div class="li">'.$this->_format_tag_link($tag, $target).'</div></li>');
        }
        $html = '<ul class="blogtng_tags">'.DOKU_LF.join(DOKU_LF, $prepared).'</ul>'.DOKU_LF;
        echo $html;
    }

    /**
     * Print the joined tags as a string
     *
     * @param string $target - tag links will point to this page
     * @param string $separator
     */
    public function tpl_tagstring($target, $separator) {
        echo join($separator, array_map(array($this, '_format_tag_link'), $this->tags, array_fill(0, count($this->tags), $target)));
    }

    /**
     * Displays a tag cloud
     *
     * @author Michael Klier <chi@chimeric.de>
     *
     * @param $conf
     * @return string
     */
    public function xhtml_tagcloud($conf) {
        $tags = $this->load_by_blog($conf['blog']);
        if(!$tags) return '';
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
     *
     * @param $tags
     * @param $min
     * @param $max
     * @param $levels
     */
    private function _cloud_weight(&$tags,$min,$max,$levels){
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

    /**
     * Create html of url to target page for given tag
     *
     * @param string $tag
     * @param string $target pageid
     * @return string html of url
     */
    private function _format_tag_link($tag, $target) {
        return '<a href="'.wl($target,array('btng[post][tags]'=>$tag)).'" class="tag">'.hsc($tag).'</a>';
    }
}
// vim:ts=4:sw=4:et:
