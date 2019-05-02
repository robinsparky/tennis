<?php

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
        add_action('wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
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
        $this->log->error_log($eventId, "$loc: EventId");
        if( $eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $evts = Event::find( array( "club" => $club->getID() ) );
        
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $this->getEventRecursively( $evt, $eventId );
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
            return $this->renderBracket( $td, $bracket );
        }

        $brackets = $td->getBrackets();
        foreach( $brackets as $bracket ) {
            $this->renderBracket( $td, $bracket );
        }

    }

    private function renderBracket( TournamentDirector $td, Bracket $bracket ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $tournamentName = $td->getName();
        $bracketName    = $bracket->getName();
        if( !$bracket->isApproved() ) {
            return __("'$tournamentName ($bracketName bracket)' has not been approved", TennisEvents::TEXT_DOMAIN );
        }

        $loadedTemplate = $td->loadMatches( $bracketName );
        if( count( $loadedTemplate ) < 1 ) {
            //TODO: This will never happen!
            return __("'$tournamentName ($bracketName bracket)' has not been scheduled yet", TennisEvents::TEXT_DOMAIN );
        }
        //$this->log->error_log($loadedTemplate, "$loc: Loaded Template");

        // $matches = $td->getMatches();
        // $umpire  = $td->getChairUmpire();
        $preliminaryRound = $bracket->extractPreliminaryRound();
        $numPreliminaryMatches = $preliminaryRound->count();
        $numRounds = $td->totalRounds( $bracketName );
        $actualNumRounds = $bracket->getNumberOfScheduledRounds();
        if( $numRounds != $actualNumRounds ) {
            return __("'$tournamentName ($bracketName bracket)' expected rounds=$numRounds but actual rounds=$actualNumRounds", TennisEvents::TEXT_DOMAIN );
        }

        $signupSize = $bracket->signupSize();
        $this->log->error_log("$loc: number prelims=$numPreliminaryMatches; number rounds=$numRounds; signup size=$signupSize");


        $begin = <<<EOT
<table id="%s" class="bracketdraw">
<caption>%s: %s Bracket</caption>
<thead><tr>
EOT;
        $out = sprintf( $begin, $bracketName, $tournamentName, $bracketName );

        for( $i=0; $i < $numRounds; $i++ ) {
            $out .= sprintf( "<th>Round %d</th>", $i );
        }
        $out .= "<th>Champion</th>";
        $out .= "</tr></thead>" . PHP_EOL;

        $out .= "<tbody>" . PHP_EOL;

        $templ = <<<EOT
<td class="item-player" rowspan="%d">
<div>%s</div><div>%s</div><div>%s</div>
<div>%s</div><div>%s</div>
</td>
EOT;

        $rowEnder = "</tr>" . PHP_EOL;
        $this->log->error_log( $preliminaryRound,"$loc: Preliminary Round" );

        //rows
        $row = 0;
        $preliminaryRound->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
        for( $preliminaryRound->rewind(); $preliminaryRound->valid(); $preliminaryRound->next() ) {
            ++$row;
            $out .= "<tr>";
            $r = 1; //means preliminary round (i.e. first column)
            $nextRow = '';
            try {
                $rowObj = $preliminaryRound->shift(); //throws RuntimeException
                $id = sprintf("M(%d,%d)",$rowObj->round, $rowObj->match_num);
                $visitor = $rowObj->is_bye ? 'Bye' : $rowObj->visitor;  
                $out .= sprintf( $templ, $r, $id, $rowObj->home, $rowObj->score, $visitor, 'yyy' );
                //following columns
                //$this->log->error_log($rowObj,"$loc: rowObj");
                //$remaining = $loadedTemplate;
                $this->log->error_log($rowObj,"$loc: rowObj");
                $nextMatches = $bracket->getBracketTemplate()->getFollowingMatches( $rowObj->round, $rowObj->match_num );
                $this->log->error_log( $nextMatches, "$loc: nextMatches" );
                foreach( $nextMatches as $colObj ) {
                    $rowspan = pow( 2, $r++ );
                    $id = sprintf("M(%d,%d)",$colObj->round, $colObj->match_num);  
                    $home = isset($colObj->home) ? $colObj->home : 'unknown home'; 
                    $score = isset($colObj->score) ? $colObj->score : 'xxx';
                    $visitor = isset($colObj->visitor) ? $colObj->visitor : 'unknown visitor';
                    $out .= sprintf($templ, $rowspan, $id, $home, $score, $visitor, 'yyy');
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
        $this->log->error_log( $out, "$loc: Table Markup" );
        return $out;

    }

    /**
     * Recursive function to extract all following matches from the given match
     * @param $startObj The starting match in stdClass form
     * @param $rounds Reference to an array of splDoublyLinkedList reprsenting all matches beyond the priliminary one
     * @return array of match objects
     */
    private function getNextMatches( $startObj, array &$rounds ) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $found = array();
        $nr = isset($startObj->next_round_num) ? $startObj->next_round_num : -1;
        $nm = isset($startObj->next_match_num) ? $startObj->next_match_num : -1;

        foreach( $rounds as $round ) {
            //$dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $i = 0; $i < $round->count(); $i++ ) {
                if( !$round->offsetExists( $i ) ) continue;
                $obj = $round->offsetGet( $i );   
                $r = isset( $obj->round ) ? $obj->round : -1;
                $m = isset( $obj->match_num ) ? $obj->match_num : -1;
                if( $r == $nr && $m == $nm ) {
                    $found[] = $obj;
                    $round->offsetUnset( $i );
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
    
}
