<?php
use templates\DrawTemplateGenerator;
use api\BaseLoggerEx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Renders a Round Robin by match using shortcodes
 * with actions to manage the RR such as approve
 * @class  ManageRoundRobin
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageRoundRobin
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageTennisRoundRobin';
    const NONCE     = 'manageTennisRoundRobin';
    const SHORTCODE = 'manage_roundrobin';

    private $eventId = 0;
    private $errobj = null;
    private $errcode = 0;
    private $log;

    public static function register() {
        $handle = new self();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
        $handle->registerHandlers();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();	
        $this->log = new BaseLoggerEx( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        $jsurl =  TE()->getPluginUrl() . 'js/matches.js';
        wp_register_script( 'manage_rr', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        
        $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }
    
    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        // Handle the ajax request
        check_ajax_referer(  self::NONCE, 'security'  );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisEvents::TEXT_DOMAIN ));
        $this->handleErrors("You've been a bad boy.");
    }
     

	public function renderShortcode( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

        if( $_POST ) {
            $this->log->error_log($_POST, "$loc: POST:");
        }

        if( $_GET ) {
            $this->log->error_log($_GET, "$loc: GET:");
        }

        $my_atts = shortcode_atts( array(
            'clubname' => '',
            'eventid' => 0,
            'bracketname' => Bracket::WINNERS
        ), $atts, 'render_draw' );

        $this->log->error_log( $my_atts, "$loc: My Atts" );

        //Get the Club from attributes
        $club = null;
        if(!empty( $my_atts['clubname'] ) ) {
            $arrClubs = Club::search( $my_atts['clubName'] );
            if( count( $arrClubs) > 0 ) {
                $club = $arrClubs[0];
            }
        }
        else {
            $homeClubId = esc_attr( get_option(self::HOME_CLUBID_OPTION_NAME, 0) );
            $club = Club::get( $homeClubId );
        }
        if( is_null( $club ) ) return __('Please set home club id or specify name in shortcode', TennisEvents::TEXT_DOMAIN );

        //Get the event from attributes
        $eventId = (int)$my_atts['eventid'];
        $this->log->error_log("$loc: EventId=$eventId");
        if( $eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $evts = Event::find( array( "club" => $club->getID() ) );
        //$this->log->error_log( $evts, "$loc: All events for {$club->getName()}");
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = Event::getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
        }

        if( !$found ) {
            $mess = sprintf("No such event=%d for the club '%s'", $eventId, $club->getName() );
            return __($mess, TennisEvents::TEXT_DOMAIN );
        }

        //Get the bracket from attributes
        $bracketName = $my_atts["bracketname"];

        //Go
        $td = new TournamentDirector( $target );
        $bracket = $td->getBracket( $bracketName );
        if( is_null( $bracket ) ) {            
            $mess = sprintf("No such bracket='%s' for the event '%s'", $bracketName, $target->getName() );
            return __($mess, TennisEvents::TEXT_DOMAIN );
        }

        if( !is_null( $bracket ) ) {
            return $this->renderBracketByMatch( $td, $bracket );
        }
        else {
            return  __("No such Bracket $bracketName", TennisEvents::TEXT_DOMAIN );
        }
    }
    
    /**
     * Perform the tasks as indicated by the Ajax request
     */
    public function performTask() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        $this->log->error_log( $_POST, "$loc: _POST:"  );

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        $ok = false;
        if( current_user_can( 'manage_options' ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Only administrators can modify draw.', TennisEvents::TEXT_DOMAIN ));
        }
        
        if(count($this->errobj->errors) > 0) {
            $this->handleErrors(__("Errors were encountered", TennisEvents::TEXT_DOMAIN  ) );
        }

        $response = array();

        $data = $_POST["data"];
        $task = $data["task"];
        $this->eventId = $data["eventId"];
        $event = Event::get( $this->eventId );
        $bracketName = $data["bracketName"];
        $bracket = $event->getBracket( $bracketName );
        $returnData = $task;
        $mess = '';
        switch( $task ) {
            case "getdata":
                $td = new TournamentDirector( $event );
                $arrData = $this->getMatchesAsArray( $td, $bracket );
                $mess = "Data for $bracketName bracket";
                break;
            case "reset":
                $mess = $this->removePreliminary( $data );
                break;
            case "approve":
                $mess = $this->approvePreliminary( $data );
                break;
            case 'changehome':
                $mess = $this->changeHomeEntrant( $data );
                $returnData = $data;
                break;
            case 'changevisitor':
                $mess = $this->changeVisitorEntrant( $data );
                $returnData = $data;
                break;
            case 'savescore':
                $mess = $this->recordScore( $data );
                $returnData = $data;
                break;
            case 'defaultentrant':
                $mess = $this->defaultEntrant( $data );
                $returnData = $data;
                break;
            case 'setcomments':
                $mess = $this->setComments( $data );
                $returnData = $data;
                break;
            case 'setmatchstart':
                $mess = $this->setMatchStart( $data );
                $returnData = $data;
                break;
            default:
                $mess =  __( 'Illegal task.', TennisEvents::TEXT_DOMAIN );
                $this->errobj->add( $this->errcode++, $mess );
                break;
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors( $mess );
        }

        $response["message"] = $mess;
        $response["returnData"] = $returnData;

        //Send the response
        wp_send_json_success( $response );
    
        wp_die(); // All ajax handlers die when finished
    }

    /**
     * Modify the Home Entrant in a match identified in the data
     * @param array $data array of event/match identifiers and new home player name
     */
    private function changeHomeEntrant( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        $player        = strip_tags( htmlspecialchars( $data["player"] ) );
        $mess          = __("Modified home entrant.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $newHome = $event->getNamedEntrant( $player );
            if( is_null( $newHome ) ) {
                throw new InvalidEntrantException(__("No such player", TennisEvents::TEXT_DOMAIN) );
            }
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $match->setHomeEntrant( $newHome );
            $match->save();
            $returnName = $newHome->getSeededName();
            $data['player'] = $returnName;
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['player'] = '';
        }
        return $mess;
    }
    
    /**
     * Modify the Visitor Entrant in a match identified in the data
     * @param array $data A reference to an array of event/match identifiers and new visitor player name
     * @return string A message describing success or failure
     */
    private function changeVisitorEntrant( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        $player        = strip_tags( htmlspecialchars( $data["player"] ) );
        $mess          = __("Modified visitor entrant.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $newVisitor = $event->getNamedEntrant( $player );
            if( is_null( $newVisitor ) ) {
                throw new InvalidEntrantException(__("No such player", TennisEvents::TEXT_DOMAIN) );
            }
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $match->setVisitorEntrant( $newVisitor );
            $match->save();
            $returnName = $newVisitor->getSeededName();
            $data['player'] = $returnName;
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['player'] = '';
        }
        return $mess;
    }

    /**
     * Record the score for a match identified in $data.
     * @param array A reference to an array of identifying data and the in progress or final score for a match
     * @return string A message describing success or failure
     */
    private function recordScore( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        //$strScore      = strip_tags( htmlspecialchars( $data["score"] ) );
        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $chairUmpire = $td->getChairUmpire();
            $scores = $data["score"]; //$this->parseScores( $strScore );
            if( is_string( $scores ) ) {
                //This is a backdoor to reset an old score
                // that needs changing but should not affect outcomes of matches
                $match->removeSets();
                $match->save();
                $data['score'] = '';
                $data['status'] = $chairUmpire->matchStatus( $match );
                $mess          = __("Score reset.", TennisEvents::TEXT_DOMAIN );
            }
            else {
                //Set the score for this match 
                foreach( $scores as $score ) {
                    $chairUmpire->recordScores( $match
                                                , $score["setNum"]
                                                , $score["homeGames"]
                                                , $score["homeTieBreaker"]
                                                , $score["visitorGames"]
                                                , $score["visitorTieBreaker"] );
                    if( $chairUmpire->isLocked( $match ) ) break;
                }

                if( empty($match->getMatchDate_Str()) ) {
                    $match->setMatchDate_Str( date("Y-m-d") );
                    $match->setMatchTime_Str( date("g:i:s") );
                }

                $numTrimmed = $chairUmpire->trimSets( $match );
                $data['setsTrimmed'] = $numTrimmed;
                $match->save();

                $statusObj = $chairUmpire->matchStatusEx( $match );
                $data['majorStatus'] = $statusObj->getMajorStatus();
                $data['minorStatus'] = $statusObj->getMinorStatus();
                $data['status'] = $statusObj->toString();

                $data['matchdate'] = $match->getMatchDate_Str();
                $data['matchtime'] = $match->getMatchTime_Str();

                $data['advanced'] = 0; //$td->advance( $bracketName );
                $data['displayscores'] = $chairUmpire->tableDisplayScores( $match );
                $data['modifyscores'] = $chairUmpire->tableModifyScores( $match );
                
                $summaryTable = $td->getEntrantSummary( $bracket );
                //$this->log->error_log($summaryTable, "$loc - entrant summary");
                $data["entrantSummary"] = $summaryTable;

                $winner = $chairUmpire->matchWinner( $match );
                $data['winner'] = '';
                if( !is_null( $winner ) ) {
                    if( $chairUmpire->winnerIsVisitor( $match ) ) {
                        $data['winner'] = 'visitor';
                    }
                    else {
                        $data['winner'] = 'home';
                    }
                }
                $mess = __("Score recorded.", TennisEvents::TEXT_DOMAIN );
            }
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['score'] = '';
        }
        return $mess;
    }

    /**
     * Parse the scores provided as a string 
     * E.G. 6-3, 1-6, 6-6(3)
     * @param string $scores
     * @return array of score objects by set number
     */
    private function parseScores( string $scores ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc($scores)");

        $result = [];
        if( empty( $scores ) ) return $result; //early return

        if( 'reset' === $scores ) {
            return $scores;
        }

        $sets = explode( ',', $scores, 5 );
        if( count( $sets ) < 1 ) return $result; //early return

        $setNum = 1;
        foreach( $sets as $setscore ) {
            $setObj = new \stdClass;
            $setObj->setNum = $setNum++;
            $mscore = explode( '-', $setscore, 2 );
            if( count( $mscore ) !== 2 ) return $result; //early return

            $setObj->homeScore = intval( $mscore[0] );
            $setObj->visitorScore = intval( $mscore [1]);
            $setObj->homeTBscore = 0;
            $setObj->visitorTBscore = 0;

            //Check for tie breaker scores
            $needle = "(";
            if( in_array( $set->setNum, [3,5]) ) {
                if( strpos( $mscore[0], $needle ) > 0 ) {
                    $setObj->homeTBscore = intval( strstr($mscore[0], $needle ) );
                }
                elseif( strpos( $mscore[1], $needle ) > 0 ) {
                    $setObj->visitorTBscore = intval( strstr($mscore[0], $needle ) );
                }
            }
            $this->log->error_log( $setObj, "$loc" );
            $result[] = $setObj;
        }

        return $result;
    }
    
    /**
     * Default an entrant and record the comments for a specific match identified in $data
     * @param array $data A reference to an array of event/match identifiers and new visitor player name
     * @return string A message describing success or failure
     */
    private function defaultEntrant( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        $player        = $data["player"];
        $comments      = $data["comments"];
        $comments      = strip_tags( htmlspecialchars( $comments ) );
        $mess          = __("Defaulted '$player'", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $chairUmpire = $td->getChairUmpire();
            switch( $player ) {
                case "home":
                    $chairUmpire->defaultHome( $match, $comments );
                    $status = $chairUmpire->matchStatus( $match );
                    $data['advanced'] = $td->advance( $bracketName );
                    break;
                case "visitor":
                    $chairUmpire->defaultVisitor( $match, $comments );
                    $status = $chairUmpire->matchStatus( $match );
                    $data['advanced'] = $td->advance( $bracketName );
                    break;
                default:
                    $mess  = __("Unable to default '$player'", TennisEvents::TEXT_DOMAIN );
                    throw new InvalidArgumentException($mess);
                    break;
            }
            $data['status'] = $status;
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['status'] = '';
        }
        return $mess;
    }


    /**
     * Set the comments for a specific match identified in $data
     * @param array $data A reference to an array of event/match identifiers and new visitor player name
     * @return string A message describing success or failure
     */
    private function setComments( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        $comments      = $data["comments"];
        $comments      = strip_tags( htmlspecialchars( $comments ) );
        $mess          = __("Set Comments.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $match->setComments( $comments );
            $match->save();
            $data['comments'] = $comments;
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['comments'] = '';
        }
        return $mess;
    }

    /**
     * Set the match's start date and time
     * @param array $data A reference to an array of event/match identifiers and new visitor player name
     * @return string A message describing success or failure
     */
    private function setMatchStart( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $bracketNum    = $data["bracketNum"];
        $roundNum      = $data["roundNum"];
        $matchNum      = $data["matchNum"];
        $matchStartDate= $data["matchstartdate"];
        $matchStartTime= $data["matchstarttime"];
        $mess          = __("Set Start Match Date/Time.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $timestamp = strtotime( $matchStartDate );
            $match->setMatchDate_TS( $timestamp );
            list( $hours, $minutes ) = explode(":", $matchStartTime );
            $match->setMatchTime( $hours, $minutes );
            $match->save();
            $data['matchstartdate'] = $match->getMatchDate_Str();
            $data['matchstarttime'] = $match->getMatchTime_Str();
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['matchstartdate'] = '';
            $data['matchstarttime'] = '';
        }
        return $mess;
    }

    
    /**
     * Approve the preliminary round
     */
    private function approvePreliminary( $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $mess          = __('Approve succeeded.', TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $td->approve( $bracketName );
            $td->advance( $bracketName );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }
    
    /**
     * Delete the preliminary round
     */
    private function removePreliminary( $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $bracketName = $data["bracketName"];
        try {            
            $event = Event::get( $this->eventId );
            $evtName = $event->getName();
            $td = new TournamentDirector( $event );
            $td->removeMatches( $bracketName );
            $numAffected = $event->save();
            $mess =  __("Removed all matches for {$evtName} - {$bracketName} Bracket.", TennisEvents::TEXT_DOMAIN );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Renders rounds and matches for the given bracket
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return HTML for table-based page showing the draw
     */
    private function renderBracketByMatch( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
		$startFuncTime = microtime( true );

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        // if( !$bracket->isApproved() ) {
        //     return __("'$tournamentName ($bracketName bracket)' has not been approved", TennisEvents::TEXT_DOMAIN );
        // }

        $umpire = $td->getChairUmpire();

        $loadedMatches = $bracket->getMatchHierarchy( true );
        // $preliminaryRound = count( $loadedMatches ) > 0 ? $loadedMatches[1] : array();                
        // $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );
        $numMatches = $bracket->numMatches();
        $matchesByEntrant = $bracket->matchesByEntrant();

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: num matches:$numMatches; number rounds=$numRounds; signup size=$signupSize");

        $this->eventId = $td->getEvent()->getID();
        $jsData = $this->get_ajax_data();
        $jsData["eventId"] = $this->eventId;
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $signupSize;
        $jsData["isBracketApproved"] = $bracket->isApproved() ? 1:0;
        $jsData["numSets"] = $umpire->getMaxSets();
        $arrData = $this->getMatchesAsArray( $td, $bracket );
        $jsData["matches"] = $arrData; 
        wp_enqueue_script( 'manage_rr' );         
        wp_localize_script( 'manage_rr', 'tennis_draw_obj', $jsData );        
        
	    // Start output buffering we don't output to the page
        ob_start();
        //Render the point summary
        $path = TE()->getPluginPath() . '\includes\templates\pointscore-template.php';
        require( $path );

        // Get template file
        $path = TE()->getPluginPath() . '\includes\templates\render-roundrobinreadonly.php';
        //$user = wp_get_current_user();
        if( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $path = TE()->getPluginPath() . '\includes\templates\render-roundrobin.php';
        }
        require( $path );

        // Save output and stop output buffering
        $output = ob_get_clean();

        $this->log->error_log( sprintf("%0.6f",micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    /**
     * Get the Draw's match data as array
     * Needed in order to serialize for json
     */
    private function getMatchesAsArray(TournamentDirector $td,  Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $matches = $bracket->getMatches();
        $chairUmpire = $td->getChairUmpire();

        $arrMatches = [];
        foreach( $matches as $match ) {
            $arrMatch = $match->toArray();
            $winner = $chairUmpire->matchWinner( $match );
            $status = $chairUmpire->matchStatus( $match );
            $strScores = $chairUmpire->strGetScores( $match );
            $arrMatch["scores"] = $strScores;
            $arrMatch["status"] = $status;
            $arrMatch["winner"] = is_null( $winner ) ? '' : $winner->getName();
            $arrMatches[] = $arrMatch;
        }
        return $arrMatches;
    }
    
    private function getMatchesJson( TournamentDirector $td,  Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $arrMatches = $this->getMatchesAsArray( $td, $bracket );

        $json = json_encode($arrMatches);
        $this->log->error_log( $json, "$loc: json:");

        return $json;

    }

    private function sendMatchesJson( Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $json = $this->getMatchesJson( $bracket );
        $this->log->error_log( $json, "$loc: json:");

        $script = "window.bracketmatches = $json; ";

        gw_enqueue_js($script);
    }

    /**
     * Get the AJAX data that WordPress needs to output.
     *
     * @return array
     */
    private function get_ajax_data()
    {        
        $mess = '';
        return array ( 
             'ajaxurl' => admin_url( 'admin-ajax.php' )
            ,'action' => self::ACTION
            ,'security' => wp_create_nonce( self::NONCE )
            ,'message' => $mess
        );
    }

    private function handleErrors( string $mess ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        $response = array();
        $response["message"] = $mess;
        $response["exception"] = $this->errobj;
        wp_send_json_error( $response );
        wp_die($mess);
    }
}