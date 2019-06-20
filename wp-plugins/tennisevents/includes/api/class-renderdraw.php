<?php
use templates\DrawTemplateGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Rendering a draw is implemented using shortcodes
 * @class  RenderDraw
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderDraw
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    private $ajax_nonce = null;
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
    }
    
    public function registerHandlers( ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( 'render_draw', array( $this, 'renderDrawShortcode' ) );
    }

	public function renderDrawShortcode( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

        // if( !is_user_logged_in() ) {
        //     return '';
        // }

        $my_atts = shortcode_atts( array(
            'clubname' => '',
            'eventid' => 0,
            'by' => 'table',
            'bracketname' => Bracket::WINNERS
        ), $atts, 'render_draw' );

        $this->log->error_log($my_atts, "$loc: My Atts" );

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

        $eventId = $my_atts['eventid'];
        $this->log->error_log("$loc: EventId=$eventId");
        if( $eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $by = $my_atts['by'];
        $this->log->error_log("$loc: by=$by");
        if( !in_array( $by, ['table','list']) )  return __('Please specify how to render the draw in shortcode', TennisEvents::TEXT_DOMAIN );

        $evts = Event::find( array( "club" => $club->getID() ) );
        
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

        if( !$found ) return __('No such event for this club', TennisEvents::TEXT_DOMAIN );

        $td = new TournamentDirector( $target,  $target->getMatchType() );

        $bracketName = $my_atts['bracketname'];

        $bracket = !empty( $bracketName ) ? $td->getBracket( $bracketName ) : null;
        if( !is_null( $bracket ) ) {
            switch($by) {
                case 'table':
                    return $this->renderBracketByTable( $td, $bracket );
                case 'list':
                    return $this->renderBracketByList( $td, $bracket );
                default:
                    return  __("Whoops!", TennisEvents::TEXT_DOMAIN );
            }
        }
        else {
            return  __("No such Bracket $bracketName", TennisEvents::TEXT_DOMAIN );
        }
    }

    
    /**
     * Renders rounds and matches for the given brackete
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return HTML for table-based page showing the draw
     */
    private function renderBracketByTable( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startTime = microtime( true );

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        if( !$bracket->isApproved() ) {
            return __("'$tournamentName ($bracketName bracket)' has not been approved", TennisEvents::TEXT_DOMAIN );
        }

        $umpire = $td->getChairUmpire();

        $loadedMatches = $bracket->getMatchHierarchy();
        $preliminaryRound = $loadedMatches[1];                
        $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");


        $begin = <<<EOT
<table id="%s" class="bracketdraw">
<caption>%s: %s Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $bracketName, $tournamentName, $bracketName );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;

        $templ = <<<EOT
<td class="item-player" rowspan="%d">
<div class="matchtitle">%s</div><div class="homeentrant">%s</div><div class="matchscore">%s</div>
<div class="visitorentrant">%s</div><div class="matchstatus">%s</div><div class="matchcomments">%s</div>
</td>
EOT;

        $rowEnder = "</tr>" . PHP_EOL;
        // $this->log->error_log( $preliminaryRound,"$loc: Preliminary Round" );

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

                $winner  = $umpire->matchWinner( $match );
                $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

                $visitor = $match->getVisitorEntrant();
                $vname   = 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = $visitor->getName();
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }
                $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                $cmts = $match->getComments();
                $cmts = isset( $cmts ) ? $cmts : '';
                $score   = $umpire->tableGetScores( $match );                        
                $status  = $umpire->matchStatus( $match );

                $out .= sprintf( $templ, $r, $match->toString(), $hname, $score, $vname, $status, $cmts );

                $futureMatches = $this->getFutureMatches( $match->getNextRoundNumber(), $match->getNextMatchNumber(), $loadedMatches );
                foreach( $futureMatches as $futureMatch ) {
                    $rowspan = pow( 2, $r++ );
                    $winner  = $umpire->matchWinner( $futureMatch );
                    $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                    $home    = $futureMatch->getHomeEntrant();
                    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";
    
                    $visitor = $futureMatch->getVisitorEntrant();
                    $vname   = 'tba';
                    $vseed   = '';
                    if( isset( $visitor ) ) {
                        $vname   = $visitor->getName();
                        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                    }
                    $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                    $cmts = $futureMatch->getComments();
                    $cmts = isset( $cmts ) ? $cmts : '';

                    $score   = $umpire->tableGetScores( $futureMatch );                        
                    $status  = $umpire->matchStatus( $futureMatch );
                    $out .= sprintf( $templ, $rowspan, $futureMatch->toString(), $hname, $score, $vname, $status, $cmts );
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
		$this->log->error_log( sprintf("%0.6f",micro_time_elapsed( $startTime ) ), $loc . ": Elapsed Micro Elapsed Time");
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
     * Get a child event from its ancestor
     * @param $evt The ancestor Event
     * @param $descendantId The id of the descendant event
     * @return Event which is the child or null if not found
     */
    private function getEventRecursively( Event $evt, int $descendantId ) {
        static $attempts = 0;
        if( $descendantId === $evt->getID() ) return $evt;

        if( count( $evt->getChildEvents() ) > 0 ) {
            if( ++$attempts > 10 ) return null;
            foreach( $evt->getChildEvents() as $child ) {
                if( $descendantId === $child->getID() ) {
                    return $child;
                }
                else { 
                    return $this->getEventRecursively( $child, $descendantId );
                }
            }
        }
        else {
            return null;
        }
    }
    
    /**TODO: Remove this function
     * Renders draw showing entrants for the given bracket
     * @param $td The tournament director for this bracket
     * @param $bracket The bracket
     * @return HTML for table-based page showing the draw
     */
    private function renderBracketByEntrant( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );


        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        if( !$bracket->isApproved() ) {
            return __("'$tournamentName ($bracketName bracket)' has not been approved", TennisEvents::TEXT_DOMAIN );
        }

        $loadedMatches = $bracket->getMatchHierarchy();
        $preliminaryRound = $loadedMatches[1];                
        $numPreliminaryMatches = count( $preliminaryRound );
        $numRounds = $td->totalRounds( $bracketName );

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");

        $umpire = $td->getChairUmpire();

        $begin = <<<EOT
<table id="%s" class="bracketdraw">
<caption>%s: %s Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $bracketName, $tournamentName, $bracketName );

        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $bracket->roundOf( $i );
            $out .= sprintf( "<th>Round Of %d</th>", $rOf );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;



        $gen = new DrawTemplateGenerator("$tournamentName - $bracketName", $signupSize );
        $template = $gen->generateTable();
        $includeMatrix = $gen->getIncludeMatrix();
        $players = $this->expandMatchesToPlayers( $umpire, $loadedMatches );
        
        for( $row = 1; $row <= $gen->getRows(); $row++ ) {
            $out .= "<tr id='row$row'>";
            for( $col = 1; $col <= $gen->getColumns(); $col++ ) {
                $rowspan = pow( 2, $col - 1 );
                if(array_key_exists($row, $players) && array_key_exists($col, $players[$row])) {
                    $entrantName = $players[$row][$col];
                }
                else {
                    $entrantName = "unknown";
                }
                //if( !is_null(error_get_last()) ) continue;

                if(  $includeMatrix[$row][$col] == 1 ) {
                    $out .= "<td rowspan='$rowspan'>($row,$col)$entrantName</td>";
                }
            }
            $out .= "</tr>" . PHP_EOL;
        }

        $out .= "</tbody><tfooter></tfooter>";
        $out .= "</table>";
        return $out;

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
    
}
