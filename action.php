<?php
/******************************************************************************
**
**  action script related to anewssystem
**  Action to display the archive page
*/
/******************************************************************************
**  must run within Dokuwiki
**/
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');


/******************************************************************************/
class action_plugin_anewssystem extends DokuWiki_Action_Plugin {

    var $parameter = "";
 
  /**
   * return some info
   */
  function getInfo(){
    return array(
         'author' => 'Taggic',
         'email'  => 'Taggic@t-online.de',
         'date'   => '2013-02-19',
         'name'   => 'News archive page (action plugin component)',
         'desc'   => 'to show the News aechive alone on a page.',
         'url'    => 'http://www.dokuwiki.org/plugin:anewssystem',
         );
  }
/******************************************************************************
**  Register its handlers with the dokuwiki's event controller
*/
     function register(Doku_Event_Handler $controller) {
         $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_act', array());
         $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'output', array());
     }

/******************************************************************************
**  Handle the action
*/
     function _handle_act(&$event, $param) {
         if($event->data !== 'shownewsarchive') { return; }
            $event->preventDefault(); // https://www.dokuwiki.org/devel:events#event_object
            return true;
     }
/******************************************************************************
**  Generate output
*/
    function output(&$event, $param) {
        if($event->data !== 'shownewsarchive') { return; }
        global $ID;
        
        $target       = $this->getConf('news_datafile');
        $targetpage   = htmlspecialchars(trim($target));
        $prefix       = 'anss';
        $del          = 'anss_del';
        $cut_prefx    = 'news_input_';
        $allnewsdata1 = $this->getConf('news_output');
        $allnewsdata  = wl( (isset($allnewsdata1) ? $allnewsdata1 : 'news:newsdata') );
        $i            = strripos($allnewsdata, ":");
        $news_root    = substr($allnewsdata, 0, $i);          

        // necessary for the back link of a show one article per page (SOAPP)
        if(stripos($_GET['archive'],'archive')!== false) $ans_conf['param'] = $_GET['archive'];
        $_GET['archive']="";  
                        
        // 1. read template (plugins/anewssystem/template.php)
        $template   = file_get_contents(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
      /*------- add news action part -----------------------------------------*/
            $post_prefix   = $_POST["xs-".$prefix];
            $delete_record = $_POST["anss_del_record"];
            $delete_anchor = $_POST["anss_del_anchor"];

          // date  ... consider all news of a defined month of a year (mm.yyyy, empty per default)
          // qty   ... limits the number of news headlines starting with most recent (either integer or all, default:all)
          // tag   ... consider all news where news article owns the given tag string (empty per default) tag delimiter is "|"
          // style ... css style string as used in HTML (except quotation marks) for the outer element div
          // class ... css style for usecase toc, page or box
          // ho    ... headlinesonly will list the news headlines without timestamp and author (on/off, default: off)
          
          // check if page ID was called with tag filter
//          $tmp         .= ','.$_GET['tag'];    // this will overrule the page syntax setting
          if(strlen($tmp)<2) {
              // strip parameter to get set of add parameter
              $tmp     = substr($ans_conf['param'],strlen('allnews')); 
          }
          $split_array = explode(',',$tmp); // one or multiple tag filters: $prefs[1] ... [n]
          $archive_options = array();
          
          // split parameter into array with key and data
          foreach ($split_array as $item) {
            list($key, $value) = split("=",trim($item),2);
            $archive_options = $archive_options + array($key => $value);
          }

          if(($archive_options['qty']=='') || ($archive_options['qty']<1)) $archive_options['qty']   = 'all';
          if(array_key_exists('class',$archive_options) === false)         $archive_options['class'] = 'page';
          if(array_key_exists('ho',$archive_options) === false)            $archive_options['ho']    = 'off';
          $page        = wl( (isset($targetpage) ? $targetpage : 'news:newsdata') );          
          
          // load raw news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
          
          // split the news articles
          $newsitems = explode("======",$oldrecord);
          $info = array();
          
          // get the headline level from config
          $yh_level = $this->getConf('yh_level');
          $mh_level = $this->getConf('mh_level');
          $h_level = $this->getConf('h_level');
                    
          // 1. read news file (e.g. news:newsdata.txt)
          foreach($newsitems as $article) {             
             // split news block into line items
             $article_array = explode("\n  * ",$article);
             unset($article_array[0]);
             
             // 2. create output
             // split line items into key and data
             $aFlag = false;   // flag: start date value exists and start is not in future

                 foreach ($article_array as $item) {
                        list($key, $value) = split(":",trim($item),2);
                        $tag_flag = false;
                        if($key=='anchor') {
                            $anchor = trim($value);
                        }
                        elseif(($key=='start') && strtotime(trim($value)) < time()) {
                            $value = date($this->getConf('d_format'), strtotime($value));
                            $news_date = '<span class="news_date_a"> ('. $value;
                            // get month and year to compare with $archive_options['date']
                            if(isset($archive_options['date']) && ($archive_options['date'] !== date('m.Y',strtotime($value)))) break;
                            $aFlag = true;
                        }
                        // head has to be before the link in the template !
                        elseif($key=='head'){
                             $news_head = trim($value);                        
                        }
                        elseif($key=='subtitle'){
                             $news_subtitle = '<br /><span class="news_subtitle">'.trim($value).'</span>'.NL;                        
                        }
                        elseif($key=='link'){                      
                            $news_head = '<a href="'.$value.'" id="'.$value.'" name="'.$value.'">'. trim($news_head) .'</a>'.NL;
                        }
                        elseif($key=='author'){                      
                            $news_date .= ', '. $value;
                        }
                        elseif(($key=='tags') && (isset($archive_options['tag']) !== false)) {
//                            echo $value.'<br />';
                            $tags = explode(',',$value);
                            foreach($tags as $tag) {
                                if(($tag!==false) && (stripos($archive_options['tag'],trim($tag))!==false)){
                                    $tag_flag = true;
                                    break;
                                }
                            }
                        }
                 }
                 
                 $news_date .=  ')</span>'.NL;

                 if((isset($archive_options['tag']) === false) || (strlen($archive_options['tag']) <2)) $tag_flag = true;                 
                 
                 if (($aFlag === true) && ($tag_flag === true)) {
                    //stop adding older news articles if quantity is reached
//                    echo intval($archive_options['qty']).' >= '.$qty.'<br>';
                    $qty++;
                    if(($qty > intval($archive_options['qty'])) && ($archive_options['qty']!=='all')) break;
                    
                    // list all news stories as headline linked to the story itself
                    $elt = explode(",",$news_date);
                    $elt[0] = trim(strip_tags(str_replace('(','',$elt[0])));
                    $elt[0] = date('F,Y',strtotime($elt[0]));
                    list($new_month,$new_year) = explode(',',$elt[0]); 
                    
                    // idea is that all stories are created one after the other 
                    // and the order within newsdata is according the start date
                    // manipulation of Start/Perishing date possible but not expected
                    // !!! There is no sort algorithm for year and month implemented !!!
                    // to do such would lead into re-development of the plugin
                    if(($old_year  !== $new_year) && (($archive_options['class']==='page') || ($archive_options['ho']==='off')))  {
                      if(trim($old_year) !== '') $close_ytag = "</li></ul>".NL;
                      $output .= $close_ytag.'<ul><li class="level1"><div class="li">'.$new_year.'</div><ul class="n_box">'; 
                      $old_year  = $new_year;
                    }
                    
                    if(($old_month  !== $new_month) && (($archive_options['class']==='page') || ($archive_options['ho']==='off'))) {
                      if(trim($old_month) !== '') $close_mtag = "</li></ul>".NL;
                      $output .= $close_mtag.'<ul><li class="level2"><div class="li">'.$new_month.'</div>';
                      $old_month = $new_month; 
                    }
                    
                    if($archive_options['ho']==='on') $news_date='';
                    else $news_date .= '<br />'; 
                    
                    if(($archive_options['tag']!==false) && ($archive_options['tag']!=='off') && ($archive_options['class']=='page')) $output .= '<div class="archive_item">'.trim($news_date).$news_head.$news_subtitle.'</div>'.NL;
                    else $output .= '<ul><li class="level3"><div class="li">'.trim($news_date).$news_head.'</div></li></ul>'.NL;
                    
                    $close_ytag    = "";
                    $close_mtag    = "";
                    $anchor        = "";
                    $news_date     = "";
                    $news_head     = "";
                    $news_subtitle = "";
                    $tags          = ""; 
                }
          }
          $blink_id = "news_items";
          $img_ID   = "img_archive__toc";

        $archive_lnkTitle = $this->getConf('lnk_newsarchive');
        if($archive_lnkTitle=='') $archive_lnkTitle = "News Archive";

        $backlink = '<a href="javascript:history.back(-1)">'.$this->getLang('lnk_back').'</a>';
        $backlink .= '<span class="anss_sep"> &nbsp;|&nbsp;</span>
                      <a href="'.DOKU_URL.'doku.php?id='.$this->getConf('news_output').'">'.$this->getLang('allnews').' &raquo;</a>';
        $output = '<div class="backlinkDiv" style="font-size:.85em;">'.$backlink.'</div><br />'.NL.
                  '<div class="archive_section" id="news_archive_head"  style="'.$archive_options['style'].'">
                      <div id="news_items">
                          '.$output.'
                       </div>
                   </div>'.NL.
                  '<div class="backlinkDiv" style="font-size:.85em;">'.$backlink.'</div><br />'.NL;          
                
        echo $output;
        $event->preventDefault();
    }
/******************************************************************************/
}