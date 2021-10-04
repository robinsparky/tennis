<?php
namespace commandline;

use \WP_CLI;
use \WP_CLI_Command;

use api\TournamentDirector;
use api\ChairUmpire;

use commonlib\GW_Support;
use commonlib\GW_Debug;
use commonlib\Math_Combinatorics;

use datalayer\Club;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Match;
use datalayer\Entrant;

use api\events\EventManager;
use api\events\OverloadedConstructors;
use templates\DrawTemplateGenerator;

WP_CLI::add_command( 'tennis tourney', 'commandline\TournamentCommands' );

/**
 * Implements all commands for manipulating a tennis tournament and its brackets' matches
 * Tennis tournaments are identified by a tennis club and an event at that club
 */
class TournamentCommands extends WP_CLI_Command {

    /**
     * Shows All Brackets and their Matches for a Tournament
     *
     * ## OPTIONS
     * 
     * [--clubId=<value>]
     * If present identifies the club
     * 
     * [--eventId=<value>]
     * If present identifies the event
     * 
     * ## EXAMPLES
     *
     *     # Show matches for club 2, event 6.
     *     $ wp tennis tourney show --clubId=2 --eventId=6
     * 
     *     # Show matches for club and event defined in the tennis command environment.
     *     $ wp tennis tourney show
     *
     * @when after_wp_load
     */
    function show( $args, $assoc_args ) {
        $clubId  = array_key_exists( 'clubId', $assoc_args )  ? $assoc_args["clubId"] : 0;
        $eventId = array_key_exists( 'eventId', $assoc_args ) ? $assoc_args["eventId"] : 0;

        $support = CmdlineSupport::preCondtion();
        if( 0 === $clubId || 0 === $eventId ) {
            list( $clubId, $eventId ) = $support->getEnvError();
        }

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target =  $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                $brackets = $td->getBrackets();
                $umpire  = $td->getChairUmpire();
                foreach( $brackets as $bracket ) {
                    WP_CLI::line( '-');
                    WP_CLI::line( sprintf( "Matches for '%s' at '%s'", $evtName, $name ) );
                    $numRounds = $td->totalRounds();
                    WP_CLI::line( sprintf( "%s Bracket: %d Rounds", $bracket->getName(), $numRounds ) );
                    $matches = $bracket->getMatches();
                    $items   = array();
                    foreach( $matches as $match ) {
                        $round   = $match->getRoundNumber();
                        $mn      = $match->getMatchNumber();
                        $status  = $umpire->matchStatusEx( $match )->toString();
                        $score   = $umpire->strGetScores( $match );
                        $winner  = $umpire->matchWinner( $match );
                        $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                        $home    = $match->getHomeEntrant();
                        $hname   = !is_null( $home ) ? sprintf( "%d %s", $home->getPosition(), $home->getName() ) : 'tba';
                        $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';

                        $visitor = $match->getVisitorEntrant();
                        $vname   = 'tba';
                        $vseed   = '';
                        if( isset( $visitor ) ) {
                            $vname   = sprintf( "%d %s", $visitor->getPosition(), $visitor->getName()  );
                            $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                        }

                        $cmts    = $match->getComments();
                        $cmts    = isset( $cmts ) ? $cmts : '';
                        $items[] = array( "Round" => $round
                                        , "Match Number" => $mn
                                        , "Status" => $status
                                        , "Score" => $score
                                        , "Home Name" => $hname
                                        , "Home Seed" => $hseed
                                        , "Visitor Name" => $vname
                                        , "Visitor Seed" => $vseed 
                                        , "Comments" => $cmts
                                        , "Winner" => $winner );
                    }
                    WP_CLI\Utils\format_items( 'table', $items, array( 'Round', 'Match Number', 'Status', 'Score', 'Home Name', 'Home Seed', 'Visitor Name', 'Visitor Seed', 'Comments', 'Winner' ) );
                }
                WP_CLI::success("Done!");
            }
            else {
                WP_CLI::warning( "tennis display tourney show ... could not event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis display tourney show ... could not any events for club with Id '$clubId'" );
        }
    }

    /**
     * Create the given bracket's preliminary rounds.
     * The Winner's bracket uses the current signup
     * while the Loser's or Consolation bracket uses the list of losers from 
     * the Winner's preliminary rounds
     * The target club and event must first be set using 'tennis env set'
     *
     * 
     * ## OPTIONS
     * <bracketName>
     * The name of the bracket to initialize
     * 
     * [--shuffle=<values>]
     * If present causes draw to be randomized
     * ---
     * default: no
     * options:
     *   - yes
     *   - no
     * ---
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney initialize Losers
     * 
     *  wp tennis tourney initialize Main --shuffle=yes
     *
     * @when after_wp_load
     */
    function initialize( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        //list( $bracketName ) = $args;

        $shuffle = array_key_exists("shuffle",$assoc_args) ? $assoc_args["shuffle"] : '';
        if( strcasecmp("yes", $shuffle) === 0 ) $shuffle = true;
        else $shuffle = false;

        $env = $support->getEnvError();
        list( $clubId, $eventId, $bracketName ) = $env;
        error_clear_last();
        if( !is_null( error_get_last() ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                try {
                    $numMatches = $td->schedulePreliminaryRounds( $bracketName, $shuffle );
                    if( $numMatches > 0 ) {
                        WP_CLI::success( "tennis tourney initialize ... generated $numMatches preliminary matches" );
                    }
                    else {
                        throw new Exception( "Failed to generate any matches." );
                    }
                }
                catch( Exception $ex ) { 
                    WP_CLI::error( sprintf( "tennis tourney initialize ... failed: %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis tourney initialize ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match initialize ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Delete all matches for this tennis tournament
     * The target club and event must first be set using 'tennis env'
     * 
     * ## OPTIONS
     
     * ## EXAMPLES
     *
     *  wp tennis tourney reset bracketName
     *
     * @when after_wp_load
     */
    function reset( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        $env = $support->getEnvError();
        error_clear_last();
        list( $clubId, $eventId, $bracketName ) = $env;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = is_null($club) ? 'unknown club' : $club->getName();
                $evtName = $target->getName();
                try {
                    $td = new TournamentDirector( $target );
                    if( $td->removeMatches( $bracketName ) ) {
                        WP_CLI::success("tennis tourney reset ... accomplished.");
                    }
                    else {
                        WP_CLI::error("tennis tourney reset ... unable to reset.");
                    }
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney reset for Club:'{$name}' - Event:'{$evtName}' ... failed: %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney reset ... could not find event with Id '$eventId' for club with Id '$clubId'/{$name}" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney reset ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Advance a bracket's matches to the next round if appropriate
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket containing the match
     * 
     * ## EXAMPLES
     * 
     * wp tennis tourney advance Main
     * 
     */
    function advance( $args, $assoc_args ) {
  
        $support = CmdlineSupport::preCondtion();

        error_clear_last();
        list( $clubId, $eventId, $bracketName ) = $support->getEnvError();
        if( !is_null( error_get_last() ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                $numAdvanced = $td->advance( $bracketName );
                if( $numAdvanced > 0 ) {
                    WP_CLI::success("$numAdvanced entrants advanced.");
                }
                else {
                    WP_CLI::warning("$numAdvanced entrants advanced for Club:'{$name}' - Event:{$evtName}'.");
                }
            }
            else {
                WP_CLI::warning( "tennis tourney advance ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis tourney advancd ... could not find any events for club with Id '$clubId'" );
        }

    }
    
    /**
     * Add or remove a commment to a match in the given draw.
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket containing the match
     * 
     * <round>
     * The round containing the match
     * 
     * <match>
     * The match number
     * 
     * --comment=<a comment>
     * 
     * ## EXAMPLES
     * 
     * To add/replace a comment (use double quotes)
     * wp tennis tourney comment losers 1 10 comments="This is a comment"
     * 
     * To remove a comment
     * wp tennis tourney comment losers 1 10
     *
     * @when after_wp_load
     */
    function comment( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $bracketName, $round, $matchnum ) = $args;
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong args for ... bracket round match ");
            exit;
        }
        $comments = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "";

        error_clear_last();
        list( $clubId, $eventId ) = $support->getEnvError();
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $matchId = "M($round,$matchnum)";
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                if( $td->comment( $bracketName, $round, $matchnum, $comments ) ) {
                    WP_CLI::success("Match commented.");
                }
                else {
                    WP_CLI::warning("Match not commented");
                }
            }
            else {
                WP_CLI::warning( "tennis tourney comment ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis tourney comment ... could not find any events for club with Id '$clubId'" );
        }

    }

    /**
     * Move a match to a given point in the draw.
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket containing the match
     * 
     * <round>
     * The round containing the match
     * 
     * <source>
     * The match number of the source match
     * 
     * <destination>
     * The destination match number
     * 
     * --comment=<a comment>
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney move winners 1 10 16
     * 
     *  wp tennis tourney move losers 1 10 16 comments='This is a comment'
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();
        list( $clubId, $eventId ) = $support->getEnvError();
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }

        error_clear_last();
        list( $bracketName, $round, $source, $dest ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... bracket round sourceMatch destMatch ");
            exit;
        }
        
        $fromId = "M($round,$source)";
        $toId   = "M($round,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved from $fromId to $toId on $stamp";

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                if( $td->matchMove($bracketName, $round, $source, $dest, $cmts ) ) {
                    WP_CLI::success("Match moved.");
                }
                else {
                    WP_CLI::warning("Match was not moved");
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }

    }

    /**
     * Move a match forward by given steps in the draw.
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <bracketName>
     * : The name of the bracket containing the match
     * 
     * <round>
     * The number of the round containing the match
     * 
     * <source>
     * The match number of the source match
     * 
     * <steps>
     * The number of places to advance match number
     *
     * --comments=<a comment>
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney moveby Main 0 1 2
     * 
     *  wp tennis tourney moveby losers 1 10 -3 --comments='This is a comment'
     *
     * @when after_wp_load
     */
    function moveby( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $clubId, $eventId ) = $support->getEnvError();
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }

        error_clear_last();
        list($bracketName, $round, $source, $steps ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... bracket round sourceMatch steps ");
            exit;
        }
        
        $result = 0;
        $dest   = $source + $steps;
        if( $steps > 256 || $dest < 1 ) {
            WP_CLI::error("Invalid step value");
        }

        $fromId = "Match($bracket,$round,$source)";
        $toId   = "Match($bracket,$round,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved up from $fromId by $steps to $toId on $stamp";

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                if( $td->matchMove($bracketName, $round, $source, $dest, $cmts ) ) {
                    WP_CLI::success("Match moved.");
                }
                else {
                    WP_CLI::warning("Match was not moved");
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }
    }
    
    /**
     * Approve the preliminary matches
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket to be approved
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney approve Main
     *
     * @when after_wp_load
     */
    function approve( $args, $assoc_args ) {

        list( $bracketName ) = $args;
        $bracketName = (is_null( $bracketName ) || empty($bracketName)) ? Bracket::WINNERS : $bracketName;

        $support = CmdlineSupport::preCondtion();
        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                //$bracket = $target->getBracket( $bracketName );
                $td = new TournamentDirector( $target );
                try {
                    $matchHierarchy = $td->approve( $bracketName );
                    WP_CLI::line(sprintf("Total Rounds=%d", $td->totalRounds()));
                    $ctr = 0;
                    foreach( $matchHierarchy as $round ) {
                        foreach( $round as $match ) {
                            $matchCtr = sprintf("%02d", ++$ctr);
                            WP_CLI::line(  $matchCtr . ' ' . str_repeat(".",$match->getRoundNumber()) . $match->title() );
                        }
                    }
                    WP_CLI::success("tennis tourney approve ... bracket $bracketName approved.");
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney approve ... failed:  %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis tourney approve ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis tourney approve ... could not find any events for club with Id '$clubId'" );
        }
    }
    
    /**
     * Review a bracket
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket to be approved
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney review Main
     *
     * @when after_wp_load
     */
    function review( $args, $assoc_args ) {

        list( $bracketName ) = $args;
        $bracketName = is_null( $bracketName ) || empty( $bracketName ) ? Bracket::WINNERS : $bracketName;

        $support = CmdlineSupport::preCondtion();
        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $bracket = $target->getBracket( $bracketName );
                $td = new TournamentDirector( $target );
                try {
                    if( is_null( $bracket ) ) {
                        throw new InvalidBracketException("No such bracket.");
                    }
                    $eventName = $td->getName();
                    $clubName  = $club->getName();
                    $signupSize = $bracket->signupSize();
                    $numRounds = $td->totalRounds();
                    $numByes = $bracket->getNumberOfByes();
                    $numMatches = $bracket->numMatches();
                    $isApproved = $bracket->isApproved();
                    $matchHierarchy = $bracket->getMatchHierarchy();
                    WP_CLI::line("Club: $clubName");
                    WP_CLI::line("Event: $eventName");
                    WP_CLI::line("Bracket: $bracketName");
                    WP_CLI::line("Signup: $signupSize");
                    WP_CLI::line("Number of Byes: $numByes");
                    WP_CLI::line("Is Approved: $isApproved");
                    WP_CLI::line("Number of Rounds=$numRounds");
                    WP_CLI::line("Number of Matches: $numMatches");
                    $ctr = 0;
                    foreach( $matchHierarchy as $round ) {
                        foreach( $round as $match ) {
                            $matchCtr = sprintf("%02d", ++$ctr);
                            WP_CLI::line(  $matchCtr . ' ' . str_repeat(".",$match->getRoundNumber()) . $match->title() );
                        }
                    }
                    WP_CLI::success("tennis tourney review ... bracket $bracketName reviewed.");
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney " . __FUNCTION__ . " ...  failed:  %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis tourney " . __FUNCTION__ . " ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis tourney " . __FUNCTION__ . " ... could not find any events for club with Id '$clubId'" );
        }
    }


    /**
     * Record the score for a match
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <bracketName>
     * The name of the bracket
     * 
     * <roundnum>
     * The round number
     * 
     * <matchnum>
     * The match number
     * 
     * <setnum>
     * The set number
     * 
     * --home=<value>
     * The home player's games won
     * 
     * --vistor=<value>
     * The vistor player's games won
     * 
     * [--hometb=<value>]
     * The home player's tie breaker points won
     * 
     * [--vistortb=<value>]
     * The vistor player's tie breaker points won
     * 
     * ## EXAMPLES
     *
     * wp tennis tourney score winners 1 3 2 --home=6 --visitor=6
     * 
     * wp tennis tourney score losers 1 3 2 --home=6 --hometb=7 --visitor=6 --visitortb=3
     *
     * @when after_wp_load
     */
    function score( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        if( !is_null( error_get_last()  ) ) {
            WP_CLI::error("Wrong env for ... clubId, eventId");
            exit;
        }

        error_clear_last();
        //Get the bracket, round, match and set numbers from the args
        list( $bracketName, $roundnum, $matchnum, $setnum ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... bracket round match set ");
            exit;
        }
        
        
        $home      = array_key_exists( 'home', $assoc_args )  ? $assoc_args["home"] : 0;
        $visitor   = array_key_exists( 'visitor', $assoc_args )  ? $assoc_args["visitor"] : 0;
        $hometb    = array_key_exists( 'hometb', $assoc_args )  ? $assoc_args["hometb"] : 0;
        $visitortb = array_key_exists( 'visitortb', $assoc_args )  ? $assoc_args["visitortb"] : 0;

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                $bracket = $td->getBracket( $bracketName );
                try {
                    $match = $bracket->getMatch( $roundnum, $matchnum );
                    if( !isset( $match ) ) {
                        throw new InvalidTournamentException("No such match.");
                    }

                    $umpire = $td->getChairUmpire();
                    if( $hometb < 1 && $visitortb < 1 ) {
                        $umpire->recordScores($match, $setnum, $home, $visitor );
                    }
                    else {
                        $umpire->recordScores($match, $setnum, $home, $hometb, $visitor, $visitortb );
                    }
                    WP_CLI::success( sprintf("tennis tourney score ... Recorded score %d(%d) : %d(%d) in match '%s'", $home, $hometb, $visitor, $visitortb, $match->title() ) );
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney " . __FUNCTION__ . " ... failed:  %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis tourney " . __FUNCTION__ . "... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis tourney " . __FUNCTION__ . " ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * simulate initial conditions (i.e. number of players) impact on the consequent bracket
     * 
     * ## OPTIONS
     * <n>
     * Number of players
     * 
     * ## EXAMPLES
     *
     * wp tennis tourney simulate 15 
     *
     * @when before_wp_load
     */

    function simulate( $args, $assoc_args ) {

        if( count($args)  != 1) $n = 0;
        else list( $n ) = $args;

        $defbyes = $this->byeCount( $n );
        if( $defbyes != -1 ) {
            $bt = new BracketTemplate();
            $bt->build( $n, $defbyes );
            // print_r( $bt->getTemplate() );
            $template = $bt->arrGetTemplate();
            foreach( $template as $line ) {
                WP_CLI::line( $line );
            }
            WP_CLI::success(__FUNCTION__ . " used $defbyes byes");
        }
        else {
            WP_CLI::warning(__FUNCTION__ . " could not calculate number of byes correctly!");
        }
    }


    /**
     * Test PHP code
     * 
     * ## OPTIONS
     * <n>
     * : Number of players
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney test 15
     *
     * @when before_wp_load
     */
    function test( $args, $assoc_args ) {

        if( count($args)  != 1) $n = 0;
        else list( $n ) = $args;
        /*
         * 1. Reference test
         */
        // $array = array(00, 11, 22, 33, 44, 55, 66, 77, 88, 99);
        // $this->ref($array, 2, $ref);
        // $ref[0] = 'xxxxxxxxx';
        // var_dump($ref);
        // var_dump($array);

        /*
         * 2. Overloaded constructors test
         * Proved that this cannot handle references in the overloaded functions' args
         */
        // $obj1 = new stdClass;
        // $obj1->name = 'Robin';
        // try {
        // $test = new Test( $obj1 );
        // }
        // catch( Exception $ex ) {
        //     WP_CLI::error( $ex->getMessage() );
        // }
        /*
         * 3. Events Manager
         
        $events = EventManager::getInstance();

        $events->listen('do', function($e) {
            echo $e->getName() . "\n";
            print_r($e->getParams());
        });
        $events->trigger('do', array('a', 'b', 'c'));
        */
        /**
         * 4. Template generator
         */
        // $tempGen = new DrawTemplateGenerator( "Test Generation", $n );
        // $template = $tempGen->generateTable( );
        // WP_CLI::line($template);

        
        /**
         * 5. Combinatorics
         */
        //$combinatorics = new Math_Combinatorics;
        // $set = array(
        //     'one'   => 'a',
        //     'two'   => 'b',
        //     'three' => 'c',
        //     'four'  => 'd',
        //     'five'  => 'e',
        //     'six'   => 'f',
        //     'seven' => 'g',
        //     'eight' => 'h',
        //     );
        // $set = range(1,8);
        // $combinations = $combinatorics->combinations($set, 2);
        // shuffle_assoc( $combinations );
        // WP_CLI::line("Combinations");
        // $combstr = print_r($combinations, true);
        // WP_CLI::line( $combstr );

        /**
         * 6. Event Copy constructor
         */

         //Tennis Event
        $testId = $n;
        $event = Event::get($testId);
        WP_CLI::Line("Leaf Event:");
        WP_CLI::Line(print_r($event,true));

        $copy = new Event('','',$event);
        $trace = GW_Debug::get_debug_trace(3);
        WP_CLI::Line("Copy of Leaf Event:");
        WP_CLI::Line(print_r($copy,true));

        //Custom post type
        $eventCPT = get_post(Event::getExtEventRefByEventId($testId));
        if(empty($eventCPT)) {
            WP_CLI::Line("Could not find custom post for event id={$testId}");
            return;
        }
        WP_CLI::Line("Event Custom Post:");
        WP_CLI::Line(print_r($eventCPT,true));

        $id = $eventCPT->ID;
        WP_CLI::Line("Event Custom Post id={$id}");
        $copyCptId = $this->duplicate( $id );
        WP_CLI::Line("Copied CPT id={$copyCptId}");
        WP_CLI::Line("Event Custom Post Copy:");
        $copyCpt = get_post($copyCptId);
        WP_CLI::Line(print_r($copyCpt,true));

        $copy->addExternalRef((string)$copyCptId);
        $copy->save();
    }

    /**
     * Duplicates a post & its meta and it returns the new duplicated Post ID
     * @param  [int] $post_id The Post you want to clone
     * @return [int] The duplicated Post ID
    */
  function duplicate($post_id) {
    $title   = get_the_title($post_id);
    $oldpost = get_post($post_id);
    if(empty($oldpost)) return 0;

    $post    = array(
      'post_title' => $title . ' Copy',
      'post_status' => 'publish',
      'post_type' => $oldpost->post_type,
      'post_author' => 1,
      'post_parent' => $oldpost->post_parent,
      'post_category' => $oldpost->post_category
    );

    $new_post_id = wp_insert_post($post);
    // Copy post metadata
    $data = get_post_custom($post_id);
    foreach ( $data as $key => $values) {
      foreach ($values as $value) {
        add_post_meta( $new_post_id, $key, $value );
      }
    }
    return $new_post_id;
  }
    

    private function ref( &$array, int $idx = 1, &$ref = array() )
    {
            //$ref = array();
            $ref[] = &$array[$idx];
    }
    
    /**
     * Calculates the number of byes in round 1
     * to cause the number of players in round 2 be a power of 2
     * The number of players and the number of byes must be 
     * of the same parity (i.e.both even or both odd)
     * @param int $n The number of entrants
     * @return int The number of byes in round 1; -1 if $n is out of range
     */
    private function byeCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;
        
        if( $n < TournamentDirector::getMinPlayersForElimination() 
        || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $exp  =  GW_Support::calculateExponent( $n );
        $target  = pow( 2, $exp );
        $result  = $target - $n;
        if( !($n & 1) && ($result & 1) ) $result = -1;
        elseif( ($n & 1) && !($result & 1) ) $result = -1;
        elseif( $this->isPowerOf2( $n ) ) $result = 0;
        
        return $result;
    }


    private function byePossibilities( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = array();
        if( $n <= 8 || $n > pow( 2, 8 ) ) return $result;

        $exp2 =  GW_Suport::calculateExponent( $n );
        $pow2 = pow(2, $exp2 );
        $maxToEliminate = $n - $pow2;
        $elimRange = range( 1, $pow2 - 1 );

        foreach( $elimRange as $byes ) {
            $possibility = array();
            $possibility["Signup"] = $n;
            $possibility["Elimination"] = $byes;
            $possibility["Round 1"] = $n - $byes;
            $possibility["Round 2"] = $byes + ($n - $byes)/2;
            array_push ($result, $possibility );
        }

        return $result;
    }

    private function challengerPossibilities( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = array();
        if( $n < 0 || $n > pow( 2, 8 ) && $n & 1 ) return $result;

        $exp2 =  GW_Support::calculateExponent( $n );
        $pow2 = pow(2, $exp2 );
        $maxToEliminate = $n - $pow2;
        $elimRange = range( 1, $pow2 - 1 );
        
        foreach( $elimRange as $challengers ) {
            $possibility = array();
            $possibility["Signup"] = $n;
            $possibility["Elimination"] = $challengers;
            $possibility["Round 0"] = 2 * $challengers;
            $possibility["Round 1"] = $n - $challengers;
            array_push ($result, $possibility );
        }

        return $result;
    }
    
    /**
     * Determine if this integer is a power of 2
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
     */
	private function isPowerOf2( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) === $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }


}

class Test extends OverloadedConstructors {
    public function __construct1(array $arg) {
        print_r( $arg );
        WP_CLI::success('First construct');
    }

    public function __construct2(stdClass &$test) {
        print_r( $test );
        WP_CLI::success('Second construct');
    }

    public function __construct3($optional = null) {
        print_r( $optional );
        WP_CLI::success('Third construct');
    }
 }

