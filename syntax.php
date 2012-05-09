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
require_once(DOKU_INC.'inc/auth.php');
 
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
    /*      parameter 1 can be one of the following: xs-author, flash 
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
        $records      = file(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
        unset($records[0]);
        $target       = $this->getConf('news_datafile');
        $targetpage   = htmlspecialchars(trim($target));
        $prefix       = 'anss';
        $del          = 'anss_del';
        $cut_prefx    = 'news_input_';
        $allnewsdata1 = $this->getConf('news_output');
        $allnewsdata  = wl( (isset($allnewsdata1) ? $allnewsdata1 : 'news:newsdata') );          
        // check if user has write permission on that ID
        $current_usr = pageinfo();

        // 1. read template (plugins/anewssystem/template.php)
        $template   = file_get_contents(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
      /*------- add news action part -----------------------------------------*/
            $post_prefix   = $_POST["xs-".$prefix];
            $delete_record = $_POST["anss_del_record"];
            $delete_anchor = $_POST["anss_del_anchor"];
            
            if( (strlen($post_prefix)>2) && (auth_quickaclcheck($targetpage) >= AUTH_EDIT) ) {

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
                        $postvalue = str_replace(chr(13),"",$postvalue);
                      }
//                      if( strpos(trim($postvalue), "\n\n") !== false ) $postvalue = str_replace('\n\n','\n',$postvalue);
                      if(strpos('anchor',$key)>0) {
                        $postvalue ='<a href="'.$postvalue.'">'.$postvalue.'</a>';
                      }
                      $newrecord .= "  * " . $key . ": " . $postvalue . "\n";
                    }
                }
                  
//                $newrecord .= "\n----\n\n";
                $newrecord = '====== '.$_POST['news_input_head'].' ======'.chr(10).chr(10).$newrecord.chr(10);
                $oldrecord = rawWiki($targetpage);

                saveWikiText($targetpage, $newrecord.$oldrecord, "New entry", true);
                $_POST["xs-".$prefix] = '';
                msg($this->getLang('news_added'),1);

            }
            elseif( (strlen($post_prefix)>2) && (auth_quickaclcheck($targetpage) < AUTH_EDIT) ) {
                msg($this->getLang('no_permission'),-1);
            }
      /*------- delete a news record -----------------------------------------*/
            if( (strlen($delete_record)>2) && (auth_quickaclcheck($targetpage) >= AUTH_EDIT) ) {
                $raw_records = rawWiki($targetpage);
                $records = explode("====== ",$raw_records);
                foreach($records as $record) {
                  if((stripos($record, $delete_record)!==false) && (stripos($record, $delete_anchor)!==false))  {
//                    echo "Delete: ".$delete_record."<br />";
//                    echo "Anchor: ".$delete_anchor."<br />";
                    continue;
                  }
                  else { if(strlen($record)>1) $news_records.= "====== ".$record;}
                }
//                echo "News: ".$news_records."<br />";
                // write file
                saveWikiText($targetpage, $news_records, "New entry", true);
                // inform user
                msg('Record deleted.',1);
            }    
      /*------- show user form -----------------------------------------------*/            
            // this will provide the user form to add further news
            // 2. create input form based on template
        
            
        if ($ans_conf['param']==='author') {
            
            $output .= '<span><script type="text/javascript">
                          function count_chars(obj, max) {
                              if(obj.value.length>max) output = \'<span style="color:red;">\' + obj.value.length + \'</span>\';
                              else output = obj.value.length;
                              document.getElementById("nws_charcount").innerHTML =  "&nbsp;&nbsp;(message length: " + output + " )"
                          }
                       </script></span>';

            $output .= '<div class="news_form_div">
                       <form class="news_input_form" id="'.$prefix.'"
                            method="POST"
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
                                            ' onkeyup="count_chars(this,'.$this->getConf('prev_length').')" >'.
                                      '</textarea></p>'.NL;
                }
                else if (trim($fields[0]) == "anchor") {
                        $default_anker = '#'.date("YmdHis");
                        
                        if((stripos($fields[1],'hidden') === false) && ($this->getConf('hide_anchorID')< 1)) {                       
                            $output .= '<p>'.trim($fields[4]).'
                                          <input class="news_input_'.trim($fields[0]).
                                              '" id="news_input_'.trim($fields[0]).
                                              '" name="news_input_'.trim($fields[0]).
                                              '" type="'.trim($fields[1]).
                                              '" '.trim($fields[2]). 
                                                 'value="'.$default_anker.'" title="'.trim($this->getLang(trim($fields[5]))).
                                          '" /></p>'.NL;
                        }
                        else {
                            $output .= '<input class="news_input_'.trim($fields[0]).
                                              '" id="news_input_'.trim($fields[0]).
                                              '" name="news_input_'.trim($fields[0]).
                                              '" type="hidden'.
                                              '" '.trim($fields[2]). 
                                                 'value="'.$default_anker.'" title="'.trim($this->getLang(trim($fields[5]))).
                                          '" />'.NL;                        
                        }
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
                else if (trim($fields[0]) == "author") {
                        global $ID;
                        global $conf;
                        $current_usr = pageinfo();  //to get current user as author
                        // real name: $current_usr['userinfo']['name']
                        // login:     $current_usr['client']
                        if($conf['showuseras'] == 'loginname') {
                          $default_value = $current_usr['client'];
                        }
                        elseif($conf['showuseras'] == 'username') {
                          // real name
                          $default_value = $current_usr['userinfo']['name'];
                        }
                        elseif($conf['showuseras'] == 'email') {
                          // ofuscated mail address according mailguard settings
                          $default_value = $current_usr['userinfo']['name'];
                        }
                        elseif($conf['showuseras'] == 'email_link') {
                          $default_value = $current_usr['userinfo']['name']; 
                        }
                        else $default_value = "";
                        
                        $output .= '<p>'.trim($fields[4]).'
                                      <input class="news_input_'.trim($fields[0]).
                                          '" id="news_input_'.trim($fields[0]).
                                          '" name="news_input_'.trim($fields[0]).
                                          '" type="'.trim($fields[1]).
                                          '" '.trim($fields[2]). 
                                           ' value="'.$default_value.'" title="'.trim($this->getLang(trim($fields[5]))).
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
                               type="submit" 
                               name="submit" 
                               value="'.$this->getLang('anss_input_btn_save').'" 
                               title="'.$this->getLang('anss_input_btn_save_descr').'" />'.NL;
            $output .= '</form></div>';
            // 3. check if path/file exist on save click
            // 4. add the new post before the existing (e.g. news:newsdata.txt)
            $renderer->doc .= $output;
        }

      /*------- show perview -------------------------------------------------*/      
        if (strpos($ans_conf['param'], 'flash')!== false) {
          $info        = array();
          $tmp         = substr($ans_conf['param'],strlen('flash')); //strip parameter to get set of add parameter
          $prefs       = explode(',',$tmp);
          // $prefs[0] = preview length
          // $prefs[1] = box width
          // $prefs[2] = float option
          // $prefs[3] = max items
          // $prefs[4] = tags separated by pipe
          if(!isset($prefs[4])) $tag_flag = true;
          
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
          $output = '<div class="news_box" '.$prefs[1].'>';
          if($this->getConf('newsflash_link') == false) {
              $output .= '<div  class="news_header">'.$this->getLang('newsflash_title').'</div>'.NL;
          }
          else {
              $output .= '<div  class="news_header"><a class="news_header_link" href="'. $allnewsdata .'">'.$this->getLang('newsflash_title').'</a></div>'.NL;
          }
          $output .= '<div class="news_list" '.$item_width.'">'.NL;

          // 1. read news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
//          $entries = explode("\n----\n\n",$oldrecord);
          $entries = explode("======",$oldrecord);
          foreach($entries as $entry) {
             // split news block into line items
             $temp_array = explode("\n  * ",$entry);
             unset($temp_array[0]);
             
             // 2. create preview output
             // split line items into key and data
             $aFlag = false;
             $bFlag = false;
             foreach ($temp_array as $item) {
                    list($key, $value) = split(":",trim($item),2);
                    $tag_flag = false;
                    
                    if($key=='anchor') {
                            $anchor = trim($value);
                        }
                    elseif(($key=='start') && strtotime(trim($value)) < time()) {
                        $aFlag = true;
                        $value = date($this->getConf('d_format'), strtotime($value));
                        $news_date = '<span class="news_date"> ('. $value ;
                    }
                    elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                        $bFlag = true;
                    }
                    elseif($key=='text'){                      
//                        $prvw_string = substr( trim( preg_replace ('/\<.*?\>/', ' ', $value ) ), 0, $preview_length );
                        $prvw_string = p_render('xhtml',p_get_instructions($value),$info);
                        $preview_string = '<span class="news_preview">' . $prvw_string .'</span>'. NL;
                    }
                    // head has to be before the link in the template !
                    elseif($key=='head'){
                        $news_head = $value;                        
                             // add edit button to section edit the article if edit  
                             // permission is given to that current user for this ID
                             if($current_usr["perm"]>1) {
                                 // detect start and stop of section
                                 $news_rawcontent = rawWiki($targetpage);                                
                                 $start= stripos($news_rawcontent,$value)-5;
                                 $tmp = explode("====== ",$news_rawcontent);
                                 foreach($tmp as $temps) {
                                    if(stripos($temps,$value)!==false) {
                                        $stop = strlen($temps)+$start+6;
                                        break;
                                    }                                    
                                 }
                                 // assamble the pieces for the button and form.
                                 $url = wl($this->getConf('news_datafile'),'',true);

                                 $ank = '<div><form class="btn_secedit" 
                                              method="post" 
                                              action="'.$url.'">
                                                <input type="hidden" name="do" value="edit" />
                                                <input type="hidden" name="summary" value="['.$value.'] " />
                                                <input type="hidden" name="target" value="section" />
                                                <input type="hidden" name="range" value="'.$start.'-'.$stop.'" />
                                                <input class="anss_edit_img" type="image" src="'.DOKU_BASE.'lib/plugins/anewssystem/images/dot2.gif" alt="'.$this->getLang('anss_edit_imgttl').'" title="'.$this->getLang('anss_edit_imgttl').'" value="Edit" />
                                          </form>
                                          <span style="width:3em;">&nbsp;</span>';
                                 // add a delete button and $POST
                                 $ank .= '<form class="anss_delete" 
                                              method="post" >
                                              <input type="hidden" name="anss_del_anchor" value="'.$anchor.'"/>
                                              <input type="hidden" name="anss_del_record" value="'.$news_head.'"/>        
                                              <input class="anss_del_img" type="image" src="'.DOKU_BASE.'lib/plugins/anewssystem/images/dot.gif" alt="Del" title="'.$this->getLang('del_title').'" />        
                                          </form>
                                          </div>';         
                             }
                             else $ank='';
                    }
                    elseif($key=='link'){                      
                        $news_head = '<a class="news_link" href="'.$value.'">'. $news_head .'</a>'.NL;
                    }
                    elseif($key=='author'){                      
                        $news_date .= ', '. $value;
                    }
                    elseif(($key=='tags') && (isset($prefs[4]) !== false)) {
                        $tags = explode(',',$value);
                        foreach($tags as $tag) {
                            if(($tag!==false) && (stripos($prefs[4],trim($tag))!==false)){
                                $tag_flag = true;
                                break;
                            }
                        }
                    }
             }
             if(isset($prefs[4]) == false) $tag_flag = true;
             $news_date .=  ')</span><br />'.NL;
             if(($aFlag === true) && ($bFlag === true) && ($tag_flag === true)) {
                 $output .= '<div class="prev_newsitem">'.$news_head.$news_date.$preview_string.$ank.'</div>'.NL;
                 $item_counter = $item_counter + 1;                 
                 // stop if max number of items is reached
                 if (isset($prefs[3]) && ($item_counter == $prefs[3])) {
                    break; }
             }    
          }
          $output .= '</div></div>'.NL.NL;
          $renderer->doc .= $output;
        }

        /* --- Show all news -------------------------------------------------*/
        if (strpos($ans_conf['param'], 'allnews')!== false) {
          $tmp         = substr($ans_conf['param'],strlen('allnews')); //strip parameter to get set of add parameter
          $prefs       = explode(',',$tmp);
          // $prefs[1] = tags filter
          $newsitems = array();
          // this will be called to display all news articles
          $page = wl( (isset($targetpage) ? $targetpage : 'news:newsdata') );          
          $output = '<div class="allnews_box">'.NL;
          // 1. read news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
//          $entries = explode("\n----\n\n",$oldrecord);
          $entries = explode("======",$oldrecord);
          $info = array();
                    
          foreach($entries as $entry) {
             // split news block into line items
             $temp_array = explode("\n  * ",$entry);
             unset($temp_array[0]);
             
             // 2. create output
             // split line items into key and data
             $aFlag = false;
             $bFlag = false;

                 foreach ($temp_array as $item) {
                        list($key, $value) = split(":",trim($item),2);
                        $tag_flag = false;
                        if($key=='anchor') {
                            $anchor = trim($value);
                        }
                        elseif(($key=='start') && strtotime(trim($value)) < time()) {
                            $aFlag = true;
                            $value = date($this->getConf('d_format'), strtotime($value));
                            $news_date = '<span class="news_date"> ('. $value ;
                        }
                        elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                            $bFlag = true;
                        }
                        elseif($key=='text'){                      
                            // parse value for DW syntax ?
                            $preview_string = p_render('xhtml',p_get_instructions($value),$info);
//                            $preview_string = '<div class="all_news_article">' . $preview_string . '</div>'.NL;
                        }
                        // head has to be before the link in the template !
                        elseif($key=='head'){
                             $news_head = trim($value);                        
                             // add edit button to section edit the article if edit  
                             // permission is given to that current user for this ID
                             if($current_usr["perm"]>1) {
                                 // detect start and stop of section
                                 $news_rawcontent = rawWiki($targetpage);                                
                                 $start= stripos($news_rawcontent,$value)-5;
                                 $tmp = explode("====== ",$news_rawcontent);
                                 foreach($tmp as $temps) {
                                    if(stripos($temps,$value)!==false) {
                                        $stop = strlen($temps)+$start+6;
                                        break;
                                    }                                    
                                 }
                                 // assamble the pieces for the button and form.
                                 $url = wl($this->getConf('news_datafile'),'',true);
                                 $ank = '<div><form class="btn_secedit" 
                                              method="post" 
                                              action="'.$url.'">
                                                <input type="hidden" name="do" value="edit" />
                                                <input type="hidden" name="summary" value="['.$value.'] " />
                                                <input type="hidden" name="target" value="section" />
                                                <input type="hidden" name="range" value="'.$start.'-'.$stop.'" />
                                                <input class="anss_edit_img" type="image" src="'.DOKU_BASE.'lib/plugins/anewssystem/images/dot2.gif" alt="'.$this->getLang('anss_edit_imgttl').'" title="'.$this->getLang('anss_edit_imgttl').'" value="Edit" />
                                          </form>
                                          <span style="width:3em;">&nbsp;</span>';
                                 // add a delete button and $POST
                                 $ank .= '<form class="anss_delete" 
                                              method="post" >
                                              <input type="hidden" name="anss_del_anchor" value="'.$anchor.'"/>
                                              <input type="hidden" name="anss_del_record" value="'.$news_head.'"/>        
                                              <input class="anss_del_img" type="image" src="'.DOKU_BASE.'lib/plugins/anewssystem/images/dot.gif" alt="Del" title="'.$this->getLang('del_title').'" />        
                                          </form>
                                          </div>';         
                             }
                             else $ank='';
                        }
                        elseif($key=='link'){                      
                            $news_head = '<span class="allnews_head">'. $news_head .'</span>'.NL;
                        }
                        elseif($key=='author'){                      
                            $news_date .= ', '. $value;
                        }
                        elseif(($key=='tags') && (isset($prefs[1]) !== false)) {
                            $tags = explode(',',$value);
                            foreach($tags as $tag) {
                                if(($tag!==false) && (stripos($prefs[1],trim($tag))!==false)){
                                    $tag_flag = true;
                                    break;
                                }
                            }
                        }
                 }
                 $news_date .=  ')</span><br />'.NL;
                 
                 if(isset($prefs[1]) == false) $tag_flag = true;
                 if(($aFlag === true) && ($bFlag === true) && ($tag_flag === true)) {
                     $output .= '<div>'.NL.$news_head.NL.$news_date.NL.$preview_string.NL.$ank.NL.'</div>'.NL;
                 }    
          }
          $output .= '</div><div style="clear: both;"></div>'.NL.NL;
          $renderer->doc .= $output;
        }
        // --- faulty syntax ---
        elseif (($ans_conf['param']!='author') && (strpos($ans_conf['param'], 'flash')=== false)) {
          $renderer->doc .= msg('Syntax of anewssystem plugin detected but an unknown parameter  ['.$ans_conf['param'].'] was provided.', -1);
        }
    }
}
?>