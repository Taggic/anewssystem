<?php
/**
 * Options for the anewssystem plugin
 */

$conf['d_format']          = 'd. M Y';
$conf['news_datafile']     = 'news:newsdata';
$conf['news_output']       = 'news:allnewsdata';
$conf['prev_length']       = '500';
$conf['newsflash_link']    = 1;
$conf['hide_anchorID']     = 1;
$conf['wysiwyg']           = 0;
$conf['soapp']             = 0;                 // soapp = show one article per page (instead of all news)
$conf['yh_level']          = 2;                 // headline level for year cluster of All News articles
$conf['mh_level']          = 3;                 // headline level for month clusetr of All News articles
$conf['h_level']           = 4;                 // headline level for All News articles itself
$conf['lnk_newsarchive']   = 'News Archive &raquo;';    // text for archive link
$conf['act_delim']         = '&';               // newer templates / dw-version seem to use questionmark instead of ampersand at action links
$conf['convert']           = 'http://fadeout.de/thumbshot-pro/?scale=3&url=%s&effect=2'; //the online service, which converts the linked page into a preview picture
// http://www.thumbshots.de/cgi-bin/show.cgi?url=%s
// http://images.websnapr.com/?size=s&nocache=81&url=%s
// http://www.artviper.net/screenshots/screener.php?sdx=1024&sdy=768&w=120&h=80&q=100&url=%s
// http://image.thumber.de/?size=XXL&amp;url=%s
// http://fadeout.de/thumbshot-pro/?scale=3&url=%s&effect=2