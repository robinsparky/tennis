<?php
use templates\DrawTemplateGenerator;
use commonlib\BaseLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Renders a draw by match or by entrant using shortcodes
 * with actions to manage the draw such as approve
 * @class  ManageDraw
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageDraw
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageTennisDraw';
    const NONCE     = 'manageTennisDraw';
    const SHORTCODE = 'manage_draw';

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
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        //By entrant
        $jsurl =  TE()->getPluginUrl() . 'js/draw.js';
        wp_register_script( 'manage_draw', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        
        //By match
        $jsurl =  TE()->getPluginUrl() . 'js/matches.js';
        wp_register_script( 'manage_matches', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        
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
            'by' => 'match',
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

        $by = $my_atts['by'];
        $this->log->error_log("$loc: by=$by");
        if( !in_array( $by, ['match','entrant']) )  return __('Please specify how to render the draw in shortcode', TennisEvents::TEXT_DOMAIN );

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
            //$user = wp_get_current_user();
            if( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
                wp_deregister_script( 'manage_draw' );
                return $this->renderBracketForWrite( $td, $bracket );
            }
            else {
                wp_deregister_script( 'manage_matches' );
                return $this->renderBracketForRead( $td, $bracket );
            }
        }
        else {
            return  __("No such Bracket {$bracketName}", TennisEvents::TEXT_DOMAIN );
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
            case 'advance':
                $mess = $this->advanceMatches( $data );
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
                //$data['status'] = $chairUmpire->matchStatus( $match );
                $mess = __("Score reset.", TennisEvents::TEXT_DOMAIN );
            }
            else {
                //Set the score for this match 
                foreach( $scores as $score ) {
                    //Record and save scores
                    $chairUmpire->recordScores( $match, $score );
                }

                if( empty($match->getMatchDate_Str()) ) {
                    $match->setMatchDate_Str( date("Y-m-d") );
                    $match->setMatchTime_Str( date("g:i:s") );
                }

                // $numTrimmed = $chairUmpire->trimSets( $match );
                // $data['setsTrimmed'] = $numTrimmed;
                $match->save();//Now save the match

                $statusObj = $chairUmpire->matchStatusEx( $match );
                $data['majorStatus'] = $statusObj->getMajorStatus();
                $data['minorStatus'] = $statusObj->getMinorStatus();
                $data['status'] = $statusObj->toString();

                $data['matchdate'] = $match->getMatchDate_Str();
                $data['matchtime'] = $match->getMatchTime_Str();

                $data['advanced'] = $td->advance( $bracketName );
                $data['displayscores'] = $chairUmpire->tableDisplayScores( $match );
                $data['modifyscores'] = $chairUmpire->tableModifyScores( $match );
                
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
     * Adavance the matches in this bracket.
     * @param array A reference to an array of identifying data and the in progress or final score for a match
     * @return string A message describing success or failure
     */
    private function advanceMatches( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        //$strScore      = strip_tags( htmlspecialchars( $data["score"] ) );
        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );

            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket '{$bracketName}'", TennisEvents::TEXT_DOMAIN) );
            }
            
            $advanced = $td->advance( $bracketName );
            $data['advanced'] = $advanced;
            $mess = __("{$advanced} Matches advanced.", TennisEvents::TEXT_DOMAIN );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['advanced'] = 0;
        }
        return $mess;
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
        $matchStartDate= $data["matchdate"];
        $matchStartTime= $data["matchtime"];
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
            $match->setMatchTime_Str( $matchStartTime );
            $match->save();
            $data['matchdate'] = $match->getMatchDate_Str();
            $data['matchtime'] = $match->getMatchTime_Str();
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
            $data['matchdate'] = '';
            $data['matchtime'] = '';
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
     * in write mode so matches can be modified and scores updated
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return string Table-based HTML showing the draw
     */
    private function renderBracketForWrite( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );

        $winnerClass = "matchwinner";

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        $champion = $td->getChampion( $bracketName );
        $championName = empty( $champion ) ? 'tba' : $champion->getName();
        $umpire = $td->getChairUmpire();

        $loadedMatches = $bracket->getMatchHierarchy( );
        $preliminaryRound = count( $loadedMatches ) > 0 ? $loadedMatches[1] : array();                
        $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );
        $numMatches = $bracket->getNumberOfMatches();

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: num matches:$numMatches; number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");

        $this->eventId = $td->getEvent()->getID();
        $jsData = $this->get_ajax_data();
        $jsData["eventId"] = $this->eventId;
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $signupSize;
        $jsData["numPreliminary"] = $numPreliminaryMatches;
        $jsData["isBracketApproved"] = $bracket->isApproved() ? 1:0;
        $jsData["numSets"] = $umpire->getMaxSets();
        $arrData = $this->getMatchesAsArray( $td, $bracket );
        $jsData["matches"] = $arrData;
        wp_enqueue_script( 'manage_matches' );         
        wp_localize_script( 'manage_matches', 'tennis_draw_obj', $jsData );        

        $begin = <<<EOT
<table id="%s" class="managedraw" data-eventid="%d" data-bracketname="%s">
<caption class='tennis-draw-caption'>%s&#58;&nbsp;%s&nbsp;Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $bracketName, $this->eventId, $bracketName, $tournamentName, $bracketName );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;

        $templ = <<<EOT
<td class="item-player sortable-container ui-state-default" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d"  data-majorstatus="%d"  data-minorstatus="%d">
<div class="menu-icon">
<svg class="dots" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true">
<path d="M8 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM1.5 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm13 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z">
</path>
</svg>

<ul class="matchaction unapproved">
 <li><a class="changehome">Replace Home</a></li>
 <li><a class="changevisitor">Replace Visitor</a><li></ul>
<ul class="matchaction approved">
 <li><a class="recordscore">Enter Score</a></li>
 <li><a class="defaulthome">Default Home</a></li>
 <li><a class="defaultvisitor">Default Visitor</a></li>
 <li><a class="setmatchstart">Start Date &amp; Time</a></li>
 <li><a class="setcomments">Comment Match</a></li></ul>
</div>
<div class="matchinfo matchtitle">%s&nbsp;<span class="matchinfo matchstatus">%s</span></div>
<div class="matchcomments">%s</div>
<div class="matchinfo matchstart">%s &nbsp; %s</div>
<div class="changematchstart">
<input type='date' class='changematchstart' name='matchStartDate' value='%s'>
<input type='time' class='changematchstart' name='matchStartTime' value='%s'>
<button class='button savematchstart'>Save</button> <button class='button cancelmatchstart'>Cancel</button></div>
<div class="homeentrant %s">%s</div>
<div class="displaymatchscores"><!-- Display Scores Container -->
%s</div>
<div class="modifymatchscores tennis-modify-scores"><!-- Modify Scores Container -->
%s</div>
<div class="visitorentrant %s">%s</div>
</td>
EOT;

        $champTemplate = <<<EOT
<td class="item-player sortable-container ui-state-default" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d"  data-majorstatus="%d"  data-minorstatus="%d">
<div class="tennis-champion">%s</div>
</td>
EOT;   

        $rowEnder = "</tr>" . PHP_EOL;
        //rows
        $row = 0;
        foreach( $preliminaryRound as $match ) {
            ++$row;
            $out .= "<tr>";
            $r = 1; //means preliminary round (i.e. first column)
            $nextRow = '';
            try {
                $title = $match->title();
                $this->log->error_log("$loc: preliminary match: $title");
                $eventId = $match->getBracket()->getEvent()->getID();
                $bracketNum = $match->getBracket()->getBracketNumber();
                $roundNum = $match->getRoundNumber();
                $matchNum = $match->getMatchNumber();

                $winner  = $umpire->matchWinner( $match );
                $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();

                $homeWinner = $visitorWinner = '';
                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                if( $hname === $winner ) $homeWinner = $winnerClass;
                
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

                $visitor = $match->getVisitorEntrant();
                $vname   = $match->isBye() ? '' : 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = $visitor->getName();
                    if( $vname === $winner ) $visitorWinner = $winnerClass;
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }
                $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                $cmts = $match->getComments();
                $cmts = isset( $cmts ) ? $cmts : '';
                if( $match->isBye() ) {
                    $displayscores = "<span></span>";
                    $modifyscores  = "<span></span>";
                }
                else {
                    $displayscores = $umpire->tableDisplayScores( $match );
                    $modifyscores  = $umpire->tableModifyScores( $match );
                }

                //$generalstatus  = $umpire->matchStatus( $match );
                $statusObj = $umpire->matchStatusEx( $match );
                $majorStatus = $statusObj->getMajorStatus();
                $minorStatus = $statusObj->getMinorStatus();
                $generalstatus = $statusObj->toString();

                $startDate = $match->getMatchDate_Str();
                $startTime = $match->getMatchTime_Str();

                $out .= sprintf( $templ, $r, $eventId, $bracketNum, $roundNum, $matchNum, $majorStatus, $minorStatus
                               , $match->toString()
                               , $generalstatus
                               , $cmts 
                               , $startDate
                               , $startTime
                               , $startDate
                               , $startTime
                               , $homeWinner
                               , $hname
                               , $displayscores
                               , $modifyscores
                               , $visitorWinner
                               , $vname );

                //Future matches following from this match
                $futureMatches = $this->getFutureMatches( $match->getNextRoundNumber(), $match->getNextMatchNumber(), $loadedMatches );
                $rowspan = 1;
                foreach( $futureMatches as $futureMatch ) {
                    $rowspan = pow( 2, $r++ );//The trick!
                    $eventId = $futureMatch->getBracket()->getEvent()->getID();
                    $bracketNum = $futureMatch->getBracket()->getBracketNumber();
                    $roundNum = $futureMatch->getRoundNumber();
                    $matchNum = $futureMatch->getMatchNumber();
                    
                    $winner  = $umpire->matchWinner( $futureMatch );
                    $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();
                    
                    $homeWinner = $visitorWinner = '';
                    $home    = $futureMatch->getHomeEntrant();
                    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                    if( $hname === $winner ) $homeWinner = $winnerClass;
                    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";
    
                    $visitor = $futureMatch->getVisitorEntrant();      
                    $vname   = $futureMatch->isBye() ? '' : 'tba';
                    $vseed   = '';
                    if( isset( $visitor ) ) {
                        $vname   = $visitor->getName();
                        if( $vname === $winner ) $visitorWinner = $winnerClass;
                        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                    }
                    $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                    $cmts = $futureMatch->getComments();
                    $cmts = isset( $cmts ) ? $cmts : '';

                    $startDate = $futureMatch->getMatchDate_Str();
                    $startTime = $futureMatch->getMatchTime_Str();
                    
                    $displayscores = $umpire->tableDisplayScores( $futureMatch );
                    $modifyscores = $umpire->tableModifyScores( $futureMatch );  

                    $statusObj = $umpire->matchStatusEx( $futureMatch );
                    $majorStatus = $statusObj->getMajorStatus();
                    $minorStatus = $statusObj->getMinorStatus();
                    $generalstatus = $statusObj->toString();

                    $out .= sprintf( $templ, $rowspan, $eventId, $bracketNum, $roundNum, $matchNum, $majorStatus, $minorStatus
                                   , $futureMatch->toString() 
                                   , $generalstatus
                                   , $cmts       
                                   , $startDate
                                   , $startTime  
                                   , $startDate
                                   , $startTime        
                                   , $homeWinner
                                   , $hname
                                   , $displayscores
                                   , $modifyscores
                                   , $visitorWinner
                                   , $vname);
                } //future matches  
                
                //Champion column
                if( 1 === $row && $bracket->isApproved() ) {
                    $out .= sprintf( $champTemplate, $rowspan, $eventId, $bracketNum, 0, 0, 0, 0
                                    , $championName );
                }  
            }
            catch( RuntimeException $ex ) {
                $rowEnder = '';
                $this->log->error_log("$loc: preliminary round is empty at row $row");
            }
            finally {
                $out .= $rowEnder;
            }  
        } //preliminaryRound  
        
        $out .= "</tbody><tfooter></tfooter>";
        $out .= "</table>";	 
        $out .= "<div class='bracketDrawButtons'>";
        if( $numPreliminaryMatches > 0 ) {
            if( !$bracket->isApproved() ) {
                $out .= '<button class="button" type="button" id="approveDraw">Approve</button>' . PHP_EOL;
            }
            if( !empty($champion) ) {
                $out .= '<button class="button" type="button" id="advanceMatches">Advance Matches</button>' . PHP_EOL;
            }

            $out .= '<button class="button" type="button" id="removePrelim">Reset Bracket</button>&nbsp;' . PHP_EOL;
        }

        $out .= "</div>";

        $out .= '<div id="tennis-event-message"></div>';
		$this->log->error_log( sprintf("%0.6f",\commonlib\micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $out;
    }
    
    /**
     * Renders rounds and matches for the given bracket
     * in read-only mode
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return string Table-based HTML showing the draw
     */
    private function renderBracketForRead( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );

        $winnerClass = "matchwinner";

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        $champion = $td->getChampion( $bracketName );
        $championName = empty( $champion ) ? 'tba' : $champion->getName();
        $umpire = $td->getChairUmpire();

        $loadedMatches = $bracket->getMatchHierarchy( true );
        $preliminaryRound = count( $loadedMatches ) > 0 ? $loadedMatches[1] : array();                
        $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );
        $numMatches = $bracket->getNumberOfMatches();

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: num matches:$numMatches; number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");

        $this->eventId = $td->getEvent()->getID();
        $jsData = $this->get_ajax_data();
        $jsData["eventId"] = $this->eventId;
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $signupSize;
        $jsData["numPreliminary"] = $numPreliminaryMatches;
        $jsData["isBracketApproved"] = $bracket->isApproved() ? 1:0;
        $jsData["numSets"] = $umpire->getMaxSets();
        $arrData = $this->getMatchesAsArray( $td, $bracket );
        $jsData["matches"] = $arrData;
        wp_enqueue_script( 'manage_matches' );         
        wp_localize_script( 'manage_matches', 'tennis_draw_obj', $jsData );        

        $begin = <<<EOT
<table id="%s" class="managedraw" data-eventid="%d" data-bracketname="%s">
<caption class='tennis-draw-caption'>%s&#58;&nbsp;%s&nbsp;Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $bracketName, $this->eventId, $bracketName, $tournamentName, $bracketName );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;

        $templ = <<<EOT
<td class="item-player" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d" data-majorstatus="%d" data-minorstatus="%d">
<div class="matchinfo matchtitle">%s&nbsp;<span class="matchinfo matchstatus">%s</span></div>
<div class="matchcomments">%s</div>
<div class="matchinfo matchstart">%s &nbsp; %s</div>
<div class="homeentrant %s">%s</div>
<div class="displaymatchscores"><!-- Display Scores -->
<span>%s</span></div>
<div class="visitorentrant %s">%s</div>
</td>
EOT;

    $champTemplate = <<<EOT
<td class="item-player sortable-container ui-state-default" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d"  data-majorstatus="%d"  data-minorstatus="%d">
<div class="tennis-champion">%s</div>
</td>
EOT; 

        $rowEnder = "</tr>" . PHP_EOL;
        //rows
        $row = 0;
        foreach( $preliminaryRound as $match ) {
            ++$row;
            $out .= "<tr>";
            $r = 1; //means preliminary round (i.e. first column)
            $nextRow = '';
            try {
                $title = $match->title();
                $this->log->error_log("$loc: preliminary match: $title");
                $eventId = $match->getBracket()->getEvent()->getID();
                $bracketNum = $match->getBracket()->getBracketNumber();
                $roundNum = $match->getRoundNumber();
                $matchNum = $match->getMatchNumber();

                $winner  = $umpire->matchWinner( $match );
                $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();

                $homeWinner = $visitorWinner = '';
                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                if( $hname === $winner ) $homeWinner = $winnerClass;
                
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

                $visitor = $match->getVisitorEntrant();
                $vname   = $match->isBye() ? '' : 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = $visitor->getName();
                    if( $vname === $winner ) $visitorWinner = $winnerClass;
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }
                $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                $cmts = $match->getComments();
                $cmts = isset( $cmts ) ? $cmts : '';
                if( $match->isBye() ) {
                    $displayscores = "<span></span>";
                }
                else {
                    $displayscores = $umpire->strGetScores( $match );
                }

                $statusObj = $umpire->matchStatusEx( $match );
                $majorStatus = $statusObj->getMajorStatus();
                $minorStatus = $statusObj->getMinorStatus();
                $generalstatus = $statusObj->toString();

                $startDate = $match->getMatchDate_Str();
                $startTime = $match->getMatchTime_Str();
                if( !empty($startDate) || !empty($startTime)) {
                    $startDate = "Started: " . $startDate;
                }

                $out .= sprintf( $templ, $r, $eventId, $bracketNum, $roundNum, $matchNum, $majorStatus, $minorStatus
                               , $match->toString()
                               , $generalstatus
                               , $cmts 
                               , $startDate
                               , $startTime
                               , $homeWinner
                               , $hname
                               , $displayscores
                               , $visitorWinner
                               , $vname );

                $futureMatches = $this->getFutureMatches( $match->getNextRoundNumber(), $match->getNextMatchNumber(), $loadedMatches );
                $rowspan = 1;
                foreach( $futureMatches as $futureMatch ) {
                    $rowspan = pow( 2, $r++ );
                    $eventId = $futureMatch->getBracket()->getEvent()->getID();
                    $bracketNum = $futureMatch->getBracket()->getBracketNumber();
                    $roundNum = $futureMatch->getRoundNumber();
                    $matchNum = $futureMatch->getMatchNumber();
                    
                    $winner  = $umpire->matchWinner( $futureMatch );
                    $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();
                    
                    $homeWinner = $visitorWinner = '';
                    $home    = $futureMatch->getHomeEntrant();
                    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                    if( $hname === $winner ) $homeWinner = $winnerClass;
                    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";
    
                    $visitor = $futureMatch->getVisitorEntrant();      
                    $vname   = $futureMatch->isBye() ? '' : 'tba';
                    $vseed   = '';
                    if( isset( $visitor ) ) {
                        $vname   = $visitor->getName();
                        if( $vname === $winner ) $visitorWinner = $winnerClass;
                        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                    }
                    $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                    $cmts = $futureMatch->getComments();
                    $cmts = isset( $cmts ) ? $cmts : '';

                    $startDate = $futureMatch->getMatchDate_Str();
                    $startTime = $futureMatch->getMatchTime_Str();
                    if( !empty($startDate) || !empty($startTime)) {
                        $startDate = "Started: " . $startDate;
                    }
                    
                    $displayscores = $umpire->strGetScores( $futureMatch );

                    $statusObj = $umpire->matchStatusEx( $futureMatch );
                    $majorStatus = $statusObj->getMajorStatus();
                    $minorStatus = $statusObj->getMinorStatus();
                    $generalstatus = $statusObj->toString(); 

                    $out .= sprintf( $templ, $rowspan, $eventId, $bracketNum, $roundNum, $matchNum, $majorStatus, $minorStatus
                                   , $futureMatch->toString() 
                                   , $generalstatus
                                   , $cmts       
                                   , $startDate
                                   , $startTime       
                                   , $homeWinner
                                   , $hname
                                   , $displayscores
                                   , $visitorWinner
                                   , $vname);
                }
                
                //Champion column
                if( 1 === $row && $bracket->isApproved() ) {
                    $out .= sprintf( $champTemplate, $rowspan, $eventId, $bracketNum, 0, 0, 0, 0
                                    ,$championName );
                }       
            }
            catch( RuntimeException $ex ) {
                $rowEnder = '';
                $this->log->error_log("$loc: preliminary round is empty at row $row");
            }
            finally {
                $out .= $rowEnder;
            }  
        } //preliminaryRound  
             
        $out .= "</tbody><tfooter></tfooter>";
        $out .= "</table>";	

        $out .= '<div id="tennis-event-message"></div>';
		$this->log->error_log( sprintf("%0.6f",commonlib\micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $out;
    }


    /**
     * Recursive function to extract all following matches from the given match
     * @param $startObj The starting match
     * @param $rounds Reference to an array of arrays reprsenting all matches beyond the priliminary one
     * @return array of match objects
     */
    private function getNextMatches( $startObj, array &$rounds ) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $found = array();
        $nr = $startObj->getNextRoundNumber();
        $nm = $startObj->getNextMatchNumber();
        $nr = isset($nr) ? $nr : -1;
        $nm = isset($nm) ? $nm : -1;

        foreach( $rounds as $round ) {
            for( $i = 0; $i < count($round); $i++ ) {
                if( !isset($round[$i]) ) continue;
                $obj = $round[$i];  
                $r = $obj->getRoundNumber();
                $m = $obj->getMatchNumber();
                $r = isset( $r) ? $r : -1;
                $m = isset( $m ) ? $m : -1;
                if( $r == $nr && $m == $nm ) {
                    $found[] = $obj;
                    unset($round[$i]);
                    $more = $this->getNextMatches( $obj, $rounds );
                    foreach($more as $next) {
                        $found[] = $next;
                    }
                    break;
                }
            }
        }
        return $found;
    }

    private function getFutureMatches( $nr, $nm, &$matchHierarchy ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( "$loc(nextRound=$nr, nextMatch=$nm)" );

        $futureMatches = array();
        $rndNum = $nr;
        foreach( $matchHierarchy as $key => &$round ) {
            $count = count( $round );
            $this->log->error_log("$loc: future round #$key has $count matches." );

            $futureMatch = null;
            foreach( $round as $key=>$m ) {
                if( $m->getRoundNumber() === $nr && $m->getMatchNumber() === $nm ) {
                    $futureMatch = $m;
                    unset( $round[$key] );
                    break;
                }
            }
            if( !is_null( $futureMatch ) ) {
                $title = $futureMatch->title();
                $this->log->error_log("$loc: found $count future match[$nr][$nm]: $title");
                $futureMatches[] = $futureMatch;
                unset( $matchHierarchy[$nr][$nm] );
                $nr = $futureMatch->getNextRoundNumber();
                $nm = $futureMatch->getNextMatchNumber();
            }
            else {
                $this->log->error_log("$loc: no future matches found.**************");
            }
            ++$rndNum;
        }

        $count=count($futureMatches);
        $this->log->error_log("$loc: returning $count matches");

        return $futureMatches;
    }

    /**
     * Renders draw showing entrants for the given bracket
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return string Table-based HTML showing the draw without ability to modify
     */
    private function renderBracketByEntrant( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        $eventId = $td->getEvent()->getID();

        $loadedMatches = $bracket->getMatches();
        $preliminaryRound = $bracket->getMatchesByRound( 1 );                
        $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");

        if( count( $loadedMatches ) === 0 ) {
            $out = '<h3>' . "{$tournamentName}&#58;&nbsp;{$bracketName} Bracket" . '</h3>';
            $out .= "<div>". __("No matches scheduled yet", TennisEvents::TEXT_DOMAIN ) . "</div>";
            return $out;
        }

        $jsData = $this->get_ajax_data();
        $arrData = $this->getMatchesAsArray( $td, $bracket );
        $jsData["matches"] = $arrData;

        wp_enqueue_script( 'manage_draw' );         
        wp_localize_script( 'manage_draw', 'tennis_draw_obj', $jsData );      

        $umpire = $td->getChairUmpire();
        $gen = new DrawTemplateGenerator("{$tournamentName}&#58;&nbsp;{$bracketName} Bracket", $signupSize, $eventId, $bracketName  );
        
        $template = $gen->generateTable();
        
        $template .= PHP_EOL . '<div id="tennis-event-message"></div>' . PHP_EOL;

        return $template;
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

    private function expandMatchesToPlayers( &$umpire, &$matches ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $players = array();
        $col = 1;
        foreach( $matches as $round ) {
            $row = 1;
            foreach( $round as $match ) {
                $title = $match->title();
                $this->log->error_log("$loc: match: $title");
                $cmts = $match->getComments();
                $cmts = isset( $cmts ) ? $cmts : '';
                $score   = $umpire->tableGetScores( $match );                        
                $status  = $umpire->matchStatus( $match );
                $winner  = $umpire->matchWinner( $match );
                $winner  = is_null( $winner ) ? 'tba': $winner->getName();

                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                $hname    = empty($hseed) ? $hname : $hname . "($hseed)";
                $this->log->error_log("$loc: adding home:$hname in $title to [$row][$col] ");
                $players[$row++][$col] = $hname;
                
                $visitor = $match->getVisitorEntrant();
                $vname   = 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = $visitor->getName();
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }
                $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                $this->log->error_log("$loc: adding visitor:$vname in $title to [$row][$col] ");
                $players[$row++][$col] = $vname;
            }
            ++$col;
        }
        return $players;
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