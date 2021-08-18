<?php
namespace special;

use \TennisEvents;
use commonlib\GW_Debug;
use commonlib\GW_Support;
use commonlib\BaseLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PickleballSurvey 
{
    public const STRONGAGREE = "Strongly agree";
    public const AGREE = "Agree";
    public const DONTKNOW = "Don't know";
    public const DISAGREE = "Disagree";

    public const VERYINTERESTED = "Very interested";
    public const SOMEWHATINTERESTED = "Somewhat interested";
    public const NOTINTERESTED = "Not interested";
    public const TOTALLYAGAINST = "Totally against";

    public const YES = "Yes";
    public const NO = "No";
    
    private static $survey=["Enjoyed"=>[self::STRONGAGREE=>0
                    , self::AGREE=>0
                    , self::DONTKNOW=>0
                    , self::DISAGREE=>0]
                ,"Interested"=>[self::VERYINTERESTED=>0
                        ,self::SOMEWHATINTERESTED=>0
                        ,self::NOTINTERESTED=>0
                        ,self::TOTALLYAGAINST=>0]
                ,"Invest"=>[self::YES=>0, self::NO=>0]
                ];

    private static $totalResponses = 0;
    
    private static $allEmails = [];

    public function getFlamingoContent() {
        $loc = __FUNCTION__;

        global $wpdb;
        $table = $wpdb->prefix . 'posts';
        $sql = "SELECT post_content FROM $table 
        WHERE `post_type` = 'flamingo_inbound' 
        and `post_title` = 'Pickleball Survey';";
        
        $rows = $wpdb->get_results( $sql, ARRAY_A );

        //echo("<p>$loc: rows</p>");
        // echo "<div>";
        // print_r($rows[0]["post_content"]);
        // echo "</div>";
        return $rows;
    }

    /* A SAMPLE POST_CONTENT FROM FLAMINGO INBOUND POST TYPE
    Robin Smith
    robin.sparky@gmail.com
    Pickleball Survey
    Strongly agree
    Somewhat interested
    No
    1
    24.141.166.36
    Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36
    https://tyandagatennis.com/8fea70813366cee136c5c8f4a424114c/
    August 17, 2021
    9:51 am
    3171
    8fea70813366cee136c5c8f4a424114c
    Pickleball Survey
    https://tyandagatennis.com/8fea70813366cee136c5c8f4a424114c/
    robin
    president@tyandagatennis.com
    Tyandaga Tennis Club
    Burlington's Summer Tennis Club Since 1972
    https://tyandagatennis.com
    robin.sparky@gmail.com
    */
    public function parseFlamingoContent( $field = '') {
        $loc = __FUNCTION__;

        if( empty($field) ) return [];
        //echo "<p>$loc: $field</p>";
        $matches = [];
        preg_match("/\b[a-z0-9.]+\@[a-z0-9.-]+\b/i", $field, $matches);
        $email = '';
        if( count($matches) > 0 ) {
            $email=$matches[0];
            //echo "<p>$email</p>";
            if(in_array($email, self::$allEmails )) return;
            array_push(self::$allEmails, $email);
        }
        else {
            return;
        }

        preg_match("/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/",$field,$matches);
        $ip = '';
        if( count($matches) > 0 ) {
            $ip=$matches[0];
            //echo "<p>$ip</p>";
            // if(in_array($email, self::$allEmails )) return;
            // array_push(self::$allEmails, $email);
        }
        else {
            return;
        }

        ++self::$totalResponses;

        //Enjoyed?
        if( strpos($field, self::STRONGAGREE) > 0 ) {
            self::$survey["Enjoyed"][self::STRONGAGREE] += 1;
        }
        elseif( strpos($field, self::AGREE) ) {
            self::$survey["Enjoyed"][self::AGREE] += 1;
        }
        elseif( strpos($field, self::DONTKNOW) ) {
            self::$survey["Enjoyed"][self::DONTKNOW] += 1;
        }
        elseif( strpos($field, self::DISAGREE) ) {
            self::$survey["Enjoyed"][self::DISAGREE] += 1;
        }

        //Interested in pickleball?
        if( strpos($field, self::VERYINTERESTED) ) {
            self::$survey["Interested"][self::VERYINTERESTED] += 1;
        }
        elseif( strpos($field, self::SOMEWHATINTERESTED) ) {
            self::$survey["Interested"][self::SOMEWHATINTERESTED] += 1;
        }
        elseif( strpos($field, self::NOTINTERESTED) ) {
            self::$survey["Interested"][self::NOTINTERESTED] += 1;
        }
        elseif( strpos($field, self::TOTALLYAGAINST) ) {
            self::$survey["Interested"][self::TOTALLYAGAINST] += 1;
        }
        
        //Willing to invest?
        if( strpos($field, self::YES) ) {
            ++self::$survey["Invest"][self::YES];
        }
        elseif( strpos($field, self::NO) ) {
            ++self::$survey["Invest"][self::NO];
        }
    }

    public function getSurvey() {
        $out = '';
        $out .= '<h2>Pickleball Survey</h2>';
        $out .= '<p>Total Responses=' . self::$totalResponses . '</p>';
        $out .= '<h4>Enjoyed Season?</h4>';
        $out .= '<ol>';
        foreach(self::$survey['Enjoyed'] as $key=>$value ) {
            $perc = number_format(100.0*$value/self::$totalResponses,1);
            $out .= '<li>' . $key . ": " . $value . ' (' . $perc . '%)' ;
        }
        $out .= '</ol>';
        
        $out .= '<h4>Interested in Pickleball?</h4>';
        $out .= '<ol>';
        foreach(self::$survey['Interested'] as $key=>$value ) {
            $perc = number_format(100.0*$value/self::$totalResponses,1);
            $out .= '<li>' . $key . ": " . $value . ' (' . $perc . '%)';
        }
        $out .= '</ol>';

        $out .= '<h4>Willing to Invest?</h4>';
        $out .= '<ol>';
        foreach(self::$survey['Invest'] as $key=>$value ) {
            $perc = number_format(100.0*$value/self::$totalResponses,1);
            $out .= '<li>' . $key . ": " . $value . ' (' . $perc . '%)';
        }
        $out .= '</ol>';
        return $out;
    }

    public function run() {
        
        $rows = $this->getFlamingoContent();
        
        $arrResult = [];
        $ctr = 0;
        foreach($rows as $row) {
            ++$ctr;
            // echo "<div><span>$ctr. </span>";
            // print_r($row["post_content"]);
            // echo "</div>";
            $this->parseFlamingoContent($row["post_content"]);
        }
    }
}