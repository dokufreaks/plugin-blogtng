<?php
$blogtng_meta__excluded_syntax = array('info', 'blogtng_commentreply', 'blogtng_blog', 'blogtng_readmore', 'blogtng_header', 'blogtng_topic');

$meta['comments_allow_web']       = array('onoff');
$meta['comments_subscription']    = array('onoff');
$meta['comments_gravatar_rating'] = array('multichoice', '_choices' => array('X', 'R', 'PG', 'G'));
$meta['comments_gravatar_default'] = array('multichoice', '_choices' => array('blank', 'default', 'identicon', 'monsterid', 'wavatar'));
$meta['comments_forbid_syntax']   = array(
                                       'multicheckbox',
                                       '_choices' => array_diff(plugin_list('syntax'), $blogtng_meta__excluded_syntax),
                                   );
$meta['comments_xhtml_renderer']  = array(
                                       'multicheckbox',
                                       '_choices' => array_diff(plugin_list('syntax'), $blogtng_meta__excluded_syntax),
                                   );
$meta['editform_set_date']        = array('onoff');
$meta['tags']                     = array('string');
$meta['receive_linkbacks']         = array('onoff');
$meta['send_linkbacks']          = array('onoff');
