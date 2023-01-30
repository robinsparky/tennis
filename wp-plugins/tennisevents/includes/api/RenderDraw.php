<?php
namespace api;
use api\TournamentDirector;
use templates\DrawTemplateGenerator;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_Error;
use \TennisEvents;
use \TE_Install;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Club;
use datalayer\InvalidMatchException;
use datalayer\MatchStatus;
use InvalidArgumentException;

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
                      
        //Digital clock
        $cturl =  TE()->getPluginUrl() . 'js/digitalclock.js';
        wp_register_script( 'digital_clock', $cturl, array('jquery'), TennisEvents::VERSION, true );
       
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
        $target = new Event();
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
        $bracketName = urldecode($my_atts["bracketname"]);

        //Go
        $td = new TournamentDirector( $target );
        $bracket = $td->getBracket( $bracketName );
        if( is_null( $bracket ) ) {            
            $mess = sprintf("No such bracket='%s' for the event '%s'", $bracketName, $target->getName() );
            return __($mess, TennisEvents::TEXT_DOMAIN );
        }

        if( !is_null( $bracket ) ) {
            return $this->renderBracket( $td, $bracket );
        }
        else {
            return  __("No such Bracket {$bracketName}", TennisEvents::TEXT_DOMAIN );
        }
    }
       
    /**
     * Renders rounds and matches for the given bracket
     * in write mode so matches can be modified and scores updated
     * @param TournamentDirector $td The tournament director for this bracket
     * @param Bracket $bracket The bracket to be rendered
     * @return string Table-based HTML showing the draw
     */
    private function renderBracket( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );
        $winnerClass = "matchwinner";

        $eventId = $td->getEvent()->getID(); //$this->eventId;
        $tournamentName = str_replace("\'","'",$td->getName());
        $bracketName    = $bracket->getName();
        $champion = $td->getChampion( $bracketName )[ChairUmpire::CHAMPIONNAME];
        $championScore = $td->getChampion( $bracketName )[ChairUmpire::CHAMPSCORE];
        $championName = empty( $champion ) ? 'tba' : $champion->getName();
        $umpire = $td->getChairUmpire();

        $loadedMatches = $bracket->getMatchHierarchy( );
        $preliminaryRound = count( $loadedMatches ) > 0 ? $loadedMatches[1] : array();                
        $numPreliminaryMatches = count( $preliminaryRound );
        $totalRounds = $td->totalRounds( $bracketName );
        $numMatches = $bracket->getNumberOfMatches();

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: num matches:$numMatches; number prelims=$numPreliminaryMatches; number rounds=$totalRounds; signup size=$signupSize");

        $scoreType     = $td->getEvent()->getScoreType();
        $scoreRuleDesc = $td->getEvent()->getScoreRuleDescription();
        $parentName    = $td->getParentEventName();
        //$now = (new \DateTime('now', wp_timezone() ))->format("Y-m-d g:i a");

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
        wp_enqueue_script( 'digital_clock' );         
        wp_localize_script( 'manage_matches', 'tennis_draw_obj', $jsData );        

        // Get template file
        if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) 
        || current_user_can( TE_Install::RESET_MATCHES_CAP )
        || current_user_can( TE_Install::SCORE_MATCHES_CAP ) ) {
            $path = TE()->getPluginPath() . 'includes\templates\render-draw-template.php';
        } else {
            $path = TE()->getPluginPath() . 'includes\templates\render-draw-template-grid.php';
        }
        $path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );

	    // Start output buffering 
        ob_start();
        require( $path );
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
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

    /**
     * Get all matches emanating from the given match (round number, match number)
     * @param $nr the round number
     * @param $nm the match number
     * @param &$matchHierarchy the total match hierarchy
     * @return array of matches
     */
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

    private function getMenuPath( int $majorStatus ) {
        $menupath = '';
        
        if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) 
        || current_user_can( TE_Install::RESET_MATCHES_CAP )
        || current_user_can( TE_Install::SCORE_MATCHES_CAP ) ) {
            switch( $majorStatus ) {
                case MatchStatus::NotStarted:
                case MatchStatus::InProgress:
                case MatchStatus::Completed:
                case MatchStatus::Retired:
                    $menupath = TE()->getPluginPath() . 'includes\templates\menus\elimination-menu-template.php';
                    $menupath = str_replace( '\\', DIRECTORY_SEPARATOR, $menupath );
                    break;
                case MatchStatus::Bye:
                case MatchStatus::Waiting:
                case MatchStatus::Cancelled:
                default:
                    $menupath = '';
            }
        }
        return $menupath;
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
                $status  = $umpire->matchStatusEx( $match )->toString();
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

    /**
     * Returns the appropriate grid parameters for the given match
     * @param $roundNum the round number
     * @param $matchNum the match number
     * @return string containing the grid style i.e. gridRow, gridColumn and line numbers
     */
    private function getGridStyle($roundNum, $matchNum) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}({$roundNum},{$matchNum})");

        $gridRow = 1;
        $span = 1;
        $inc = 1;
        $style = '';
        $pat = "grid-column: %d; grid-row: %d/span %d;";
        switch($roundNum) {
            case 1:
                return "grid-column: 1;"; //early return
            case 2:
                $span = 1;
                $inc = 2 * $span;
                $gridRow = 1 + $inc * ($matchNum - 1);
                $pat .= " top: calc(.5 * (var(--matchHeight) + var(--rowGap)));";
                break;
            case 3: 
                $span = 2;
                $inc = 2 * $span;
                $gridRow = 2 + $inc * ($matchNum - 1);
                break;
            case 4:
                $span = 4;
                $inc = 2 * $span;
                $gridRow = 3 + $inc * ($matchNum - 1);
                break;
            case 5:
                $span = 8;
                $inc = 2 * $span;
                $gridRow = 4 + $inc * ($matchNum - 1);
                break;
            case 6:
                $span = 16;
                $inc = 2 * $span;
                $gridRow = 5 + $inc * ($matchNum - 1);
                break;
            case 7:
                $span = 32;
                $inc = 2 * $span;
                $gridRow = 6 + $inc * ($matchNum - 1);
                break;
            default:
                $mess = __("Invalid round number!", TennisEvents::TEXT_DOMAIN);
                $this->log->error_log($mess);
                throw new InvalidMatchException($mess);
        }

        $style = sprintf($pat, $roundNum, $gridRow, $span);
        $this->log->error_log("$loc: returning style: '{$style}'");

        return $style;

        /*

        $col2 = ["grid-column: 2; grid-row: 1/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 3/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 5/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 7/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 9/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 11/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 13/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
                ,"grid-column: 2; grid-row: 15/span 1; top: calc(.5 * (var(--matchHeight) + var(--rowGap)));"
        ];

        $col3 = ["grid-column: 3; grid-row: 2/span 2;"
                ,"grid-column: 3; grid-row: 6/span 2;"
                ,"grid-column: 3; grid-row: 10/span 2;"
                ,"grid-column: 3; grid-row: 14/span 2;"
        ];

        $col4 = ["grid-column: 4; grid-row: 3/span 4;"
                ,"grid-column: 4; grid-row: 11/span 4;"
                ,"grid-column: 4; grid-row: 19/span 4;"
                ,"grid-column: 4; grid-row: 27/span 4;"
                ,"grid-column: 4; grid-row: 35/span 4;"
                ,"grid-column: 4; grid-row: 43/span 4;"
                ,"grid-column: 4; grid-row: 51/span 4;"
                ,"grid-column: 4; grid-row: 59/span 4;"
        ];

        $col5 = ["grid-column: 5; grid-row: 4/span 8;"
                ,"grid-column: 5; grid-row: 20/span 8;"
                ,"grid-column: 5; grid-row: 36/span 8;"
                ,"grid-column: 5; grid-row: 52/span 8;"
        ];

        $col6 = ["grid-column: 6; grid-row: 5/span 16;"
                ,"grid-column: 6; grid-row: 37/span 16;"

        ];

        $col7 = ["grid-column: 7; grid-row: 6/span 32;"
        ];

        $allRowsCols = [[],$col2, $col3, $col4, $col5, $col6, $col7];

        return $allRowsCols[$roundNum[$matchNum]];
        */

    }
}