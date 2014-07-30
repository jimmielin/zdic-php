<?php
/**
 * ZDic.net API Class
 * Retrieves Chinese Character Information from ZDIC.NET (汉典).
 * 从汉典 (ZDIC.NET) 获取关于汉字和词语的基本解释信息与注音.
 *******************************************
 * @package        psn.jimmielin.yw.zdic
 * @copyright      (c) 2014 Lin "Jimmie" Haipeng
 * @license        MIT License
 *******************************************
 * Notes:
 * - ZDIC.NET WAP "简单模式" has a way to prevent programmatic access by shifting data around and adding class='zdct*' to it.
 * This system auto-processes these situations, removing any .zdct[0-9] that are display: none, and if empty arrays are retrieved, it auto-requests data.
 *
 * 声明: 此API Class并未受到汉典授权, 因此其中包含大量进行筛选的regex, 删除汉典对于信息的 "广告式" 标记和保护.
 * 请在试用这个class获取的信息时给予汉典相应的信息来源标注, 以尊重原来源的版权.
 */

class zdic {
    
    /**
     * Word-related functions.
     */
    public function getWord($request) {
        $raw = $this->_zdic_dl($request, 2);
        
        // Match Pinyin
        $pinyin = array();
        preg_match_all("#<span class=\"dicpy\">([^</]*)</span>#si", $raw, $pinyin);

        // Match Meanings (alternate algo)
        $meanings = array();
        preg_match_all("#<p( class=\"zdct([0-9])\")?>([^</]*)</p>#usi", $raw, $meanings);
        
        //echo "<xmp>" . $raw . "</xmp>";
        
        return array(
            "metadata" => $this->internalState["metadata"],
            "pinyin" => $pinyin[1][0],
            "meanings" => $meanings[3]
        );
    }
    
    /**
     * Character-related functions.
     */
    public function getCharacter($request) {
        $raw = $this->_zdic_dl($request, 1);
        // echo "<xmp>" . $raw . "</xmp>";
        
        // Match Pinyin
        $pinyin = array();
        preg_match_all("#http://www\.zdic\.net/z/pyjs/\?py=([a-zA-Z]*\d{1})#si", $raw, $pinyin);

        // Match Meanings (alternate algo)
        $meanings = array();
        preg_match_all("#<p( class=\"zdct([0-9])\")?>([^</]*)</p>#usi", $raw, $meanings);
        
        // Resolve Pinyin
        
        return array(
            "metadata" => $this->internalState["metadata"],
            "pinyin" => $pinyin[1],
            "meanings" => $meanings[3]
        );
    }
    
    /**
     * Retrieve a given page from ZDIC.NET with POST (word) or GET (char), and returns data somehow stripped for basic processing.
     * $request 	string
     * $type		1 for char, 2 for word (experimental)
     */
    private function _zdic_dl($request, $type = 1) {
        if($type == 1) {
            // Build the cURL URL
            $curlURL = "http://www.zdic.net/z/jd/?u=" . $this->_ordutf8($request);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $curlURL,
                CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36",
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_FOLLOWLOCATION => true
            ));
        }
        elseif($type == 2) {
            // Build the cURL URL
            $curlURL = "http://www.zdic.net/search/?c=2";
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $curlURL,
                CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36",
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(
                    array(
                        "c" => 2,
                        "q" => $request
                    )
                ),
            ));
        }
        
        $resp = curl_exec($curl);
        curl_close($curl);

        // Clean out Chinese Punctuation to save space
        $resp = str_replace("（", "(", $resp);
        $resp = str_replace("）", ")", $resp);
        $resp = str_replace("。", ". ", $resp);
        $resp = str_replace("，", ", ", $resp);
        $resp = str_replace("；", ";", $resp);
        $resp = str_replace("：", ":", $resp);
        $resp = str_replace("◎", "* ", $resp);
        
        // Anti-ZDIC-Protection: Find if some DIV is being hidden, strip it from the raw output
        $divID = array();
        if(preg_match("#.zdct([0-9])\s{\r\n\sdisplay: none;\r\n}#si", $resp, $divID)) {
            $resp = preg_replace("#<([a-z]*) class=\"zdct{$divID[1]}\">([^<]*)</([a-z]*)>#siu", "", $resp);
            // echo "<strong>Anti-ZDIC Protection Applied:</strong> .zdic{$divID[1]} was found and removed<br />";
        }
        
        // Preg-Assist: There is a <span> there that sometimes likes messing with stuff, strip the tag only
        $resp = preg_replace('#<span class="diczx3">([^</]*)</span>#siu', '$1', $resp);
        
        $this->internalState = array(
            "metadata" => array(
                "request" => $request,
                "type" => $type
            ),
            "raw" => $resp
        );
        
        return $resp;
    }

    /**
     * UTF-8 Character to Ordinal - used to request zdic codes
     * Credit to arglanir+phpnet[at]gmail.com
     */
    private function _ordutf8($string) {
        $offset = 0;
        $code = ord(substr($string, $offset, 1)); 
        if ($code >= 128) {
            if ($code < 224) $bytesnumber = 2;
            else if ($code < 240) $bytesnumber = 3;
            else if ($code < 248) $bytesnumber = 4;
            $codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
            for ($i = 2; $i <= $bytesnumber; $i++) {
                $offset ++;
                $code2 = ord(substr($string, $offset, 1)) - 128;
                $codetemp = $codetemp*64 + $code2;
            }
            $code = $codetemp;
        }
        
        $offset += 1;
        if ($offset >= strlen($string)) $offset = -1;
        
        return strtoupper(base_convert($code, 10, 16));
    }
}