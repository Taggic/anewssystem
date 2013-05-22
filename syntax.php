<?php
 
/**
 * Plugin anewssystem: provides an easy to handle, page based news system
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Taggic <taggic@t-online.de>
 */
 
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
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
          msg('Syntax of anewssystem detected but unknown parameter was attached.', -1);          
        }
        else { return $ans_conf;}        
     }
/******************************************************************************/
/* render output
* @author Taggic <taggic@t-online.de>
*/   
    function render($mode, &$renderer, $ans_conf) {
        global $ID;
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
        $i            = strripos($allnewsdata, ":");
        $news_root    = substr($allnewsdata, 0, $i);          
        // check if user has write permission on that ID
        $current_usr = pageinfo();
        // necessary for the back link of a show one article per page (SOAPP)
        if(stripos($_GET['archive'],'archive')!== false) $ans_conf['param'] = $_GET['archive'];
        $_GET['archive']="";  
                        
        // 1. read template (plugins/anewssystem/template.php)
        $template   = file_get_contents(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
      /*------- add news action part -----------------------------------------*/
            $post_prefix   = $_POST["xs-".$prefix];
            $delete_record = $_POST["anss_del_record"];
            $delete_anchor = $_POST["anss_del_anchor"];
//            msg($delete_record." = |".$delete_anchor.'|',0);
            
            if(!isset($delete_anchor)) $delete_anchor = $delete_record; // if anchor field was deleted on input 
            
            
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
            elseif( (strlen($delete_record)>2) && (auth_quickaclcheck($targetpage) >= AUTH_EDIT) ) {
                $raw_records = rawWiki($targetpage);
                $news_record = explode("====== ",$raw_records);
                foreach($news_record as $record) {
                  
                  if((stripos($record, $delete_record)!==false) && (stripos($record, $delete_anchor)!==false))  {
                    // inform user
//                    msg("Delete: $record = ".$delete_record,0);
//                    msg("Anchor: $record = ".$delete_anchor,0);
                    msg('News Article deleted.',1);
                  }
                  else { if(strlen($record)>1) $news_records.= "====== ".$record;}
                }
                // write file
                saveWikiText($targetpage, $news_records, "New entry", true);
            }    
      /*------- show user form -----------------------------------------------*/            
            // this will provide the user form to add further news
            // 2. create input form based on template          
        if ($ans_conf['param']==='author') {
            
            $output .= '<span><script type="text/javascript">
                          function count_chars(obj, max) {
                              var data = obj.value;
                              var extract = data.split(" ");
                              var bextract = data.split("\n");
                              var cextract = extract.length + bextract.length -1;
                              if(cextract>max) output = \'<span style="color:red;">\' + cextract + \'</span>\';
                              else output = cextract;
                              document.getElementById("nws_charcount").innerHTML =  "&nbsp;&nbsp;(word count: " + output + " of " + max + " )"
                          }
                          
                          function resizeBoxId(obj,size) {
                              var arows = document.getElementById(obj).rows;
                              document.getElementById(obj).rows = arows + size;
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
                                             name="nws_charcount">&nbsp;&nbsp;(word count: 0 of '.$this->getConf('prev_length').' )</label><br />';   

                        $output .= $this->news_edit_toolbar('news_input_'.trim($fields[0]));
                        
                        $imgBASE = DOKU_BASE."lib/plugins/anewssystem/images/toolbar/";
                        $output .= '<textarea class="news_input_textarea"'. 
                                            ' id="news_input_'.trim($fields[0]).'"'.
                                            ' name="news_input_'.trim($fields[0]).'"'.
                                            ' title="'.trim($this->getLang(trim($fields[5]))).'" '.trim($fields[2]).'"'.
                                            ' onkeyup="count_chars(this,'.$this->getConf('prev_length').')" >'.
                                      '</textarea>
                                      <span class="reply_close_link">
                                      <a href="javascript:resizeBoxId(\'news_input_'.trim($fields[0]).'\', -20)"><img src="'.$imgBASE.'reduce.png" title="reduce textarea" style="float:right;" /></a>
                                      <a href="javascript:resizeBoxId(\'news_input_'.trim($fields[0]).'\', +20)"><img src="'.$imgBASE.'enlarge.png" title="enlarge textarea" style="float:right;" /></a>
                                      </span></p>'.NL;
                }
                else if (trim($fields[0]) == "anchor") {

                        $default_anker = date("YmdHis");
                        if($this->getConf('soapp')>0) $link_anker = '&anchor='.$default_anker; // to show only one article only on a page
                        else $link_anker = '#'.$default_anker;  // to show all news at one page but scroll to the anchor position
                        $default_anker = '#'.$default_anker;
                        
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
                        $default_value = wl($allnewsdata1).$link_anker;
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
        elseif (strpos($ans_conf['param'], 'flash')!== false) {
          $info        = array();
          $tmp         = substr($ans_conf['param'],strlen('flash')); //strip parameter to get set of add parameter
          $prefs       = explode(',',$tmp);
          // $prefs[0] = preview length
          // $prefs[1] = box width
          // $prefs[2] = float option
          // $prefs[3] = max items
          // $prefs[4] = tags separated by pipe
          if(!isset($prefs[4])) $tag_flag = true;
          
          if($prefs[0]<10) $prefs[0] = $this->getConf('prev_length');
          $preview_length = $prefs[0];

          if(! isset($prefs[1])) { 
            $prefs[1]='';
            $item_width = '';}
          else {
            $a=0.5; 
            $item_width = 'style="margin-right: 10px !important;"';
            $prefs[2] = "float: ".$prefs[2].";";
            $prefs[1] = 'style="width: '.$prefs[1].'; '.$prefs[2].'"';} 
          
          if($prefs[3]==0) $prefs[3]=5;
          
          $newsitems = array();
          // this will be called to display a preview
          $output  = '<div class="news_box" '.$prefs[1].'>';
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
                        if($bFlag !== true) break;                      
                        // replace media links
                        $linkx = array();
                        $pattern = '/\{\{.*?\}\}/i';
                        $linkx = $this->replace_links($pattern, $value, 'medi');
                        
                        // replace hyperlinks
                        $links = array();
                        $pattern = '/\[\[.*?\]\]/i';
                        $links = $this->replace_links($pattern, $value, 'url');
                        
                        // shrink the output according settings
                        //$prvw_string = substr( preg_replace ('/\<.*?\>/', ' ', $value ) , 0, $preview_length );
                        $check = explode(' ', $value);
                        $i=0;
                        $prvw_string ='';
                        foreach($check as $a) {
                            $prvw_string .= $a.' ';
                            $i++; 
                            if($i>$preview_length) {break;}
                        }
                        if(count($check)-1>$preview_length) $prvw_string .= ' ...';
                        
                        // replace placeholder
                        $links = $this->replace_placeholder($links, $prvw_string, 'url');
                        $linkx = $this->replace_placeholder($linkx, $prvw_string, 'medi');
                                                
                        $prvw_string = p_render('xhtml',p_get_instructions($prvw_string),$info);
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
                                 // assemble the pieces for the button and form.
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
                        $prev_id++;
                        $news_head   = '<a class="news_link" href="'.$value.'"  id="news_link'.$prev_id.'" rel="subcontent'.$prev_id.'">'. $news_head .'</a>'.NL;
                        // generate an overlap div with Thumbshot picture if link is provided by conf
                        if($this->getConf('convert')) {
                            if(is_string($response)) {
    	      		               msg($response,-1);
    	      	              }
                            else {
                              list($link,$image) = $response;
                              $anID = explode("id=",$value);
                              if($anID[1]) $theLink = DOKU_URL.'doku.php?id='.$anID[1];
                              else $theLink = $value;
                              $theLink = sprintf($this->getConf('convert'), $theLink);
                              $news_head  .= '
                              <DIV id="subcontent'.$prev_id.'" class="news_subcontent">
                                  <a class="news_link" href="'.$value.'" target="_blank">
                                    <img class="news_subcontent_pic" alt="News" src="'.$theLink.'" a="">
                                  </a><br />
                              </DIV>'.NL;                            // anchorid,             pos,        glidetime, revealbehavior
                              $news_head  .= '<script type="text/javascript">'.  
                                             '   dropdowncontent.init("news_link'.$prev_id.'", "left", 500, "mouseover")'.
                                             '</script>'.NL;
                            }
                        }
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
             $news_date  .=  ')</span><br />'.NL;
             if(($aFlag === true) && ($bFlag === true) && ($tag_flag === true)) {
                 $output .= '<div class="prev_newsitem">'.$news_head.$news_date.$preview_string.$ank.'</div>'.NL;
                 $item_counter = $item_counter + 1;                 
                 // stop if max number of items is reached
                 if (isset($prefs[3]) && ($item_counter == $prefs[3])) {
                    break; }
             }    
          }
          if($item_counter==0)
          {
              $output .= '<div class="prev_newsitem">'.$this->getLang('noNews').'</div>'.NL;
          }
          $output        .= '</div></div>'.NL.NL;
          $output         = '<script type="text/javascript" src="'.DOKU_URL.'lib/plugins/anewssystem/dropdowncontent.js"></script>'.$output; 
          $renderer->doc .= $output;
        }
        /* --- Display a cloud of News Tags --------------------------------*/
        elseif (strpos($ans_conf['param'], 'cloud')!== false) {
          $tmp         = substr($ans_conf['param'],strlen('cloud')); //strip parameter to get set of add parameter          
          $oldrecord   = rawWiki($targetpage);
          $entries     = explode("======",$oldrecord);
          // loop through configured all news page
          foreach($entries as $entry) {
              // split news block into line items
              $temp_array = explode("\n  * ",$entry);
              unset($temp_array[0]);
              $aFlag    = false;
              $bFlag    = false;
              $tag_flag = false;
              
              // if perishing date is not exceeded then collect the tags
              foreach ($temp_array as $item) {
                  list($key, $value) = split(":",trim($item),2);
                  if(($key=='start') && strtotime(trim($value)) < time()) {
                      $aFlag = true;
                      $value = date($this->getConf('d_format'), strtotime($value));
                      $news_date = '<span class="news_date"> ('. $value ;
                  }
                  elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                      $bFlag = true;
                  }
                  if($key=='tags') {
                      if(($aFlag !== true) || ($bFlag !== true)) break;
                      $aFlag = false;
                      $bFlag = false;
                      $tags  = explode(',',$value);
                      if(count($tags) >0 ) {
                          foreach($tags as $tag) {
                              $tags_result[$tag]++;
                          }
                          break;
                      }
                  }
              }
          }    

          // evaluate the styling parameters    
          $tokens = preg_split('/\s+/', $tmp,-1, PREG_SPLIT_NO_EMPTY);   
          $div_class = 'newsclouddiv';
          foreach ($tokens as $token) {
              
              if (preg_match('/^\d*\.?\d+(%|px|em|ex|pt|cm|mm|pi|in)$/', $token)) {
                $styles .= ' width: '.$token.';';
                continue;
              }
              if (preg_match('/^(
                  (\#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}))|        #color value
                  (rgb\(([0-9]{1,3}%?,){2}[0-9]{1,3}%?\))     #rgb triplet
                  )$/x', $token)) {
                $styles .= ' background-color: '.$token.';';
                continue;
              }
              if((stripos($token,'tleft')    !== false) || 
                 (stripos($token,'tright')   !== false) || 
                 (stripos($token,'tcenter')  !== false) ||
                 (stripos($token,'tjustify') !== false) ||
                 (stripos($token,'tinherit') !== false)) {
                $styles .= ' text-align: '.substr(trim($token),1).';';
                //style the tabs properly
                if(stripos($token,'tright')!== false) {
                  $tab_right = "";
                  $tab_left = DOKU_TAB;
                }
                else {
                  $tab_right = "";
                  $tab_left = DOKU_TAB;
                }
                continue;
              }
              if((stripos($token,'fleft')    !== false) || 
                 (stripos($token,'fright')   !== false) || 
                 (stripos($token,'fnone')    !== false) ||
                 (stripos($token,'finherit') !== false)) {
                $styles .= ' float: '.substr(trim($token),1).';';
                continue;
              }
              if((stripos($token,'uppercase')  !== false) || 
                 (stripos($token,'capitalize') !== false) || 
                 (stripos($token,'lowercase')  !== false)) {
                $styles .= ' text-transform: '.$token.';';
                continue;
              }
              if(stripos($token,'newsclouddiv')    !== false) {
                $div_class = $token;
                continue;
              }
          }
          // output the tags as links
          $output  = '<div class="'.$div_class.'" style="'.$styles.'">'.NL;
          // loop through the tags array and evaluate the size per tag
          $min = 1000;
          $max = 1;
          
          if(count($tags_result)<1) $output.='<span>no tags found</span>';
          else {
              foreach($tags_result as $tag => $val) {
                  $min = min($val,$min);
                  $max = max($val,$max);
              }
              $delta = ($max-$min)/16; 
              foreach($tags_result as $tag => $val) {
                  if ($val < $min+round($delta)) $class = 'newscloud1';
                  elseif ($val < $min+round(2*$delta)) $class = 'newscloud2';
                  elseif ($val < $min+round(4*$delta)) $class = 'newscloud3';
                  elseif ($val < $min+round(8*$delta)) $class = 'newscloud4';
                  else $class = 'newscloud5';
                  $output .= $tab_left.'<a href="'.DOKU_URL.'doku.php?id='.$this->getConf('news_output').'&tag='.trim($tag).'" class="' . $class .'"title="'.$val.'">'.$tag.'</a>'.$tab_right.NL;
              }
          }
          $output .= '</div>'.NL;  
          $renderer->doc .= $output;
        }
        /* --- Show all news -------------------------------------------------*/
        elseif ((strpos($ans_conf['param'], 'allnews')!== false)) {
          // check if page ID was called with tag filter
          $tmp         = ','.$_GET['tag'];    // this will overrule the page syntax setting
          if(strlen($tmp)<2) {
              // strip parameter to get set of add parameter
              // there exist either 'tag' or 'anchor', never both at the same time
              $tmp     = substr($ans_conf['param'],strlen('allnews')); 
          }
          $prefs           = explode(',',$tmp); // one or multiple tag filters: $prefs[1] ... [n]
          // $prefs[0] = preview length
          // $prefs[1] = not used but comme must be existing to delim further params
          // $prefs[2] = not used but comme must be existing to delim further params
          // $prefs[3] = max items
          // $prefs[4] = tags separated by pipe
          if($prefs[0]<10) $prefs[0] = $this->getConf('prev_length');
          $preview_length = $prefs[0];

          $prefs['anchor'] = $_GET['anchor'];   // this will overrule the page syntax setting to 
                                                // show just the one article instead all of them
          
          // necessary for the back link of a show one article per page (SOAPP)
//          if($_GET['archive']=='archive') $ans_conf['param'] = 'archive';
          
          $newsitems   = array();
          // this will be called to display all news articles
          $page = wl( (isset($targetpage) ? $targetpage : 'news:newsdata') );          
          $output = '<div class="allnews_box">'.NL;
          // 1. read news file (e.g. news:newsdata.txt)
          $av = 0;
          $oldrecord = rawWiki($targetpage);
          $entries = explode("======",$oldrecord);
          $info = array();
          $yh_level = $this->getConf('yh_level');
          $mh_level = $this->getConf('mh_level');
          $h_level = $this->getConf('h_level');
                    
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
/*                            if (strpos($ans_conf['param'], 'archive')!== false) {
                                 $news_date = '<span class="news_date_a"> ('. $value;
                            }
                            else */$news_date = '<span class="news_date"> ('. $value ;
                        }
                        elseif(($key=='stop') && strtotime(trim($value)) > time()) {
                            $bFlag = true;
                        }
                        elseif($key=='text'){                      
                            // replace media links
                            $linkx = array();
                            $pattern = '/\{\{.*?\}\}/i';
                            $linkx = $this->replace_links($pattern, $value, 'medi');
                            
                            // replace hyperlinks
                            $links = array();
                            $pattern = '/\[\[.*?\]\]/i';
                            $links = $this->replace_links($pattern, $value, 'url');
                            
                            if(isset($prefs['anchor'])!==true) {
                                // shrink the output according settings
                                //$prvw_string = substr( preg_replace ('/\<.*?\>/', ' ', $value ) , 0, $preview_length );
                                $check = explode(' ', $value);
                                $i=0;
                                $prvw_string ='';
                                foreach($check as $a) {
                                    $prvw_string .= $a.' ';
                                    $i++; 
                                    if($i>$preview_length) {break;}
                                }
                                if(count($check)-1>$preview_length) $prvw_string .= ' ...';
                            }
                            else {
                              $prvw_string = $value;
                            }
                            // replace placeholder
                            $links = $this->replace_placeholder($links, $prvw_string, 'url');
                            $linkx = $this->replace_placeholder($linkx, $prvw_string, 'medi');
                                                    
                            $prvw_string = p_render('xhtml',p_get_instructions($prvw_string),$info);
                            $preview_string = '<span class="news_preview">' . $prvw_string .'</span>'. NL;
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
                                 // assemble the pieces for the button and form.
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
                        elseif($key=='subtitle'){
                             $news_subtitle = '<br /><span class="news_subtitle">'.trim($value).'</span>'.NL;                        
                        }
                        elseif($key=='link'){                      
                            $news_head = '<a href="'.$value.'" id="'.$value.'" name="'.$value.'">'. $news_head .'</a>'.NL;
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
                 
                 $news_date .=  ')</span>'.NL;
                 
                 // prevent output of date and author of ho parameter is given as off
                 if ((strpos($ans_conf['param'], 'ho=off')!== false)) { $news_date =''; }

                 if((isset($prefs[1]) === false) || (strlen($prefs[1]) <2)) $tag_flag = true;                 
                 
                 if(($aFlag === true) && ($bFlag === true) && ($tag_flag === true)) {
                     $output .= '<div>'.NL.'<h'.$h_level.'>'.$news_head.$news_date.$news_subtitle.'</h'.$h_level.'>'.NL.$preview_string.NL.$ank.NL.'</div>'.NL;
                     $item_counter++;
                 }
                 elseif(isset($prefs['anchor'])===true) {
                      // show the single article independently if it is current or outdated
                      $output .= '<div>'.NL.'<h'.$h_level.'>'.$news_head.$news_date.$news_subtitle.'</h'.$h_level.'>'.NL.$preview_string.NL.$ank.NL.'</div>'.NL;
                 }
                 $news_subtitle='';                 
                // --- just ouput only the linked article on the page ----------
                $archive_lnkTitle = $this->getConf('lnk_newsarchive');
                if($archive_lnkTitle=='') $archive_lnkTitle = "News Archive";
                if((strlen($anchor)>2) && (isset($prefs['anchor'])!==false)) {
                  if(stripos($anchor,$prefs['anchor']) !== false) {
        $backlink = '<a href="javascript:history.back(-1)">'.$this->getLang('lnk_back').'</a>';
        $backlink .= '<span class="anss_sep"> &nbsp;|&nbsp;</span>
                      <a href="'.DOKU_URL.'doku.php?id='.$this->getConf('news_output').'">'.$this->getLang('allnews').'</a>';
                      $output = '<script type="text/javascript" src="backlink.js"></script>
                                  <script type="text/javascript">
                                    <!--
                                    var gb = new backlink();
                                    gb.write();
                                    //-->
                                  </SCRIPT>
                                 <div style="font-size:.85em;">'.$backlink.NL.
                                '<span class="anss_sep">&nbsp;|&nbsp;</span><a class"wikilink" href="'.wl($ID).'&archive=archive">'.$archive_lnkTitle.'</a></div><br />'.NL.
                                '<div class="archive_section" id="news_archive_head"  style="'.$archive_options['style'].'">'.
                                  $output.
                                '<div style="font-size:.85em;">'.$backlink.NL. 
                                '<span class="anss_sep">&nbsp;|&nbsp;</span><a class"wikilink" href="'.wl($ID).'&archive=archive">'.$archive_lnkTitle.'</a></div><br />'.NL;          
                      break;  // due to the single linked article is loaded into $output
                  }
                }
                if(isset($prefs['anchor']) === true) {
                   $output = '';  // to strip away all other articles
                }   
          }
          
          if($item_counter==0)
          {
              $output .= '<span>'.$this->getLang('noNews').'</span>'.NL;
          }
          $output .= '</div><div style="clear: both;"></div>'.NL.NL;
          $renderer->doc .= $output;
        }       
/* --- Show archive ----------------------------------------------------------*/
        elseif ((strpos($ans_conf['param'], 'archive')!== false)) {
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
//          echo $archive_options['tag'].'<br />';
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
          
          if($archive_options['class']=='toc') {
              $output = '<script type="text/javascript">
                             function archive__toc_open(toggle_id, img_ID) 
                              {   if (document.getElementById(toggle_id).style.display == "none")
                                  {   document.getElementById(toggle_id).style.display = "block";
                                      document.getElementById(img_ID).style.backgroundPosition = "0px 0px";
                                  }
                                  else
                                  {   document.getElementById(toggle_id).style.display = "none";
                                      document.getElementById(img_ID).style.backgroundPosition = "0px -5px";
                                  }
                              } 
                         </script>
                         <div class="archive_box" id="archive__toc"  style="'.$archive_options['style'].'">
                            <h3 class="toggle open" style="cursor: pointer;" onClick="archive__toc_open(\''.$blink_id.'\',\''.$img_ID.'\')">
                              <strong id="img_archive__toc"></strong>
                              NEWS
                            </h3>
                             <div id="news_items">
                                 <div style="text-align:left;">
                                 <ul class="n_box">'.$output.'</ul>
                                 </div
                                 <hr />
                                 <div style="text-align:right;font-size:.85em; border-top: 1px dotted #828282;">
                                    <a href="'.$news_root.':allnewsdata&do=shownewsarchive">&raquo; News Archive</a>
                                 </div>
                             </div>
                          </div>'.NL.NL;
          }
          elseif($archive_options['class']=='box') {
              $output = '<div class="archive_box" id="archive__box" style="'.$archive_options['style'].'">
                            <div id="news_items">
                                <ul class="n_box">'.$output.'</ul>
                             </div>
                         </div>'.NL;
          }                     
          $renderer->doc .= $output;
        }

// --- faulty syntax ----------------------------------------------------------
        else {
          $renderer->doc .= msg('Syntax of anewssystem plugin detected but unknown parameter  ['.$ans_conf['param'].'] was provided.', -1);
        }
    }
//---------------------------------------------------------------------------------------
    // flatten the hierarchical arry to store path + file at first "column"
    function array_flat($array) {   
        $out=array();
        foreach($array as $k=>$v){  
            if(is_array($array[$k]))  { $out=array_merge($out,$this->array_flat($array[$k])); }
            else  { $out[]=$v; }
        }     
        return $out;
    }
//---------------------------------------------------------------------------------------
    function replace_links($pattern, &$value, $r_string) {
        // check for links and replace them by placeholder
        preg_match_all($pattern, $value, $links);
        $in=0;
        foreach($links[0] as $link) {
            $in++;
            $value = str_replace($link,$r_string.$in,$value);
        }
        return $links;
    
    }
//---------------------------------------------------------------------------------------
    function replace_placeholder($links, &$prvw_string, $r_string) {
        $in=0;
        foreach($links[0] as $link) {
            $in++;
            $prvw_string = str_replace($r_string.$in,$link,$prvw_string);
        }
        return $links;
    }

/******************************************************************************/
/* return html-code for news edit toolbar                                     */
    function news_edit_toolbar($type) {
        $imgBASE = DOKU_BASE."lib/plugins/anewssystem/images/toolbar/";
        $news_edit_tb .= '<script type="text/javascript">
          function doHLine(tag1,obj)
          { textarea = document.getElementById(obj);
          	if (document.selection) 
          	{     // Code for IE
          				textarea.focus();
          				var sel = document.selection.createRange();
          				sel.text = "\n" + tag1 + "\n" + "\n" + sel.text;
          	}
            else 
            {   // Code for Mozilla Firefox
             		var len = textarea.value.length;
             	  var start = textarea.selectionStart;
             		var end = textarea.selectionEnd;
              		
             		var scrollTop = textarea.scrollTop;
             		var scrollLeft = textarea.scrollLeft;
              		
                var sel = textarea.value.substring(start, end);
         		    var rep = tag1 + sel;
                textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
              		
             		textarea.scrollTop = scrollTop;
             		textarea.scrollLeft = scrollLeft;
          	}
          }'.

         'function doAddTags(tag1,tag2,obj)
          { textarea = document.getElementById(obj);
          	// Code for IE
          	if (document.selection) 
          			{ textarea.focus();
          				var sel = document.selection.createRange();
                  if (sel.text == "") sel.text = " ";
          				sel.text = tag1 + sel.text + tag2;
          			}
             else 
              {  // Code for Mozilla Firefox
          		  var len = textarea.value.length;
          	    var start = textarea.selectionStart;
          		  var end = textarea.selectionEnd;
          		
          		  var scrollTop = textarea.scrollTop;
          		  var scrollLeft = textarea.scrollLeft;
          		
                var sel = textarea.value.substring(start, end);
          		  if (start == end) { sel = " "; }
                var rep = tag1 + sel + tag2;
                textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
          		
          		  textarea.scrollTop = scrollTop;
          		  textarea.scrollLeft = scrollLeft;
          	}
          }'.
          
         'function doTT(obj)
          { textarea = document.getElementById(obj);
          	// Code for IE
          	if (document.selection) 
          			{ textarea.focus();
          				var sel = document.selection.createRange();
                  if (sel.text == "") sel.text = " ";
          				sel.text = "\'\'" + sel.text + "\'\'";
          			}
             else 
              {  // Code for Mozilla Firefox
          		  var len = textarea.value.length;
          	    var start = textarea.selectionStart;
          		  var end = textarea.selectionEnd;
          		
          		  var scrollTop = textarea.scrollTop;
          		  var scrollLeft = textarea.scrollLeft;
          		
                var sel = textarea.value.substring(start, end);
                if (start == end) { sel = " "; }
          		  var rep = "\'\'" + sel + "\'\'";
                textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
          		
          		  textarea.scrollTop = scrollTop;
          		  textarea.scrollLeft = scrollLeft;
          	}
          }'.

          'function doList(tag1,obj)
          {
              textarea = document.getElementById(obj);

          		if (document.selection) 
          			{ // Code for IE
          				textarea.focus();
          				var sel = document.selection.createRange();
          				var list = sel.text.split("\n");
          		
          				for(i=0;i<list.length;i++) 
          				{ list[i] = tag1 + list[i]; }
          				sel.text = "\n" + list.join("\n") + "\n";
          			} 
              else
          			{ // Code for Firefox
          		    var len = textarea.value.length;
          	      var start = textarea.selectionStart;
          		    var end = textarea.selectionEnd;
          		    var i;

          		    var scrollTop = textarea.scrollTop;
          		    var scrollLeft = textarea.scrollLeft;

                  var sel = textarea.value.substring(start, end);
          		    var list = sel.split("\n");
          		
              		for(i=0;i<list.length;i++) 
          				{ list[i] = tag1 + list[i]; }

              		var rep = "\n" + list.join("\n") + "\n";
              		textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);

              		textarea.scrollTop = scrollTop;
              		textarea.scrollLeft = scrollLeft;
              }
          }
         </script>';                      
        $news_edit_tb .= '<div class="news_edittoolbar">'.NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."bold.png\"      name=\"btnBold\"          title=\"Bold [b]\"             accesskey=\"b\" onClick=\"doAddTags('**','**','$type')\">".NL;
        $news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."italic.png\"    name=\"btnItalic\"        title=\"Italic [i]\"           accesskey=\"i\" onClick=\"doAddTags('//','//','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."underline.png\" name=\"btnUnderline\"     title=\"Underline [u]\"        accesskey=\"u\" onClick=\"doAddTags('__','__','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."mono.png\"      name=\"btnMono\"          title=\"mono-spaced font [m]\" accesskey=\"m\" onClick=\"doTT('$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."strike.png\"    name=\"btnStrike\"        title=\"Strike through [d]\"   accesskey=\"d\" onClick=\"doAddTags('<del>','</del>','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."subscript.png\" name=\"btnSubscript\"     title=\"Subscript\"                            onClick=\"doAddTags('<sub>','</sub>','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."superscript.png\" name=\"btnSuperscript\" title=\"Superscript\"                          onClick=\"doAddTags('<sup>','</sup>','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."linkextern.png\" name=\"btnLink_extern\"  title=\"external Link [l]\"    accesskey=\"l\" onClick=\"doAddTags('[[',']]','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."hr.png\"        name=\"btnLine\"          title=\"Horizontal ruler [r]\" accesskey=\"r\" onClick=\"doHLine('----','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."ol.png\"        name=\"btn_o_List\"       title=\"Ordered List [-]\"     accesskey=\"-\" onClick=\"doList('  - ','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."ul.png\"        name=\"btn_u_List\"       title=\"Unordered List [*]\"   accesskey=\"*\" onClick=\"doList('  * ','$type')\">".NL;
//      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."h1.png\"        name=\"btn_u_List\"       title=\"Headline Level 1 (Page Title) [1]\" accesskey=\"1\" onClick=\"doAddTags('======','======','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."h2.png\"        name=\"btn_u_List\"       title=\"Headline Level 2 [2]\" accesskey=\"2\" onClick=\"doAddTags('=====','=====','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."h3.png\"        name=\"btn_u_List\"       title=\"Headline Level 3 [3]\" accesskey=\"3\" onClick=\"doAddTags('====','====','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."h4.png\"        name=\"btn_u_List\"       title=\"Headline Level 4 [4]\" accesskey=\"4\" onClick=\"doAddTags('===','===','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."h5.png\"        name=\"btn_u_List\"       title=\"Headline Level 5 [5]\" accesskey=\"5\" onClick=\"doAddTags('==','==','$type')\">".NL;
      	$news_edit_tb .= "<img class=\"newsedit_button\" src=\"".$imgBASE."code.png\"      name=\"btnCode\"          title=\"Code block [c]\"       accesskey=\"c\" onClick=\"doAddTags('<code>','</code>','$type')\">".NL;
        $news_edit_tb .= "<br></div>".NL; 
        return $news_edit_tb;                     
    }
}
?>