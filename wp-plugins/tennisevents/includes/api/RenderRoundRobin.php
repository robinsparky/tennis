<?php
namespace api;
use templates\DrawTemplateGenerator;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_Error;
use \TennisEvents;
use api\TournamentDirector;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Club;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Renders a Round Robin draw using shortcode
 * Is is also invoked by the tennis events templates
 * Shows the status
 *       the players (home and visitor)
 *       the start date 
 *       the score by games within set
 *       any comments about a match
 *       the champion when the tournament is completed
 * This class also renders a summary of all matches with points earned for wins and ties
 * Identifies the winner depending on the scoring rules for the elimination draw
 * @class  RenderRoundRobin
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderRoundRobin
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
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        $jsurl =  TE()->getPluginUrl() . 'js/matches.js';
        wp_register_script( 'manage_rr', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        
        $cturl =  TE()->getPluginUrl() . 'js/digitalclock.js';
        wp_register_script( 'digital_clock', $cturl, array('jquery'), TennisEvents::VERSION, true );
        
        $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
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
        unset( $evts );

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

        return $this->renderBracketByMatch( $td, $bracket );
    }
    
    /**
     * Renders rounds and matches for the given bracket
     * @param TournamentDirector $td The tournament director for this bracket
     * @param Bracket $bracket The bracket
     * @return string HTML for table-based page showing the round robin draw
     */
    private function renderBracketByMatch( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        GW_Debug::gw_print_mem();
        
		$startFuncTime = microtime( true );

        $tournamentName = str_replace("\'","&apos;", $td->getName() );
        $parentName = str_replace("\'","&apos;", $td->getParentEventName() );
        $bracketName    = $bracket->getName();
        // if( !$bracket->isApproved() ) {
        //     return __("'$tournamentName ($bracketName bracket)' has not been approved", TennisEvents::TEXT_DOMAIN );
        // }

        $umpire = $td->getChairUmpire();
        $scoreType = $td->getEvent()->getScoreType();
        $scoreRuleDesc = $td->getEvent()->getScoreRuleDescription();

        $loadedMatches = $bracket->getMatchHierarchy();
        $numRounds = 0;
        $numMatches = 0;
        foreach( $loadedMatches as $r => $m ) {
            if( $r > $numRounds ) $numRounds = $r;
            foreach( $m as $match ) {
                ++$numMatches;
            }
        }

        $pointsPerWin = 1;
        ///if( $td->getEvent()->getFormat() === Format::POINTS2 ) $pointsPerWin = 2;
        $summaryTable = $umpire->getEntrantSummary( $bracket );
        $bracketSummary = $umpire->getBracketSummary( $bracket ); //NOTE: calls $bracket->getMatchHierarchy();

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
        $this->log->error_log($arrData, "$loc: arrData...");
        $jsData["matches"] = $arrData; 
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'manage_rr' );         
        wp_localize_script( 'manage_rr', 'tennis_draw_obj', $jsData );        
        
	    // Start output buffering we don't output to the page
        ob_start();

        // Get template file to render the round robin matches
        $path = TE()->getPluginPath() . 'includes\templates\render-roundrobin-grid.php';
        $path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
        require $path;
        
        //Render the score summary
        $path = TE()->getPluginPath() . 'includes\templates\summaryscore-template.php';
        $path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
        require $path;

        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    /**
     * Get the Draw's match data as array
     * Needed in order to serialize for json
     */
    private function getMatchesAsArray( TournamentDirector $td,  Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $matches = $bracket->getMatches();
        $chairUmpire = $td->getChairUmpire();

        $arrMatches = [];
        foreach( $matches as $match ) {
            $arrMatch = $match->toArray();

            $status = $chairUmpire->matchStatusEx( $match );
            $arrMatch["status"] = $status;

            $strScores = $chairUmpire->strGetScores( $match );
            $arrMatch["scores"] = $strScores;

            extract( $chairUmpire->getMatchSummary( $match ) );
            
            switch( $andTheWinnerIs ) {
                case 'home':
                    $winner = $match->getHomeEntrant()->getName();
                    break;
                case 'visitor':
                    $winner = $match->getVisitorEntrant()->getName();
                    break;
                case 'tie':
                    $winner = 'tie';
                    break;
                default:
                    $winner = '';
            }

            $arrMatch["winner"] = $winner;
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
}