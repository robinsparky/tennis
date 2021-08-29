<?php
namespace api;
use api\TournamentDirector;
use templates\DrawTemplateGenerator;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_Error;
use \TennisEvents;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Club;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Renders elimination rounds of matches
 * Uses shortcode so the rendering can be placed anywhere on a WP page
 * Is is also invoked by the tennis events templates
 * Shows the status
 *       the players (home and visitor)
 *       the start date 
 *       the score by games within set
 *       any comments about a match
 *       the champion when the tournament is completed
 * Identifies the winner depending on the scoring rules for the elimination draw
 * @class  RenderDraw
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderDraw
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
     * Register css and javascript scripts.
     * The javavscript calls methods in ManageDraw via ajax
     */
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
    
    /**
     * The shortcode method is added to WP's shortcodes handler
     */
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
    }
     
    /**
     * Renders the html and data for the elimination rounds
     * Decides based on the privileges of the user whether 
     * to render with menus and buttons or without (i.e. readonly)
     */
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
     * Renders rounds and matches for the given bracket
     * in write mode so matches can be modified and scores updated
     * @param TournamentDirecotr $td The tournament director for this bracket
     * @param Bracket $bracket The bracket to be rendered
     * @return string Table-based HTML showing the draw
     */
    private function renderBracketForWrite( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );

        $winnerClass = "matchwinner";

        $tournamentName = str_replace("\'","'",$td->getName());
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

        $scoreType     = $td->getEvent()->getScoreType();
        $scoreRuleDesc = $td->getEvent()->getScoreRuleDescription();
        $parentName    = $td->getParentEventName();

        $jsData = $this->get_ajax_data();
        $jsData["clubId"]  = $td->getClubId();
        $jsData["eventId"] = $this->eventId = $td->getEventId();
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $signupSize;
        $jsData["numPreliminary"] = $numPreliminaryMatches;
        $jsData["isBracketApproved"] = $bracket->isApproved() ? 1 : 0;
        $jsData["numSets"] = $umpire->getMaxSets();
        $arrData = $this->getMatchesAsArray( $td, $bracket );
        $jsData["matches"] = $arrData;
        wp_enqueue_script( 'manage_matches' );         
        wp_localize_script( 'manage_matches', 'tennis_draw_obj', $jsData );        

        $begin = <<<EOT
<h2 id="parent-event-name">%s</h2>
<table id="%s" class="managedraw" data-eventid="%d" data-bracketname="%s">
<caption class='tennis-draw-caption'>%s&#58;&nbsp;%s&nbsp;(%s)</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $parentName, $bracketName, $this->eventId, $bracketName, $tournamentName, $bracketName, $scoreRuleDesc );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        if( $bracket->isApproved() ) {
            $out .= "<tbody>" . PHP_EOL;
        }
        else {
            $out .= "<tbody class='prelimOnly'>" . PHP_EOL;
        }

        $templ = <<<EOT
<td class="item-player sortable-container ui-state-default" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d"  data-majorstatus="%d"  data-minorstatus="%d">
<div class="menu-icon">
<svg class="dots" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true">
<path d="M8 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM1.5 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm13 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z">
</path>
</svg>

<!-- <ul class="matchaction unapproved">
 <li><a class="changehome">Replace Home</a></li>
 <li><a class="changevisitor">Replace Visitor</a><li></ul> -->
<ul class="matchaction approved">
 <li><a class="recordscore">Enter Score</a></li>
 <li><a class="defaulthome">Default Home</a></li>
 <li><a class="defaultvisitor">Default Visitor</a></li>
 <li><a class="setmatchstart">Start Date &amp; Time</a></li>
 <li><a class="setcomments">Comment Match</a></li></ul>
</div>
<div class="matchinfo matchtitle ui-sortable-handle">%s</div>
<div class="matchinfo matchstatus">%s</div>
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
            if( $bracket->isApproved() ) {
                $out .= "<tr>";
            }
            else {
                $out .= "<tr data-currentpos={$row} class='drawRow ui-state-default'>";
            }

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
            else {
                $out .= '<button class="button" type="button" id="advanceMatches">Advance Matches</button>' . PHP_EOL;
            }
            $out .= '<button class="button" type="button" id="removePrelim">Reset Bracket</button>&nbsp;' . PHP_EOL;
        }

        $out .= "</div>";

        $out .= '<div id="tennis-event-message"></div>';
		$this->log->error_log( sprintf("%0.6f", GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Time");
        return $out;
    }
    
    /**
     * Renders rounds and matches for the given bracket
     * in read-only mode
     * @param TournamentDirector $td The tournament director for this bracket
     * @param Bracket $bracket The bracket to be rendered
     * @return string Table-based HTML presenting the elimination draw
     */
    private function renderBracketForRead( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );

        $winnerClass = "matchwinner";

        $tournamentName = str_replace("\'","'",$td->getName());
        $bracketName    = $bracket->getName();
        $champion = $td->getChampion( $bracketName );
        $championName = empty( $champion ) ? 'tba' : $champion->getName();
        $umpire = $td->getChairUmpire();
        $parentName = $td->getParentEventName();

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
<h2 id="parent-event-name">%s</h2>
<table id="%s" class="managedraw" data-eventid="%d" data-bracketname="%s">
<caption class='tennis-draw-caption'>%s&#58;&nbsp;%s&nbsp;Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $parentName, $bracketName, $this->eventId, $bracketName, $tournamentName, $bracketName );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;

        $templ = <<<EOT
<td class="item-player" rowspan="%d" data-eventid="%d" data-bracketnum="%d" data-roundnum="%d" data-matchnum="%d" data-majorstatus="%d" data-minorstatus="%d">
<div class="matchinfo matchtitle">%s</div>
<div class="matchinfo matchstatus">%s</div>
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
            $r = 1; //means preliminary round (i.e. first column)
            $out .= "<tr>";
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
		$this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Time");
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

    /** TODO: Remove this and associated code
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