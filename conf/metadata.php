<?php
$blogtng_meta__excluded_syntax = array('info', 'blogtng_commentreply', 'blogtng_blog', 'blogtng_readmore', 'blogtng_header', 'blogtng_topic');

$meta['comments_forbid_syntax'] = array(
                                       'multicheckbox', 
                                       '_choices' => array_diff(plugin_list('syntax'), $blogtng_meta__excluded_syntax),
                                   );
$meta['comments_xhtml_renderer'] = array(
                                       'multicheckbox', 
                                       '_choices' => array_diff(plugin_list('syntax'), $blogtng_meta__excluded_syntax),
                                   );
