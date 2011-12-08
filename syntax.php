<?php
 
/**
 * Plugin anewssystem: provides an easy to handle, page based news system
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Taggic <taggic@t-online.de>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_DATA')) define('DOKU_DATA',DOKU_INC.'data/pages/');
require_once(DOKU_PLUGIN.'syntax.php');  
require_once(DOKU_INC.'inc/parser/xhtml.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_anewssystem extends DokuWiki_Syntax_Plugin {
 
/******************************************************************************/
/* return some info
*/
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getType(){ return 'substition';}
    function getPType(){ return 'block';}
    function getSort(){ return 167;}
    
/******************************************************************************/
/* Connect pattern to lexer
*/   
    function connectTo($mode){
        $this->Lexer->addSpecialPattern('\{\{anss>[^}]*\}\}',$mode,'plugin_anewssystem');
    }

/******************************************************************************/
/* handle the match
*/   
    function handle($match, $state, $pos, &$handler) {
        global $ID;
        $match = substr($match,strlen('{{anss>'),-2); //strip markup from start and end

        //handle params
        $data = array();
    /******************************************************************************/
    /*      parameter 1 can be one of the following: author, flash 
    /******************************************************************************/

        $params = $match;  // if you will have more parameters and choose ',' to delim them
        
        //Default Value
        $ans_conf                 = array();
        $ans_conf['newsroot']     = 'news';
        $ans_conf['newspage']     = 'newsdata';
        $ans_conf['newstemplate'] = DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt';
        $ans_conf['param']        = $params;
        
        if (!$params) {
          msg('Syntax of anewssystem detected but an unknown parameter was attached.', -1);          
        }
        else { return $ans_conf;}        
     }
/******************************************************************************/
/* render output
* @author Taggic <taggic@t-online.de>
*/   
    function render($mode, &$renderer, $ans_conf) {

        $xhtml_renderer = new Doku_Renderer_xhtml();
        $records    = file(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
        unset($records[0]);
        $target     = $this->getConf('news_datafile');
        $targetpage = htmlspecialchars(trim($target));
        $prefix     = 'anss_';
        $cut_prefx = 'news_input_';
        $allnewsdata1 = $this->getConf('news_output');
        $allnewsdata = wl( (isset($allnewsdata1) ? $allnewsdata1 : 'news:newsdata') );          


        // 1. read template (plugins/anewssystem/template.php)
        $template   = file_get_contents(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
        
      /*------- add news action part -----------------------------------------*/
        if ($ans_conf['param']==='author') {
            if( ($_POST["xs-".$prefix] == "check") && (auth_quickaclcheck($targetpage) >= AUTH_EDIT) ) {
                // this will be called to store the news article to the others
                $id_count = 1;
                foreach( $_POST as $postkey => $postvalue ) {
                    if( strpos($postkey, "news_input_") === 0 ) {
                      $key = substr($postkey, strlen($cut_prefx));
                      $key = trim($key);
                      if( strpos(trim($postvalue), "\n") !== false ) {
                        // this is a multilined value, so we need to prepend a linebreak
                        // to achieve a multilined value for the template plugin
                        $postvalue = "\n" . $postvalue;
                      }
                      if(strpos('anchor',$key)>0) {
                        $postvalue ='<a href="'.$postvalue.'">'.$postvalue.'</a>';
                      }
                      $newrecord .= "  * " . $key . ": " . $postvalue . "\n";
                    }
                  }
                  $newrecord .= "\n----\n\n";
                  $oldrecord = rawWiki($targetpage);

                  saveWikiText($targetpage, $newrecord.$oldrecord, "New entry", true);
                  msg($this->getLang('news_added'),1);
            }
            elseif( ($_POST["xs-".$prefix] == "check") && (auth_quickaclcheck($targetpage) < AUTH_EDIT) ) {
                msg($this->getLang('no_permission'),-1);
            }

      /*------- show user form -----------------------------------------------*/            
            // this will provide the user form to add further news
            // 2. create input form based on template
            $output = '<span><script type="text/javascript">
                          function count_chars(obj) {
                              document.getElementById("nws_charcount").innerHTML =  "&nbsp;&nbsp;(message length: " + obj.value.length + " )";
                          }
                       </script></span>';

            $output .= '<div class="news_form_div">
                       <form class="news_input_form" id="'.$prefix.'"
                            method="POST" 
                            action="'.$_SERVER['REQUEST_URI'].'" 
                            >'.NL;
                            
            $output .= '<input type="hidden" name="xs-'.$prefix.'" value="check" />'.NL;

            foreach ($records as $record) {
                $fields = explode('|',$record);
                if (trim($fields[1]) == "textarea") { 
                        $output .= '<p>'.trim($fields[4]); 
                        $output .= '<label class="nws_charcount" 
                                             id="nws_charcount"
                                             name="nws_charcount">&nbsp;&nbsp;(message length: 0 )</label><br />';   

                        $output .= '<textarea class="news_input_textarea"'. 
                                            ' id="news_input_'.trim($fields[0]).'"'.
                                            ' name="news_input_'.trim($fields[0]).'"'.
                                            ' title="'.trim($this->getLang(trim($fields[5]))).'" '.trim($fields[2]).'"'.
                                            ' onkeyup="count_chars(this)" >'.
                                      '</textarea></p>'.NL;
                }
                else if (trim($fields[0]) == "anchor") {
                        $default_anker = '#'.date("YmdHis");
                        $output .= '<p>'.trim($fields[4]).'
                                      <input class="news_input_'.trim($fields[0]).
                                          '" id="news_input_'.trim($fields[0]).
                                          '" name="news_input_'.trim($fields[0]).
                                          '" type="'.trim($fields[1]).
                                          '" '.trim($fields[2]). 
                                             'value="'.$default_anker.'" title="'.trim($this->getLang(trim($fields[5]))).
                                      '" /></p>'.NL;
                }
                else if (trim($fields[1]) == "date") {
                        $default_value = date("Y-m-d", strtotime($fields[3]));
                        $output .= '<p>'.trim($fields[4]).'
                                      <input class="news_input_'.trim($fields[0]).
                                          '" id="news_input_'.trim($fields[0]).
                                          '" name="news_input_'.trim($fields[0]).
                                          '" type="'.trim($fields[1]).
                                          '" '.trim($fields[2]). 
                                             'value="'.$default_value.'" title="'.trim($this->getLang(trim($fields[5]))).
                                      '" /></p>'.NL;
                }   
                else if (trim($fields[1]) == "link") {
                        $default_value = wl($allnewsdata1).$default_anker;
                        $output .= '<p>'.trim($fields[4]).'
                                      <input class="news_input_'.trim($fields[0]).
                                          '" id="news_input_'.trim($fields[0]).
                                          '" name="news_input_'.trim($fields[0]).
                                          '" type="'.trim($fields[1]).
                                          '" '.trim($fields[2]). 
                                             'value="'.$default_value.'" title="'.trim($this->getLang(trim($fields[5]))).
                                      '" /></p>'.NL;
                }
                else {
                        $output .= '<p>'.trim($fields[4]).'
                                      <input class="news_input_'.trim($fields[0]).
                                          '" id="news_input_'.trim($fields[0]).
                                          '" name="news_input_'.trim($fields[0]).
                                          '" type="'.trim($fields[1]).
                                          '" '.trim($fields[2]). 
                                             'value="'.trim($fields[3]).'" title="'.trim($this->getLang(trim($fields[5]))).
                                      '" /></p>'.NL;
                }
                $id_count = $id_count + 1;   
            }

            $output .= '<input class="anss_input_btn_save" 
                               id="anss_input_btn_save" 
                               type="submit" 
                               name="anss_input_btn_save" 
                               value="'.$this->getLang('anss_input_btn_save').'" 
                               title="'.$this->getLang('anss_input_btn_save_descr').'" />'.NL;
            $output .= '<form></div>';
            // 3. check if path/file exist on save click
            // 4. add the new post before the existing (e.g. news:newsdata.txt)
            $renderer->doc .= $output;
        }

      /*------- show perview -------------------------------------------------*/      
        if (strpos($ans_conf['param'], 'flash')!== false) {
          $info = array();
          $tmp = substr($ans_conf['param'],strlen('flash')); //strip parameter to get set of add parameter
          $prefs = explode(',',$tmp);
          // $prefs[0] = preview length
          // $prefs[1] = box width
          // $prefs[2] = float option
          // $prefs[3] = max items
          if($prefs[0]<50) $prefs[0] = $this->getConf('prev_length');
          $preview_length = $prefs[0];
          if(! isset($prefs[1])) { 
            $prefs[1]='';
            $item_width = '';}
          else {
            $a=0.5; 
            $item_width = 'style="margin-right: 10px !important;"';
            $prefs[2] = "float: ".$prefs[2];
            $prefs[1] = 'style="width: '.$prefs[1].'; '.$prefs[2].'"';} 
          
          if($prefs[3]==0) $prefs[3]=5;
          
          $newsitems = array();
          // this will be called to display a preview
          $output = '<div class="news_box" '.$prefs[1].'>
          <div><a class="news_header" href="'. $allnewsdata .'">NEWS flash</a></div>
          <ul class="news_list" '.$item_width.'">';

          // 1. read news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
          $entries = explode("\n----\n\n",$oldrecord);
          foreach($entries as $entry) {
             // split news block into line items
             $temp_array = explode("  * ",$entry);
             unset($temp_array[0]);
             
             // 2. create preview output
             // split line items into key and data
             $aFlag = false;
             $bFlag = false;
             foreach ($temp_array as $item) {
                    list($key, $value) = split(":",trim($item),2);
                        
                    
                    if(($key=='start') && strtotime(trim($value)) < time()) {
                        $aFlag = true;
                        $value = date($this->getConf('d_format'), strtotime($value));
                        $news_date = '<span class="news_date">('. $value .')</span><br />'.NL;
                    }
                    elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                        $bFlag = true;
                    }
                    elseif($key=='text'){                      
                        $prvw_string = substr( trim( preg_replace ('/\<.*?\>/', ' ', $value ) ), 0, $preview_length );
                        $prvw_string = p_render('xhtml',p_get_instructions($prvw_string),$info);
                        $preview_string = '<div class="news_preview">' . $prvw_string . ' ... </div>'.NL;
                    }
                    // head has to be before the link in the template !
                    elseif($key=='head'){
                        $news_head = $value;                        
                    }
                    elseif($key=='link'){                      
                        $news_head = '<a class="news_link" href="'.$value.'">'. $news_head .'</a>'.NL;
                    }
             }
             if(($aFlag === true) && ($bFlag === true)) {
                 $output .= '<li>'.$news_date.$news_head.$preview_string.'</li><hr>'.NL;
                 $item_counter = $item_counter + 1;                 
                 // stop if max number of items is reached
                 if (isset($prefs[3]) && ($item_counter == $prefs[3])) {
                    break; }
             }    
          }
          $output .= '</ul></div>'.NL.NL;
          $renderer->doc .= $output;
        }

/*          msg('params> '.$ans_conf['param'],0);
          msg('prefs[0]> '.$prefs[0],0);
          msg('prefs[1]> '.$prefs[1],0);
          msg('prefs[2]> '.$prefs[2],0);
          msg('prefs[3]> '.$prefs[3],0);
*/
        /* --- Show all news -------------------------------------------------*/
        if (strpos($ans_conf['param'], 'allnews')!== false) {
          
          $output .= 'All News ><br />';
          
          $newsitems = array();
          // this will be called to display all news articles
          $page = wl( (isset($targetpage) ? $targetpage : 'news:newsdata') );          
          $output = '<div class="allnews_box">
          <ul class="allnews_list">'.NL;
          // 1. read news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
          $entries = explode("\n----\n\n",$oldrecord);
          $info = array();
          
          foreach($entries as $entry) {
             // split news block into line items
             $temp_array = explode("  * ",$entry);
             unset($temp_array[0]);
             
             // 2. create output
             // split line items into key and data
             $aFlag = false;
             $bFlag = false;

                 foreach ($temp_array as $item) {
                        list($key, $value) = split(":",trim($item),2);
                        
                        if($key=='anchor') {
                            $anchor = trim($value);
                        }
                        elseif(($key=='start') && strtotime(trim($value)) < time()) {
                            $aFlag = true;
                            $value = date($this->getConf('d_format'), strtotime($value));
                            $news_date = '<a name="'.$anchor.'" id="'.$anchor.'"><span class="allnews_date">('. $value .')</span></a><br />'.NL;
                        }
                        elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                            $bFlag = true;
                        }
                        elseif($key=='text'){                      
                            // parse value for DW syntax ?
                            
                            $preview_string = '<div class="all_news_article">' . p_render('xhtml',p_get_instructions($value),$info) . '</div>'.NL;
                        }
                        // head has to be before the link in the template !
                        elseif($key=='head'){
                            $news_head = $value;                        
                        }
                        elseif($key=='link'){                      
                            $news_head = '<span class="allnews_head">'. $news_head .'</span>'.NL;
                        }
                 }
                 if(($aFlag === true) && ($bFlag === true)) {
                     $output .= '<li>'.NL.$news_date.NL.$news_head.NL.$preview_string.NL.'</li>'.NL.'<hr>'.NL;
                 }    
          }
          $output .= '</ul></div><div style="clear: both;"></div>'.NL.NL;
          $renderer->doc .= $output;
        }
        // --- faulty syntax ---
        elseif (($ans_conf['param']!='author') && (strpos($ans_conf['param'], 'flash')=== false)) {
          $renderer->doc .= msg('Syntax of anewssystem plugin detected but an unknown parameter  ['.$ans_conf['param'].'] was provided.', -1);
        }
    }
}
?>