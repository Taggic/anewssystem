<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the anewssystem plugin
 *
 * @author   Taggic@t-online.de 
 */

$meta['d_format']       = array('string');
$meta['news_datafile']  = array('string');
$meta['news_output']    = array('string');
$meta['prev_length']    = array('string');
$meta['newsflash_link'] = array('onoff');
$meta['hide_anchorID']  = array('onoff');
$meta['wysiwyg']        = array('onoff');
$meta['soapp']          = array('onoff');       // soapp = show one article per page (instead of all news)
$meta['yh_level']       = array('string');      // headline level for year clusetr of All News articles
$meta['mh_level']       = array('string');      // headline level for month clusetr of All News articles
$meta['h_level']        = array('string');      // headline level for All News articles
$meta['lnk_newsarchive']= array('string');      // text for archive link
$meta['act_delim']      = array('string');      // newer templates / dw-version seem to use questionmark instead of ampersand at action links
$meta['convert']        = array('string');