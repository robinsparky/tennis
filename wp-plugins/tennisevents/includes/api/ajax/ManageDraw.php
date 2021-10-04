<?php
namespace api\ajax;
use \Exception;
use \InvalidArgumentException;
use \WP_Error;
use \TennisEvents;
use commonlib\BaseLogger;
use api\TournamentDirector;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Club;
use datalayer\InvalidMatchException;
use datalayer\InvalidBracketException;
use datalayer\InvalidTennisOperationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Performs ajax actions to manage the elimination draw:
 * Preliminary schedules:
 *  Approve preliminary schedule
 *  Change the home
 *  Change the visitor
 * Approved schedules:
 *  Set the start date and time
 *  Record scores
 *  Default the home
 *  Default the visitor
 *  Advance completed matches thru the schedule
 *  Comment a match
 *  Reset the draw (i.e. remove all matches)
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


    /**
     * No scripts to register.
     * The scripts are registered by RenderDraw
     */
    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        //$this->log->error_log( $loc );
    }
    
    /**
     * The ajax methods for Managing the elimination Draw
     */
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }
    
    /**
     * The method called when illegal attempts to request ajax operations are made
     */
    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        // Handle the ajax request
        check_ajax_referer(  self::NONCE, 'security'  );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisEvents::TEXT_DOMAIN ));
        $this->handleErrors("You've been a bad boy.");
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
        
        $this->log->error_log("$loc: action={$_POST['action']}");
        if( self::ACTION !== $_POST['action']) return;
        
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
            case 'move':
                $mess = $this->swapPlayers( $data );
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
                //$data['status'] = $chairUmpire->matchStatusEx( $match )->toString();
                $mess = __("Score reset.", TennisEvents::TEXT_DOMAIN );
            }
            else {
                //Set the score for this match 
                foreach( $scores as $score ) {
                    //Record and save scores
                    $chairUmpire->recordScores( $match, $score );
                }

                if( empty($match->getMatchDate_Str()) ) {
                    $match->setMatchDate_Str( date("Y-m-d G:i:s") );
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

                $data['advanced'] = 0; //$td->advance( $bracketName );
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
            $data['advanced'] = $advanced; //Could be the name of the champion!!!
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
     * Exchanges players between 2 matches.
     * @param array $data
     * @return string Message about failure or success/
     *                $data is also modified to return data to the client/browser
     */
    private function swapPlayers( &$data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $data, "$loc" );

        $this->eventId = $data["eventId"];
        $bracketName   = $data["bracketName"];
        $sourceRn = (int)$data["sourceRn"];//not used
        $sourceMn = (int)$data["sourceMn"];
        $targetMn = (int)$data["targetMn"];
        $mess = __("Failed to swap players!", TennisEvents::TEXT_DOMAIN );

        try {            
            $event = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );

            $bracket = $td->getBracket( $bracketName );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $bracketNum = $bracket->getBracketNumber();
            //Swap players
            $matchesAffected = $bracket->swapPlayers( $sourceMn, $targetMn );
            if( !empty( $matchesAffected ) ) { 
                $mess = __("Swapped players between Match# {$matchesAffected["source"]["matchNum"]} and Match# {$matchesAffected["target"]["matchNum"]}", TennisEvents::TEXT_DOMAIN );
                $td->save();
                $data["bracketNum"] = $bracketNum;
                $data["swap"] = $matchesAffected;
            }
            else {
                throw new InvalidTennisOperationException($mess);
            }
        }
        catch( Exception | InvalidBracketException | InvalidTennisOperationException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
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
                    $status = $chairUmpire->matchStatusEx( $match )->toString();
                    $data['advanced'] = $td->advance( $bracketName );
                    break;
                case "visitor":
                    $chairUmpire->defaultVisitor( $match, $comments );
                    $status = $chairUmpire->matchStatusEx( $match )->toString();
                    $data['advanced'] = $td->advance( $bracketName );
                    break;
                default:
                    $mess  = __("Unable to default '$player'", TennisEvents::TEXT_DOMAIN );
                    throw new InvalidArgumentException($mess);
                    break;
            }
            $data['status'] = $status;
        }
        catch( Exception | InvalidBracketException | InvalidMatchException | InvalidArgumentException $ex ) {
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
        catch( Exception | InvalidMatchException | InvalidBracketException $ex ) {
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
            $chairUmpire = $td->getChairUmpire();
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $match = $bracket->getMatch( $roundNum, $matchNum );
            if( is_null( $match ) ) {
                throw new InvalidMatchException(__("No such match", TennisEvents::TEXT_DOMAIN) );
            }
            $match->setMatchDate_Str( $matchStartDate );
            $match->setMatchTime_Str( $matchStartTime );
            $match->save();
            $data['matchdate'] = $match->getMatchDate_Str();
            $data['matchtime'] = $match->getMatchTime_Str(2);
            $data['status'] = $chairUmpire->matchStatusEx( $match )->toString();
            $mess = __("Set Start Match Date to '{$data['matchdate']}' and Time to '{$data['matchtime']}'.", TennisEvents::TEXT_DOMAIN );
        }
        catch( Exception | InvalidMatchException | InvalidBracketException  $ex ) {
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
            $status = $chairUmpire->matchStatusEx( $match )->toString();
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