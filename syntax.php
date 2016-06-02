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
    function handle($match, $state, $pos,Doku_Handler &$handler) {
        global $ID, $conf;
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
        $ans_conf['newstemplate'] = DOKU_PLUGIN.'anewssystem/tpl/newstemplate_'.$conf['lang'].'.txt';
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
    function render($mode,Doku_Renderer &$renderer, $ans_conf) {
        global $ID, $conf;
        $xhtml_renderer = new Doku_Renderer_xhtml();
        $records      = file(DOKU_PLUGIN.'anewssystem/tpl/newstemplate_'.$conf['lang'].'.txt');
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
            
            if($this->getConf('wysiwyg')==true) { $myFunc = 'onsubmit="myFunction()"'; }
            $output .= '<div class="news_form_div">
                       <form class="news_input_form" id="'.$prefix.'" name="'.$prefix.'" method="POST" '.$myFunc.'>'.NL;
                            
            $output .= '<input type="hidden" name="xs-'.$prefix.'" value="check" />'.NL;

                               

            foreach ($records as $record) {
                $fields = explode('|',$record);
                if (trim($fields[1]) == "textarea") { 
                        $output .= '<p>'.trim($fields[4]); 
                        $output .= '<label class="nws_charcount" 
                                             id="nws_charcount"
                                             name="nws_charcount">'.$this->getLang('wordcount2').$this->getConf('prev_length').' )</label><br />';   
                        if($this->getConf('wysiwyg')==false) {
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
                        else {
                              $ansTBox_ID ='anss_textBox_'.trim($fields[0]);
                              $template    = file_get_contents(DOKU_PLUGIN.'anewssystem/tpl/newstemplate.txt');
                              $FontRecords = file(DOKU_PLUGIN.'anewssystem/tpl/fonts.txt');
                              foreach ($FontRecords as $FontDef) {
                                // $Font = font-family
                                $Font = explode(',', $FontDef);
                                $Font[0] = trim($Font[0]);      // font-family
                                $Font[1] = trim($Font[1]);      // font
                                $fontOptions .= '<option style="font-family:'.$Font[0].';" value="'.$Font[1].'" title="'.$Font[1].'" onclick="formatDoc(\'fontName\',\''.$Font[1].'\')">'.$Font[1].'</option>'.NL;
                              }
                              
                              $output .= '<input type="hidden" name="news_input_wysiwyg" value="1" />'.NL;
                              $output .= '<input type="hidden" id="news_input_text" name="news_input_text">
                
                              <div style="border: 1px dotted grey;padding-left:10px;border-radius:3px 3px 0px 0px;padding-top:4px;padding-bottom:2px;">
                              
                                  <div id="toolBar2" style="margin-top:3px;">                
                                      <img class="anss_intLink" title="'.$this->getLang("Undo").'" onclick="formatDoc(\'undo\');" src="data:image/gif;base64,R0lGODlhFgAWAOMKADljwliE33mOrpGjuYKl8aezxqPD+7/I19DV3NHa7P///////////////////////yH5BAEKAA8ALAAAAAAWABYAAARR8MlJq7046807TkaYeJJBnES4EeUJvIGapWYAC0CsocQ7SDlWJkAkCA6ToMYWIARGQF3mRQVIEjkkSVLIbSfEwhdRIH4fh/DZMICe3/C4nBQBADs=" />
                                      <img class="anss_intLink" title="'.$this->getLang("Redo").'" onclick="formatDoc(\'redo\');" src="data:image/gif;base64,R0lGODlhFgAWAMIHAB1ChDljwl9vj1iE34Kl8aPD+7/I1////yH5BAEKAAcALAAAAAAWABYAAANKeLrc/jDKSesyphi7SiEgsVXZEATDICqBVJjpqWZt9NaEDNbQK1wCQsxlYnxMAImhyDoFAElJasRRvAZVRqqQXUy7Cgx4TC6bswkAOw==" />
                                      <img class="anss_intLink" title="'.$this->getLang("Clean").'" onclick="oDoc.innerHTML=sDefTxt;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAjxJREFUSEu1k9tvEkEYxfv/mhgTEx9MGrVa9cE0plotEEIUxeCDFJuaiijXLpQ7hJBFIIRwk9tyJxgvx/mWYJmdTXkQH37Z7NlzTna+mdkC8F/QFTeBrriOfjiFwu4zFO4dQAklmSR6BGEdVPSVFf46PcfvjxHIO0/QkxLsE+/jXtahsAIq+nkiofv6A7pvTtXy3K3H6AZjzHLh5YKXQX8l39nHj+Mg2i+P0Xl1wpVnbj5C2x9h1oVfKNCj1WohmUzi+5EPLcsRWi/ec+XFB4dQDG+RuPGQ2RcZoUQLlcbjccznc0ynU3wzv+PK82zehfvP0Tu0I3J9l0UWOaFolWaziWg0itlshtFo9JdleY6NRr77FO0DG86v7aDpCbHYIiuULWk0GgiHw5hMJuj3+wKZ7T1k2aY1960IXb2N+heJxS7yXNmSer0OSZIwHo/R7XYFEokEUqkUqnsWnF3ZRu3zGYvxHdwLUavVEAgEMBwOQfPVEolEEIvFUCwW4XA4UP0UYDG+gxAEn8+HwWAAGoUWWgWNR5Zl2O125HI5FuHzSwTB7XarxdVqlcPv9yMYDCKbzcJqtapPbXYVQSiVSnA6nWp5pVJR8Xg88Hq9SKfTsFgsyGQyzMrntOiKtESbzYZyuaxu1BKTyaRumtavhyAoioJ8Pg+XywWz2ayWGY1GGAwG9c9pBTRvOh20wXTGtR2EIJCRQnTkqIAuCZ2GTqeDXq+njoguCd1CbXYVXXET6IqbQFfcBLriv4OtP5w57lyaBByNAAAAAElFTkSuQmCC" />
                                      <img class="anss_intLink" title="'.$this->getLang("Remove_formatting").'" onclick="formatDoc(\'removeFormat\')" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAAd0SU1FB9oECQMCKPI8CIIAAAAIdEVYdENvbW1lbnQA9syWvwAAAuhJREFUOMtjYBgFxAB501ZWBvVaL2nHnlmk6mXCJbF69zU+Hz/9fB5O1lx+bg45qhl8/fYr5it3XrP/YWTUvvvk3VeqGXz70TvbJy8+Wv39+2/Hz19/mGwjZzuTYjALuoBv9jImaXHeyD3H7kU8fPj2ICML8z92dlbtMzdeiG3fco7J08foH1kurkm3E9iw54YvKwuTuom+LPt/BgbWf3//sf37/1/c02cCG1lB8f//f95DZx74MTMzshhoSm6szrQ/a6Ir/Z2RkfEjBxuLYFpDiDi6Af///2ckaHBp7+7wmavP5n76+P2ClrLIYl8H9W36auJCbCxM4szMTJac7Kza////R3H1w2cfWAgafPbqs5g7D95++/P1B4+ECK8tAwMDw/1H7159+/7r7ZcvPz4fOHbzEwMDwx8GBgaGnNatfHZx8zqrJ+4VJBh5CQEGOySEua/v3n7hXmqI8WUGBgYGL3vVG7fuPK3i5GD9/fja7ZsMDAzMG/Ze52mZeSj4yu1XEq/ff7W5dvfVAS1lsXc4Db7z8C3r8p7Qjf///2dnZGxlqJuyr3rPqQd/Hhyu7oSpYWScylDQsd3kzvnH738wMDzj5GBN1VIWW4c3KDon7VOvm7S3paB9u5qsU5/x5KUnlY+eexQbkLNsErK61+++VnAJcfkyMTIwffj0QwZbJDKjcETs1Y8evyd48toz8y/ffzv//vPP4veffxpX77z6l5JewHPu8MqTDAwMDLzyrjb/mZm0JcT5Lj+89+Ybm6zz95oMh7s4XbygN3Sluq4Mj5K8iKMgP4f0////fv77//8nLy+7MCcXmyYDAwODS9jM9tcvPypd35pne3ljdjvj26+H2dhYpuENikgfvQeXNmSl3tqepxXsqhXPyc666s+fv1fMdKR3TK72zpix8nTc7bdfhfkEeVbC9KhbK/9iYWHiErbu6MWbY/7//8/4//9/pgOnH6jGVazvFDRtq2VgiBIZrUTIBgCk+ivHvuEKwAAAAABJRU5ErkJggg==" />
                              
                                      <img alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAFVJREFUSEvtzDEKAEEIQ9G5/8UUwcITuamsfrFTTGfxCCSQ092/uLuCN4IlMTMFbwRL8uw4IhS8ESzJHo89Hns89nhkpoI3giWpKgVvBEuyx+PuuM8HbRKvOi5p1GMAAAAASUVORK5CYII=" />
                                      <img class="anss_intLink" title="'.$this->getLang("Bold").'" onclick="formatDoc(\'bold\');" src="data:image/gif;base64,R0lGODlhFgAWAID/AMDAwAAAACH5BAEAAAAALAAAAAAWABYAQAInhI+pa+H9mJy0LhdgtrxzDG5WGFVk6aXqyk6Y9kXvKKNuLbb6zgMFADs=" />
                                      <img class="anss_intLink" title="'.$this->getLang("Italic").'" onclick="formatDoc(\'italic\');" src="data:image/gif;base64,R0lGODlhFgAWAKEDAAAAAF9vj5WIbf///yH5BAEAAAMALAAAAAAWABYAAAIjnI+py+0Po5x0gXvruEKHrF2BB1YiCWgbMFIYpsbyTNd2UwAAOw==" />
                                      <img class="anss_intLink" title="'.$this->getLang("Underline").'" onclick="formatDoc(\'underline\');" src="data:image/gif;base64,R0lGODlhFgAWAKECAAAAAF9vj////////yH5BAEAAAIALAAAAAAWABYAAAIrlI+py+0Po5zUgAsEzvEeL4Ea15EiJJ5PSqJmuwKBEKgxVuXWtun+DwxCCgA7" />
                              
                                      <img title="'.$this->getLang("Font_color").'" id="hoveritem1" onMouseOver="ShowPopup(\'hoveritem1\', \'hoverpopup1\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAlxJREFUSEu1kktIVFEchyWwiKSnUQgZgT0WURJlBGELy0WEQRFhi0gJctNCCxIjrMmIgh4DGfYyiBRSkkIaIwNfleAjjagUk0lzNF/Z4MxEZvfrf+fOXO/N06pp8Z3Hd37nf8493Cjgv6CUkUAp/0qHdDqqtT9QymlM9sPQPuiKhu4FaL4K0YqchWmifaCR0vZCqrsqGA0MiRI/6YHB/VJ0EbiXSeHHhhe03o9w7xaU3pXD5QIhbys6OO7hmCudpOux7C3ZRE13pWhjbcJbxa/eXLRhJ9pPOSjkNedZWLEUEuKgvNj0ZlGdfm8PmQ9TWeOMJqU4AVfnA9FT635fwDYP4jgO82YZ3LlqrttDQoPbheN5FiVtTr4FRkQRNeaFgkJIOwKnrsDwqK5Dez67wXECLuaDp9v0tqJhvN+/EpjwydCYN72B5dsgJhGWbIHql/qSZc+4nOwftzlzMDjqZ2RM8anChZuwOAnSs2HGarngNbGKnJVgU9viIS2nmowz9bS8D/0JFlIOgo6rFuKTIfUQfOrTY/aclWCTf6OVmVvvE7ezjNuPOkRNBZ41GM+Qd0n+OHnyzFyj+JMaPWYvZiXYVNa5id9VxuaMSmqag1cxA9nnIFae4XwRNLbBycswZx2cduoxezEr5qDu3Rcau3rw/RiWqeE+vPWTtEdj/gZYuR1W7TD6mPWQfAD6BvSovWAYpQxTXtDM3ESNw3lQ3wSvXsOLFtidBQs3QlWdpBT7dJQyTE56J7PXajytl5nFF5UahY867N6KUkYCpYwEShkJlDISKGUkUMp/h6jfdMOr6dXAlTYAAAAASUVORK5CYII=" />
                                      <div id="hoverpopup1" style="visibility:hidden; position:absolute;" >
                                      <table>
                                      <tr><td>
                                      <img usemap="#fcolor_pallete" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIQAAABLCAYAAACx+i/GAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAKKSURBVHhe7drBitNQGMXxZOgriGVWZXwGBVcJunEvUt/ApR1059rdQOty3sAi7gVRblaCPoNDV1LxFcrEfuUiHtP75UI2wfn/IJBLwpkGDtPm8pXL5Yu2AKJDIRaLi7jETbZavSxO4jlwQCEgKATE0d8QTdPEszxVVcUz9b/mmDFmDc2x3xCFFeJfIYR41s+7167t/07W0Zezvynr6M1p9/dlHF6Osevl/r6cIyfryKMcPbysw7XiY9ZxLMe6wFcGBIWAoBAQFAKCQkBQCAgKAUEhICgEBFvXjlSOGWPW0BzbumYeAn8wD4EOCgFBISAoBASFgOC105HKMWPMGpqTfO204NlsFle+zWbjfsD1eh1Xvvl87uZUPx7Ela85/eznVFdx5Wuas2SOsayT+3n/YK+/XPdmlXfS1//WfrdncJ7v6lZc+ZqzX50cXjvRQSEgKAQEhYCgEBAUAoJCQFAICAoBwda1I5Vjxpg1NIeJKQi2rtFBISAoBASFgKAQELx2OlI5ZoxZQ3PciamyLOPK17at+wFtoiqHTWh5OdWHzImpRz0TU7fruPI1P0Myx1hWHfKyQt2f9ezTJK58lw937vPt6q9x5ZuEe50cXjvRQSEgKAQEhYCgEBAUAoJCQFAICAoBwda1I5Vjxpg1NIeJKQi2rtFBISAoBASFgKAQELx2OlI5ZoxZQ3PciantdhtXvul06n7AySRvEmi38yeBQp03nVSH9HSS5Xx7/TiufHdfvU/mGMt6vlzGle/N+XlvVl2/jStfCE/d53tXr+LK9yQsOjm8dqKDQkBQCAgKAUEhICgEBIWAoBAQFAKCrWtHKseMMWtoDhNTEGxdo4NCQBy+MuI5bryi+A1GxhVi/hSIzAAAAABJRU5ErkJggg==" />
                                      </td></tr></table></div>
                                      
                                      <img title="'.$this->getLang("Background_color").'" id="hoveritem2" onMouseOver="ShowPopup(\'hoveritem2\', \'hoverpopup2\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAihJREFUSEu1lE9Ik3EYx0eXLh2iW5fAQ6cOHio6F0EdokNihARRStCgXTosLPvDIoIaSB6yDq4FJTIrsz9rasNqIgUrXqdr6XSbbXOzOTdL59zYp5/vsvm217+tw+d5n+f7fL/P7fdqgP+CqlgKVMW14veFxUepKYb1MDmZofutm2AwKsaCrjCtB8k1hXd4VrRKXTGslR5HFMn1g5S46w9A78c+Ied3RebV0u9O0/thTLQQi0O3PYrZbJsf5X1RYDX0uzN02WOihfQcOJ0ZzA+c6PW35yXZUxRaiVAQmkxfsb6OEpsC1yA8fOSjrtZC9QmDsOR9RcGV6Hlvx3jDiPFmB033vTSaA1wyvEJ39g6NDZ3CkvcVBZcj8M3A0Eg57U+2ce3qMbQn71FVZeH85RaaLVZhKXgVweXwjb9kOrdBHrOC9uebOH6khurKF7S1eUkkZ8Su4FeEl+JnOskQBxXy574yOh8/w+MS4+LFb/40w3TRwlHesQOu7GFaWwH7GqDChnShgxF2C9tG2T6R2kw41CZ65bHFyCWAAyuneSqCHpuGXGUZ38v1pLa0MrhVon5niHrHXZrdtZgkLV/CFhFTP7iAXD5hopVDDEjbSd/aRfx6DZHDA0ztjfJmP1w8ALpTc5w7k0FXBx7xyhYOLIVcUiRIEmB2ZojcxCjZeJRsMAPip5UWJCIwPpolMgxBv0j8dUQNVbEUqIqlQFUsBapiKVAV/x00vwAP3byEksuehwAAAABJRU5ErkJggg==" />
                                      <div id="hoverpopup2" style="visibility:hidden; position:absolute;" >
                                      <table>
                                      <tr><td>
                                      <img usemap="#bgcolor_pallete" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIQAAABLCAYAAACx+i/GAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAKKSURBVHhe7drBitNQGMXxZOgriGVWZXwGBVcJunEvUt/ApR1059rdQOty3sAi7gVRblaCPoNDV1LxFcrEfuUiHtP75UI2wfn/IJBLwpkGDtPm8pXL5Yu2AKJDIRaLi7jETbZavSxO4jlwQCEgKATE0d8QTdPEszxVVcUz9b/mmDFmDc2x3xCFFeJfIYR41s+7167t/07W0Zezvynr6M1p9/dlHF6Osevl/r6cIyfryKMcPbysw7XiY9ZxLMe6wFcGBIWAoBAQFAKCQkBQCAgKAUEhICgEBFvXjlSOGWPW0BzbumYeAn8wD4EOCgFBISAoBASFgOC105HKMWPMGpqTfO204NlsFle+zWbjfsD1eh1Xvvl87uZUPx7Ela85/eznVFdx5Wuas2SOsayT+3n/YK+/XPdmlXfS1//WfrdncJ7v6lZc+ZqzX50cXjvRQSEgKAQEhYCgEBAUAoJCQFAICAoBwda1I5Vjxpg1NIeJKQi2rtFBISAoBASFgKAQELx2OlI5ZoxZQ3PciamyLOPK17at+wFtoiqHTWh5OdWHzImpRz0TU7fruPI1P0Myx1hWHfKyQt2f9ezTJK58lw937vPt6q9x5ZuEe50cXjvRQSEgKAQEhYCgEBAUAoJCQFAICAoBwda1I5Vjxpg1NIeJKQi2rtFBISAoBASFgKAQELx2OlI5ZoxZQ3PciantdhtXvul06n7AySRvEmi38yeBQp03nVSH9HSS5Xx7/TiufHdfvU/mGMt6vlzGle/N+XlvVl2/jStfCE/d53tXr+LK9yQsOjm8dqKDQkBQCAgKAUEhICgEBIWAoBAQFAKCrWtHKseMMWtoDhNTEGxdo4NCQBy+MuI5bryi+A1GxhVi/hSIzAAAAABJRU5ErkJggg==" />
                                      </td></tr></table></div>
                              
                                      <img class="anss_intLink" title="'.$this->getLang("Strikethrough").'" onclick="formatDoc(\'strikethrough\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAQCAYAAAAMJL+VAAAABGdBTUEAAK/INwWK6QAAAAlwSFlzAAAOwwAADsMBx2+oZAAAABp0RVh0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjUuMTAw9HKhAAAA30lEQVQ4T2P4//8/Ay0xTQ0HOXxwWBAbGysYGBg409HRMU1PT69cXl5+Jicn525igpYoH3h5eb2zs7MrhxnIxsZWzgD2POH4I2iBu7t7mqGh4X8NDY0OZAOBFlDHByYmJi7AIPnPz88PjDGGmUBsDI48BgYXqvgAZAgovEFBgoTPUGRBWlpaKRD/R8fS0tLIlqCzS7D5iGAcAF0qiBb2aUCxu1DfnCEUTMRYADIQnoJgBkItuEstC0DBge6Td9gsRreQWB+ADANFLCj9g30ExCjJFpdPCFpAKAgIyQ99CwDEVf+A3LSrWQAAAABJRU5ErkJggg==">
                                      <img class="anss_intLink" title="'.$this->getLang("Superscript").'" onclick="formatDoc(\'superscript\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAQCAYAAAAMJL+VAAAABGdBTUEAAK/INwWK6QAAAAlwSFlzAAAOwwAADsMBx2+oZAAAABp0RVh0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjUuMTAw9HKhAAAA9UlEQVQ4T7WTLQ7CQBCFV6ARtfUIBA5LQCA4QxEILBK7Z8BwgZ4FgW44AYoj1G3fbGbIiG67y9ImL/1J+72ZeVPjnDNTKgu+Nc8CekEOuvYVmmtwBPgGlWSSZGCMqSEHtdCcPsaxVs8WAgR8DzWpBiXDiXxmgwcb+HsSwEuohXZJBgw8MfCDc8XXtYLTaIJwzxjaIABnkFRNnbyhQhlYDphCTstAIABeuHIyuKeu9FgHkkOjTHpnHTIeM5BNOsDAsgmNyW9VjIIvAbJhIFVPWeitsjHwYMgMlLFUKg/pgvJYxZhEtRkD+imDHPC3639ABv+lqQ06XCTTcqILhPIAAAAASUVORK5CYII=">
                                      <img class="anss_intLink" title="'.$this->getLang("Subscript").'" onclick="formatDoc(\'subscript\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAQCAYAAAAMJL+VAAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwwAADsMBx2+oZAAAABp0RVh0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjUuMTAw9HKhAAAA9UlEQVQ4T8WTIQ7CQBBFR6AR2HoEAoclQSA4QxEILBLbcAQMF+hZOEHDCVAcoW75s/kLK9rubEKD+Gm7bf6b+TMV55yMqVHNtfD/AUSkhlCCtNDUVyOyis7mlmh7O4BRQXN1PhJwJ8A/WzT4EcwONHzhWvK+thiHb1KACUxD1drJE5r9DMBYTqxcAbcc8+QWRXNoIsgmB5KKKGzSDoCKEI3Jb9UFcUEPyEHnLvDQFq1pqNXrLOKtqgjYw/gKFQoxA2gYYik/G/HtQuexDOcw30KNGZCTMYwXUAt1zsb0s/QBGU2veXKLUp0AUHHAOmT7DFLGOe/fRwDI0vXm3AMAAAAASUVORK5CYII=">
                               
                                      <img alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAFVJREFUSEvtzDEKAEEIQ9G5/8UUwcITuamsfrFTTGfxCCSQ092/uLuCN4IlMTMFbwRL8uw4IhS8ESzJHo89Hns89nhkpoI3giWpKgVvBEuyx+PuuM8HbRKvOi5p1GMAAAAASUVORK5CYII=" />
                                      
                                      <img class="anss_intLink" title="'.$this->getLang("Left_align").'" onclick="formatDoc(\'justifyleft\');" src="data:image/gif;base64,R0lGODlhFgAWAID/AMDAwAAAACH5BAEAAAAALAAAAAAWABYAQAIghI+py+0Po5y02ouz3jL4D4JMGELkGYxo+qzl4nKyXAAAOw==" />
                                      <img class="anss_intLink" title="'.$this->getLang("Center_align").'" onclick="formatDoc(\'justifycenter\');" src="data:image/gif;base64,R0lGODlhFgAWAID/AMDAwAAAACH5BAEAAAAALAAAAAAWABYAQAIfhI+py+0Po5y02ouz3jL4D4JOGI7kaZ5Bqn4sycVbAQA7" />
                                      <img class="anss_intLink" title="'.$this->getLang("Right_align").'" onclick="formatDoc(\'justifyright\');" src="data:image/gif;base64,R0lGODlhFgAWAID/AMDAwAAAACH5BAEAAAAALAAAAAAWABYAQAIghI+py+0Po5y02ouz3jL4D4JQGDLkGYxouqzl43JyVgAAOw==" />
                                      <img class="anss_intLink" title="'.$this->getLang("Full_align").'" onclick="formatDoc(\'justifyfull\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAQCAYAAAAMJL+VAAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAFlJREFUOE+1ziEOADEQQtHe/9LTxU0Aw4aKZ74gnJl5ysYmG5t8/PIfvAMS2nxcrxK8AxLafFyvErwDEtp8XK8SvAMS2nxcrxK8AxLafFyvErwDEtps7JlzAcY81FZTtdiRAAAAAElFTkSuQmCC">
                                      
                                      <br />
                                      
                                      <img class="anss_intLink" title="'.$this->getLang("Add_indentation").'" onclick="formatDoc(\'indent\');" src="data:image/gif;base64,R0lGODlhFgAWAOMIAAAAADljwl9vj1iE35GjuaezxtDV3NHa7P///////////////////////////////yH5BAEAAAgALAAAAAAWABYAAAQ7EMlJq704650B/x8gemMpgugwHJNZXodKsO5oqUOgo5KhBwWESyMQsCRDHu9VOyk5TM9zSpFSr9gsJwIAOw==" />
                                      <img class="anss_intLink" title="'.$this->getLang("Delete_indentation").'" onclick="formatDoc(\'outdent\');" src="data:image/gif;base64,R0lGODlhFgAWAMIHAAAAADljwliE35GjuaezxtDV3NHa7P///yH5BAEAAAcALAAAAAAWABYAAAM2eLrc/jDKCQG9F2i7u8agQgyK1z2EIBil+TWqEMxhMczsYVJ3e4ahk+sFnAgtxSQDqWw6n5cEADs=" />
                                      <img class="anss_intLink" title="'.$this->getLang("Numbered_list").'" onclick="formatDoc(\'insertorderedlist\');" src="data:image/gif;base64,R0lGODlhFgAWAMIGAAAAADljwliE35GjuaezxtHa7P///////yH5BAEAAAcALAAAAAAWABYAAAM2eLrc/jDKSespwjoRFvggCBUBoTFBeq6QIAysQnRHaEOzyaZ07Lu9lUBnC0UGQU1K52s6n5oEADs=" />
                                      <img class="anss_intLink" title="'.$this->getLang("Dotted_list").'" onclick="formatDoc(\'insertunorderedlist\');" src="data:image/gif;base64,R0lGODlhFgAWAMIGAAAAAB1ChF9vj1iE33mOrqezxv///////yH5BAEAAAcALAAAAAAWABYAAAMyeLrc/jDKSesppNhGRlBAKIZRERBbqm6YtnbfMY7lud64UwiuKnigGQliQuWOyKQykgAAOw==" />
                                      
                                      <img class="anss_intLink" title="'.$this->getLang("H-Ruler").'" onclick="formatDoc(\'inserthorizontalrule\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAQCAYAAAAMJL+VAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAadEVYdFNvZnR3YXJlAFBhaW50Lk5FVCB2My41LjEwMPRyoQAAAPNJREFUOE9j+P//PwMtMU0NBzkcpwXl5eX/YbikpOR/Xl7e/7S0tP8xMTH/g4KC/ru7u/+3sbH5b2Bg8F9VVRVoFvaQGDgfEONqaWnp/4KCgv85OTkHoQ9whTXU1X+Arj5KTOobmDho2/By76LD7/5jw/MPvP0/e9/r/9N2v/o/cdur/z2bn/9vXf/8f+Sku3ux+QirD0AG77j4CY63nf/4f/PZD//Xn37/f/Xx9/+XH337f9Ght//n7X/9f9be1/+n73r1v3jxY6wRjdMCXC5tWP30f/XKp//Llz4GG4qMifYBMZFHrJqBiWRiXUeMuqHvAwA6MV3j7jeEEwAAAABJRU5ErkJggg==">
                                      <img class="anss_intLink" title="'.$this->getLang("Quote").'" onclick="makeCite();" src="data:image/gif;base64,R0lGODlhFgAWAIQXAC1NqjFRjkBgmT9nqUJnsk9xrFJ7u2R9qmKBt1iGzHmOrm6Sz4OXw3Odz4Cl2ZSnw6KxyqO306K63bG70bTB0rDI3bvI4P///////////////////////////////////yH5BAEKAB8ALAAAAAAWABYAAAVP4CeOZGmeaKqubEs2CekkErvEI1zZuOgYFlakECEZFi0GgTGKEBATFmJAVXweVOoKEQgABB9IQDCmrLpjETrQQlhHjINrTq/b7/i8fp8PAQA7" />
                                      <img class="anss_intLink" title="'.$this->getLang("Code").'" onclick="makeCode();" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAARBJREFUSEu1kqGRhTAQhpFIJGVQAoICKAFBAUgcJSAogEIQCCQCgUAgkEgEBezt5s3eS16WmTdze+IL8P3JboAEAPAviFIDUWogSg1EqYEoNRAls20bXny/ritefG8jymVZIM9zTAM4jgOVm5dlCXEcQ9u2XsY4D3bBMAyBCtg5M00TJEli5j01MAPtigtGUQR1XcN5nhi5kz8ZhgGyLPtt0HUd6ldmBhIU0i6+KfhJ3/dmPcHFTbDvO6RpagLacdM0XzUYx9F5U1rHmTeRG9A3rqoK9Ttn5nn2NnJdF0bvOc4Cxm4gnYqiKB4LMp6wob9/3zfeup5Oz1NBRpQaiFIDUWogSg1EqYEoNRDl34HgB96MckGYHkIZAAAAAElFTkSuQmCC" />
                                      <img class="anss_intLink" title="'.$this->getLang("Hyperlink").'" onclick="var sLnk=prompt(\'Write the URL here\',\'http:\/\/\');if(sLnk&&sLnk!=\'\'&&sLnk!=\'http://\'){formatDoc(\'createlink\',sLnk)}" src="data:image/gif;base64,R0lGODlhFgAWAOMKAB1ChDRLY19vj3mOrpGjuaezxrCztb/I19Ha7Pv8/f///////////////////////yH5BAEKAA8ALAAAAAAWABYAAARY8MlJq7046827/2BYIQVhHg9pEgVGIklyDEUBy/RlE4FQF4dCj2AQXAiJQDCWQCAEBwIioEMQBgSAFhDAGghGi9XgHAhMNoSZgJkJei33UESv2+/4vD4TAQA7" />
                                      <img class="anss_intLink" title="'.$this->getLang("Unlink").'" onclick="formatDoc(\'unlink\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAAOwQAADsEBuJFr7QAAABh0RVh0U29mdHdhcmUAcGFpbnQubmV0IDQuMC41ZYUyZQAAAk5JREFUSEu1k19IU1EcxwUfEyF80JJoFGShLLWpID5Ioi+xh+wlX+ohYg2EwEDRCGdF0sOcKRTYQ1rRfHDD0qnVw15sxITUp0p8Cv/QSCdTCqrx6Z5zd7ddPQuk9fC5597v+Z3v73fO+d0c4L+gFLOBUswGSjEbKMWM7Oxog0JXkHxZXPqGdyLEg2evkwTff9Sm9Lkx7wyB1pu8vHGbh4/HZayxVhKNwvw8xGJSN5mK8dfvuESYdvb5pS5YGntDvLmZeEMDHx49pdPtw/vqnTQhGISuLmhpgamplLFhKqq0nbvC5euepKFAJPG9mOHJcStfCg4Rtl8k4B3F4ewFjwfq66GsDBobYXo6ZSwMRZXCVJgIM6NygUi6+CnC/fPXiJ4+w/qxE6zc6qXvZDVYrVBVBR0dyMq3t1PG6RWL7YvxyNm7JkRS173nhC5cIlZczFZBISsHC6GiAtxu2NiQhgbyYZiOTwT06jREAnEkQhfI5FrVn31vWT1s4WduLj8O5EF7O2xtmUwFyRfjsoRZ+jmnz7VevcPo0VN8zS/ge14+FBXpl5bohHRMH39lcxN6eqC0FJqaoK0NKivBZoOBAXb3uHlxJsRWu7v1my8vh5ERffv9/frF1dXB8LAWmlpjNsiE369XVlsLg4OalNCNhKIzamqQcYk5s4GK1VVwOKCkBFyuvee5tgZOJ1gsYLdDOCznzSYq5uZA++PkuYZ2/cYGCwt6TLXW10ND8rz3BqmYnU1WkpHlZZichEhE+9xPV+wTpZgNlGI2UIr/Djl/AJ5goKk+yVctAAAAAElFTkSuQmCC" />
                                      
                                      <select style="margin: 0 12pt 0 12pt;" size="1">
                                          '.$fontOptions.'
                                      </select>        
                                      <img class="anss_intLink" title="'.$this->getLang("Smaller").'" onclick="addTags(\'small\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAYdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuNWWFMmUAAAD0SURBVDhPpdChbgJBEMbxeYAKQggC1WeorgCFQWGLpK6iD1DXJ8CAQCBxICsQCPoCeBIkSRG0qqLi+p9lLzc3LAmESX7J7sx9u3cnWZbdJNm0Yg2xgzbUD5Zh7gNerDY+kR+wwVOY+4AXq4oBvvCNGeph7gOeqS6WWONZG2HuA56pBt6g/+NeG2GeCDxigte4t/WA1nF5/oAp/vCLO22cq5MDKA1ocKRz9Ow8pbwhgPzmLeZ2nlLeiCzyEPUO/ZSafcYrFjwYAwfo7XvooG8DXrEQeYmBDpqRHrKwAa9YiHxgVRqKjKFvVbF9K9m8RrJ5uUz+AVQNGdVN6ktGAAAAAElFTkSuQmCC" />
                                      <img class="anss_intLink" title="'.$this->getLang("Bigger").'"  onclick="addTags(\'big\');" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAYdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuNWWFMmUAAAEJSURBVDhPldI7TsNAEIBhlxQ0SIgCpcgRnCOkojUtcpkDUFC6ywGiFDmBSxTlAEi4CH2UOki0CBpQCgqKzT/2LjseVoKs9FmzOw87jjPn3J/MGmHchaxUg2bWJSosMJSDZJNm1jUescVEDlINJ5DpAzPgDHO84QNLXKQG1JBAlGrIFZ4Qcs+4sc1y9z1C0YMaIL/7FSH3icYOKHxyhi9841zXWP1Nlt1DghwrH98iuXoDWKeQu+78voQE61CTEoPYMPX7MFA2w1BnxSA+8jtePHkHclbpJq27xLuJRtlACja2Megu8fHrXpJ/AOEpcp37qWkv8e0Xvwr4FnzuzubafOrwGMnD/3PZAXT02e5rxY58AAAAAElFTkSuQmCC" />
                                    </div>
                                  </div>
                                  <div  id="anss_textBox_'.trim($fields[0]).'" 
                                        class="anss_textBox" 
                                        contenteditable="true" 
                                        name="anss_textBox_'.trim($fields[0]).'"
                                        title="'.trim($this->getLang(trim($fields[5]))).'" '.trim($fields[2]).'"
                                        onkeyup="count_chars(this,'.$this->getConf("prev_length").')" 
                                        onMouseOver="HidePopup(\'hoverpopup1\', \'hoverpopup2\', \'hoverpopup3\')" ></div>
                              
                                        <map name="fcolor_pallete" id="fcolor_pallete">
                                          <area shape="rect" coords=" 5, 5,16,16"   href="#FFFFFF" onclick="getfColor(this);" />   
                                          <area shape="rect" coords=" 5,23,16,34"   href="#E0E0E0" onclick="getfColor(this);" />   
                                          <area shape="rect" coords=" 5,41,16,52"   href="#C1C1C1" onclick="getfColor(this);" />   
                                          <area shape="rect" coords=" 5,59,16,70"   href="#A8A8A8" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="23, 5,34,16"   href="#000000" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="23,23,34,34"   href="#696969" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="23,41,34,52"   href="#A0A0A0" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="23,59,34,70"   href="#C4C4C4" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="41, 5,52,16"   href="#FF0000" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="41,23,52,34"   href="#FFA600" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="41,41,52,52"   href="#FF7800" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="41,59,52,70"   href="#800000" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="59, 5,70,16"   href="#FFFF00" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="59,23,70,34"   href="#FFFF9E" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="59,41,70,52"   href="#FFD700" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="59,59,70,70"   href="#8B4513" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="78, 5,89,16"   href="#00FF00" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="78,23,89,34"   href="#C2F9C2" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="78,41,89,52"   href="#008000" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="78,59,89,70"   href="#2F4F4F" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="97, 5,108,16"   href="#0000FF" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="97,23,108,34"   href="#C1E3FF" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="97,41,108,52"   href="#277DC4" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="97,59,108,70"   href="#000068" onclick="getfColor(this);" />   
                                      
                                          <area shape="rect" coords="115, 5,126,16"   href="#FF00BB" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="115,23,126,34"   href="#FF9ED5" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="115,41,126,52"   href="#BC008A" onclick="getfColor(this);" />   
                                          <area shape="rect" coords="115,59,126,70"   href="#6B0050" onclick="getfColor(this);" />   
                                        </map>
                              
                                        <map name="bgcolor_pallete" id="bgcolor_pallete">
                                          <area shape="rect" coords=" 5, 5,16,16"   href="#FFFFFF" onclick="getbColor(this);" />   
                                          <area shape="rect" coords=" 5,23,16,34"   href="#E0E0E0" onclick="getbColor(this);" />   
                                          <area shape="rect" coords=" 5,41,16,52"   href="#C1C1C1" onclick="getbColor(this);" />   
                                          <area shape="rect" coords=" 5,59,16,70"   href="#A8A8A8" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="23, 5,34,16"   href="#000000" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="23,23,34,34"   href="#696969" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="23,41,34,52"   href="#A0A0A0" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="23,59,34,70"   href="#C4C4C4" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="41, 5,52,16"   href="#FF0000" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="41,23,52,34"   href="#FFA600" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="41,41,52,52"   href="#FF7800" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="41,59,52,70"   href="#800000" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="59, 5,70,16"   href="#FFFF00" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="59,23,70,34"   href="#FFFF9E" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="59,41,70,52"   href="#FFD700" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="59,59,70,70"   href="#8B4513" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="78, 5,89,16"   href="#00FF00" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="78,23,89,34"   href="#C2F9C2" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="78,41,89,52"   href="#008000" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="78,59,89,70"   href="#2F4F4F" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="97, 5,108,16"   href="#0000FF" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="97,23,108,34"   href="#C1E3FF" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="97,41,108,52"   href="#277DC4" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="97,59,108,70"   href="#000068" onclick="getbColor(this);" />   
                                      
                                          <area shape="rect" coords="115, 5,126,16"   href="#FF00BB" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="115,23,126,34"   href="#FF9ED5" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="115,41,126,52"   href="#BC008A" onclick="getbColor(this);" />   
                                          <area shape="rect" coords="115,59,126,70"   href="#6B0050" onclick="getbColor(this);" />   
                                        </map>
                                                 
                              <p id="editMode"><input type="hidden" name="switchMode" id="switchBox" onchange="setDocMode(this.checked);" /> </p>
                          
                          <br /> '.NL; }



                }
                else if (trim($fields[0]) == "anchor") {

                        $default_anker = date("YmdHis");
                        if($this->getConf('soapp')>0) $link_anker = $this->getConf('act_delim').'anchor='.$default_anker; // to show only one article only on a page
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
                               id ="submit" 
                               value="'.$this->getLang('anss_input_btn_save').'" 
                               title="'.$this->getLang('anss_input_btn_save_descr').'" />'.NL;

            if($this->getConf('wysiwyg')==true) {
                  $output .= '<script type="text/javascript">
                              var oDoc, sDefTxt;
                              
                              function validateMode() {
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                if (document.'.$prefix.'.switchMode.checked !== true) { return true ; }
                                alert("Uncheck \"Show HTML\".");
                                oDoc.focus();
                                return false;
                              }
                              
                              function formatDoc(sCmd, sValue) {
                                
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                sDefTxt = oDoc.innerHTML;
                                if (validateMode()) { document.execCommand(sCmd, false, sValue); oDoc.focus(); }
                              }
                              
                              function makeCite()
                              {
                                  var html = "";
                                  var sel, range;
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                sDefTxt = oDoc.innerHTML;
                                  if (typeof window.getSelection != "undefined") {
                                      var sel = window.getSelection();
                                      if (sel.rangeCount) {
                                          var container = document.createElement("blockquote");
                                          container.setAttribute("class", "ans_cite");
                                          for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                                              container.appendChild(sel.getRangeAt(i).cloneContents());
                                          }
                                          html = container.innerHTML;
                              
                                      }
                                  } else if (typeof document.selection != "undefined") {
                                      if (document.selection.type == "Text") {
                                          html = document.selection.createRange().htmlText;
                                      }
                                  }                            
                                  range = sel.getRangeAt(0);
                                  range.deleteContents();
                                  var post_p = document.createElement("p")
                                  range.insertNode(post_p);
                                  var post_br = document.createElement("br")
                                  range.insertNode(post_br);
                                  range.insertNode(container);
                              
                              }
                              
                              function addTags(sCmd)
                              { var html = "";
                                var sel, range;
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                sDefTxt = oDoc.innerHTML;
                                  if (typeof window.getSelection != "undefined") {
                                      var sel = window.getSelection();
                                      if (sel.rangeCount) {
                                          var container = document.createElement(sCmd);
                                          for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                                              container.appendChild(sel.getRangeAt(i).cloneContents());
                                          }
                                          html = container.innerHTML;                        
                                      }
                                  } else if (typeof document.selection != "undefined") {
                                      if (document.selection.type == "Text") {
                                          html = document.selection.createRange().htmlText;
                                      }
                                  }                            
                                  range = sel.getRangeAt(0);
                                  range.deleteContents();
                                  var post_p = document.createElement("p")
                                  range.insertNode(post_p);
                                  var post_br = document.createElement("br")
                                  range.insertNode(post_br);
                                  var p_container = document.createElement("p");
                                  p_container.appendChild(container);
                                  range.insertNode(p_container);
                              }

                              function makeCode()
                              { var html = "";
                                var sel, range;
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                sDefTxt = oDoc.innerHTML;
                                  if (typeof window.getSelection != "undefined") {
                                      var sel = window.getSelection();
                                      if (sel.rangeCount) {
                                          var container = document.createElement("code");
                                          for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                                              container.appendChild(sel.getRangeAt(i).cloneContents());
                                          }
                                          html = "<p>"+container.innerHTML+"</p>";                        
                                      }
                                  } else if (typeof document.selection != "undefined") {
                                      if (document.selection.type == "Text") {
                                          html = document.selection.createRange().htmlText;
                                      }
                                  }                            
                                  range = sel.getRangeAt(0);
                                  range.deleteContents();
                                  var post_p = document.createElement("p")
                                  range.insertNode(post_p);
                                  var post_br = document.createElement("br")
                                  range.insertNode(post_br);
                                  var p_container = document.createElement("p");
                                  p_container.appendChild(container);
                                  range.insertNode(p_container);
                              }
                              
                              function setDocMode(bToSource) {
                                var oContent;
                                oDoc = document.getElementById("'.$ansTBox_ID.'");
                                sDefTxt = oDoc.innerHTML;
                                if (bToSource) {
                                  oContent = document.createTextNode(oDoc.innerHTML);
                                  oDoc.innerHTML = "";
                                  var oPre = document.createElement("pre");
                                  oDoc.contentEditable = false;
                                  oPre.id = "sourceText";
                                  oPre.contentEditable = true;
                                  oPre.appendChild(oContent);
                                  oDoc.appendChild(oPre);
                                } else {
                                  if (document.all) {
                                    oDoc.innerHTML = oDoc.innerText;
                                  } else {
                                    oContent = document.createRange();
                                    oContent.selectNodeContents(oDoc.firstChild);
                                    oDoc.innerHTML = oContent.toString();
                                  }
                                  oDoc.contentEditable = true;
                                }
                                oDoc.focus();
                              }
                              
                              function getfColor(block)
                              { 
                              	hp = document.getElementById("hoverpopup1");
                              	hp.style.visibility = "Hidden";
                                var s_url=block.href;
                                var pColor=s_url.substr(s_url.indexOf("#"));
                                document.execCommand(\'forecolor\',         false, pColor);
                              }
                              
                              function getbColor(block)
                              { 
                              	hp = document.getElementById("hoverpopup2");
                              	hp.style.visibility = "Hidden";
                                var s_url=block.href;
                                var pColor=s_url.substr(s_url.indexOf("#"));
                                document.execCommand(\'backcolor\',         false, pColor);
                              
                              }
                              
                              function ShowPopup(hoveritem, hoverpopup)
                              {
                                HidePopup("hoverpopup1", "hoverpopup2");
                                hp = document.getElementById(hoverpopup);
                              	// Set position of hover popup
                              	hp.style.top = (document.getElementById(hoveritem).offsetTop+15) + \'px\';
                              	hp.style.left = (document.getElementById(hoveritem).offsetLeft ) + \'px\';    
                              	// Set popup to visible
                              	hp.style.visibility = "Visible";
                              }
                              
                              function HidePopup(hoverpopup1, hoverpopup2)
                              {
                                document.getElementById("hoverpopup1").style.visibility = "Hidden";
                                document.getElementById("hoverpopup2").style.visibility = "Hidden";
                              }
                              function count_chars(obj, max) {
                                    var data = obj.innerHTML;
                                    var extract = data.split(" ");
                                    var bextract = data.split("\n");
                                    var cextract = extract.length + bextract.length -1;
                                    if(cextract>max) output = \'<span style="color:red;">\' + cextract + \'</span>\';
                                    else output = cextract;
                                    document.getElementById("nws_charcount").innerHTML =  "'.$this->getLang('wordcount').'"
                                }
                                
                                function resizeBoxId(obj,size) {
                                    var arows = document.getElementById(obj).rows;
                                    document.getElementById(obj).rows = arows + size;
                                }
                                
                                function myFunction() {
                                  document.getElementById("news_input_text").value = document.getElementById("anss_textBox_text").innerHTML;
                                }
                                
                             </script>'.NL;
            }           
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
          $oldrecord = rawWiki($targetpage);
//          $entries = explode("\n----\n\n",$oldrecord);
          $entries = explode("======",$oldrecord);
          foreach($entries as $entry) {
             // split news block into line items
             $temp_array = explode("\n  * ",$entry);
             unset($temp_array[0]);
             $wysiwyg = false;
             
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
                    elseif($key=='wysiwyg'){
                      $wysiwyg = 1;
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
                                                
                        if ($wysiwyg==false) $prvw_string = p_render('xhtml',p_get_instructions($prvw_string),$info);
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
                  $output .= $tab_left.'<a href="'.DOKU_URL.'doku.php?id='.$this->getConf('news_output').$this->getConf('act_delim').'tag='.trim($tag).'" class="' . $class .'"title="'.$val.'">'.$tag.'</a>'.$tab_right.NL;
              }
          }
          $output .= '</div>'.NL;  
          $renderer->doc .= $output;
        }
        /* --- Show all news -------------------------------------------------*/
        elseif ((strpos($ans_conf['param'], 'allnews')!== false)) {
          // check if page ID was called with tag filter
          $tmp         = ','.$_GET['tag'];    // this will overrule the page syntax setting
          $info        = array();
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
             $wysiwyg = false;
             
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
                        elseif($key=='wysiwyg'){
                          $wysiwyg = 1;
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
                                                    
                            if ($wysiwyg==false) $prvw_string = p_render('xhtml',p_get_instructions($prvw_string),$info);
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
                      $output = '<div style="font-size:.85em;">'.$backlink.NL.
                                '<span class="anss_sep">&nbsp;|&nbsp;</span><a class"wikilink" href="'.wl($ID).$this->getConf('act_delim').'archive=archive">'.$archive_lnkTitle.'</a></div><br />'.NL.
                                '<div class="archive_section" id="news_archive_head"  style="'.$archive_options['style'].'">'.
                                  $output.
                                '<div style="font-size:.85em;">'.$backlink.NL. 
                                '<span class="anss_sep">&nbsp;|&nbsp;</span><a class"wikilink" href="'.wl($ID).$this->getConf('act_delim').'archive=archive">'.$archive_lnkTitle.'</a></div><br />'.NL;          
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
          // date    ... consider all news of a defined month of a year (mm.yyyy, empty per default)
          // qty     ... limits the number of news headlines starting with most recent (either integer or all, default:all)
          // tag     ... consider all news where news article owns the given tag string (empty per default) tag delimiter is "|"
          // style   ... css style string as used in HTML (except quotation marks) for the outer element div
          // class   ... css style for usecase toc, page or box
          // ho      ... headlinesonly will list the news headlines without timestamp and author (on/off, default: off)
          // p_signs ... number of previewed signs of the article
          // cws     ... define if less simple styling to be kept (or none if the parameter is missing)

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
                        elseif($key=='wysiwyg'){                      
                            $news_wysiwyg = $value;
                        }
                        elseif($key=='text'){                      
                            $news_content = $value;
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
                    
                    if (($archive_options['p_signs'] !== false) && ((int)$archive_options['p_signs'] >2)) {
                        if ((int)$news_wysiwyg==false) $news_content = p_render('xhtml',p_get_instructions($news_content),$info);
                        // cws   ...   content with style syntax parameter
                        // strip all HTML-tags except a few selected
                        if ($archive_options['cws'] == false) $news_content = strip_tags($news_content, '<br>');
                        elseif ((int)$archive_options['cws'] <2 && ($archive_options['cws'] !== false)) $news_content = strip_tags($news_content, '<br><font><strong><a><u><ul><b><i>');

                        $news_content = "<br>".'<span class="news_preview">' . trim(substr($news_content,0,(int)$archive_options['p_signs'])).' ...</span>'.NL;
                    }
                    else $news_content="";
                    
                    if(($archive_options['tag']!==false) && ($archive_options['tag']!=='off') && ($archive_options['class']=='page')) $output .= '<div class="archive_item">'.trim($news_date).$news_head.$news_subtitle.$news_content.'</div>'.NL;
                    else $output .= '<ul><li class="level3"><div class="li">'.trim($news_date).$news_head.$news_content.'</div></li></ul>'.NL;
                    
                    $close_ytag    = "";
                    $close_mtag    = "";
                    $anchor        = "";
                    $news_date     = "";
                    $news_head     = "";
                    $news_subtitle = "";
                    $news_wysiwyg  = false;
                    $news_content  = "";
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
                                    <a href="'.$news_root.':allnewsdata'.$this->getConf('act_delim').'do=shownewsarchive">&raquo; News Archive</a>
                                 </div>
                             </div>
                          </div>'.NL.NL;
          }
          elseif($archive_options['class']=='box') {
              
              $output  = '<div class="archive_box" id="archive__box" style="'.$archive_options['style'].'">
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

          function count_chars(obj, max) {
                var data = obj.innerHTML;
                var extract = data.split(" ");
                var bextract = data.split("\n");
                var cextract = extract.length + bextract.length -1;
                if(cextract>max) output = \'<span style="color:red;">\' + cextract + \'</span>\';
                else output = cextract;
                document.getElementById("nws_charcount").innerHTML =  "'.$this->getLang('wordcount').'"
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