<?php

WP_CLI::add_command( 'tennis tourney', 'TournamentCommands' );
use api\events\EventManager;
use api\events\OverloadedConstructors;
use templates\DrawTemplateGenerator;

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

        if( 0 === $clubId || 0 === $eventId ) {
            $env = CmdlineSupport::get_instance()->getEnv();
            list( $clubId, $eventId ) = $env;
        }

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target =  $evt->getDescendant( $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $brackets = $target->getBrackets();
                $td = new TournamentDirector( $target );
                $umpire  = $td->getChairUmpire();
                foreach( $brackets as $bracket ) {
                    WP_CLI::line( '-');
                    WP_CLI::line( sprintf( "Matches for '%s' at '%s'", $evtName, $name ) );
                    $numRounds = $td->totalRounds();
                    if( $bracket->getName() == Bracket::CONSOLATION ) {
                        $numRounds = $bracket->signupSize( $umpire );
                    }
                    WP_CLI::line( sprintf( "%s Bracket: %d Rounds", $bracket->getName(), $numRounds ) );
                    $matches = $bracket->getMatches();
                    $items   = array();
                    foreach( $matches as $match ) {
                        $round   = $match->getRoundNumber();
                        $mn      = $match->getMatchNumber();
                        $status  = $umpire->matchStatus( $match );
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
     *  wp tennis tourney initialize Winners --shuffle=yes
     *
     * @when after_wp_load
     */
    function initialize( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $bracketName ) = $args;

        $shuffle = array_key_exists("shuffle",$assoc_args) ? $assoc_args["shuffle"] : '';
        if( strcasecmp("yes", $shuffle) === 0 ) $shuffle = true;
        else $shuffle = false;

        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        
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
     *  wp tennis tourney reset
     *
     * @when after_wp_load
     */
    function reset( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        $env = CmdlineSupport::instance()->getEnvError();
        list( $clubId, $eventId ) = $env;
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = CmdlineSupport::get_instance()->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                try {
                    if( $td->removeBrackets( ) ) {
                        WP_CLI::success("tennis tourney reset ... accomplished.");
                    }
                    else {
                        WP_CLI::error("tennis tourney reset ... unable to reset.");
                    }
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney reset ... failed: %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney reset ... could not find event with Id '$eventId' for club with Id '$clubId'" );
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
     * wp tennis tourney advance winners
     * 
     */
    function advance( $args, $assoc_args ) {
  
        $support = CmdlineSupport::preCondtion();

        list( $bracketName ) = $args;
        if(!is_null( error_get_last() ) ) {
            WP_CLI::error("Invalid args ... need bracket name.");
        }

        list( $clubId, $eventId ) = $support->getEnvError();
        
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
                    WP_CLI::warning("$numAdvanced entrants advanced.");
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

        error_clear_last();
        list( $bracketName, $round, $matchnum ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... bracket round match ");
            exit;
        }
        $comments = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "";

        list( $clubId, $eventId ) = $support->getEnvError();
        
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
     *  wp tennis tourney moveby winners 0 1 2
     * 
     *  wp tennis tourney moveby losers 1 10 -3 --comments='This is a comment'
     *
     * @when after_wp_load
     */
    function moveby( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $clubId, $eventId ) = $support->getEnvError();

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
     *  wp tennis tourney approve winners
     *
     * @when after_wp_load
     */
    function approve( $args, $assoc_args ) {

        list( $bracketName ) = $args;
        $bracketName = is_null( $bracketName ) || strlen($bracketName) < 1 ? 'winners' : $bracketName;

        $support = CmdlineSupport::preCondtion();
        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( !is_null($bracketName) && strlen( $bracketName) > 1 ) {
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
        else {
            WP_CLI::error( "tennis tourney approve ... invalid bracket name" );       
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
     *  wp tennis tourney review winners
     *
     * @when after_wp_load
     */
    function review( $args, $assoc_args ) {

        list( $bracketName ) = $args;
        $bracketName = is_null( $bracketName ) || strlen($bracketName) < 1 ? 'winners' : $bracketName;

        $support = CmdlineSupport::preCondtion();
        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( !is_null($bracketName) && strlen( $bracketName) > 1 ) {
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
        else {
            WP_CLI::error( "tennis tourney " . __FUNCTION__ . " ... invalid bracket name" );       
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
                $bracket = $target->getBracket( $bracketName );
                $td = new TournamentDirector( $target );
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

        $serialLive = <<<EOT
a:13:{s:13:"administrator";a:34:{s:4:"name";s:13:"Administrator";s:12:"capabilities";a:181:{s:13:"switch_themes";b:1;s:11:"edit_themes";b:1;s:16:"activate_plugins";b:1;s:12:"edit_plugins";b:1;s:10:"edit_users";b:1;s:10:"edit_files";b:1;s:14:"manage_options";b:1;s:17:"moderate_comments";b:1;s:17:"manage_categories";b:1;s:12:"manage_links";b:1;s:12:"upload_files";b:1;s:6:"import";b:1;s:15:"unfiltered_html";b:1;s:10:"edit_posts";b:1;s:17:"edit_others_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:10:"edit_pages";b:1;s:4:"read";b:1;s:8:"level_10";b:1;s:7:"level_9";b:1;s:7:"level_8";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:17:"edit_others_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"publish_pages";b:1;s:12:"delete_pages";b:1;s:19:"delete_others_pages";b:1;s:22:"delete_published_pages";b:1;s:12:"delete_posts";b:1;s:19:"delete_others_posts";b:1;s:22:"delete_published_posts";b:1;s:20:"delete_private_posts";b:1;s:18:"edit_private_posts";b:1;s:18:"read_private_posts";b:1;s:20:"delete_private_pages";b:1;s:18:"edit_private_pages";b:1;s:18:"read_private_pages";b:1;s:12:"delete_users";b:1;s:12:"create_users";b:1;s:17:"unfiltered_upload";b:1;s:14:"edit_dashboard";b:1;s:14:"update_plugins";b:1;s:14:"delete_plugins";b:1;s:15:"install_plugins";b:1;s:13:"update_themes";b:1;s:14:"install_themes";b:1;s:11:"update_core";b:1;s:10:"list_users";b:1;s:12:"remove_users";b:1;s:13:"promote_users";b:1;s:18:"edit_theme_options";b:1;s:13:"delete_themes";b:1;s:6:"export";b:1;s:19:"manage_capabilities";b:1;s:21:"publish_others_events";b:1;s:18:"edit_others_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:23:"delete_others_locations";b:1;s:22:"manage_others_bookings";b:1;s:15:"edit_categories";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:14:"publish_events";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:24:"edit_others_tribe_events";b:1;s:26:"delete_others_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:27:"delete_private_tribe_events";b:1;s:25:"edit_private_tribe_events";b:1;s:25:"read_private_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:24:"edit_others_tribe_venues";b:1;s:26:"delete_others_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:27:"delete_private_tribe_venues";b:1;s:25:"edit_private_tribe_venues";b:1;s:25:"read_private_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:28:"edit_others_tribe_organizers";b:1;s:30:"delete_others_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:31:"delete_private_tribe_organizers";b:1;s:29:"edit_private_tribe_organizers";b:1;s:29:"read_private_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;s:29:"tablepress_import_tables_wptr";b:1;s:23:"tablepress_edit_options";b:1;s:24:"NextGEN Gallery overview";b:1;s:19:"NextGEN Use TinyMCE";b:1;s:21:"NextGEN Upload images";b:1;s:22:"NextGEN Manage gallery";b:1;s:19:"NextGEN Manage tags";b:1;s:29:"NextGEN Manage others gallery";b:1;s:18:"NextGEN Edit album";b:1;s:20:"NextGEN Change style";b:1;s:22:"NextGEN Change options";b:1;s:24:"NextGEN Attach Interface";b:1;s:19:"manage_job_listings";b:1;s:16:"edit_job_listing";b:1;s:16:"read_job_listing";b:1;s:18:"delete_job_listing";b:1;s:17:"edit_job_listings";b:1;s:24:"edit_others_job_listings";b:1;s:20:"publish_job_listings";b:1;s:25:"read_private_job_listings";b:1;s:19:"delete_job_listings";b:1;s:27:"delete_private_job_listings";b:1;s:29:"delete_published_job_listings";b:1;s:26:"delete_others_job_listings";b:1;s:25:"edit_private_job_listings";b:1;s:27:"edit_published_job_listings";b:1;s:24:"manage_job_listing_terms";b:1;s:22:"edit_job_listing_terms";b:1;s:24:"delete_job_listing_terms";b:1;s:24:"assign_job_listing_terms";b:1;s:10:"copy_posts";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:1;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:1;s:16:"backwpup_restore";b:1;s:23:"wf2fa_activate_2fa_self";b:1;s:25:"wf2fa_activate_2fa_others";b:1;s:21:"wf2fa_manage_settings";b:1;}s:22:"_um_can_access_wpadmin";s:1:"1";s:24:"_um_can_not_see_adminbar";s:1:"0";s:21:"_um_can_edit_everyone";s:1:"1";s:23:"_um_can_delete_everyone";s:1:"1";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:20:"_um_default_homepage";i:1;s:15:"_um_after_login";s:14:"redirect_admin";s:16:"_um_after_logout";s:12:"redirect_url";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"1";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:6:"editor";a:35:{s:4:"name";s:6:"Editor";s:12:"capabilities";a:109:{s:17:"moderate_comments";b:1;s:12:"manage_links";b:1;s:12:"upload_files";b:1;s:15:"unfiltered_html";b:1;s:10:"edit_posts";b:1;s:17:"edit_others_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:10:"edit_pages";b:1;s:4:"read";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:17:"edit_others_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"publish_pages";b:1;s:12:"delete_pages";b:1;s:19:"delete_others_pages";b:1;s:22:"delete_published_pages";b:1;s:12:"delete_posts";b:1;s:19:"delete_others_posts";b:1;s:22:"delete_published_posts";b:1;s:20:"delete_private_posts";b:1;s:18:"edit_private_posts";b:1;s:18:"read_private_posts";b:1;s:20:"delete_private_pages";b:1;s:18:"edit_private_pages";b:1;s:18:"read_private_pages";b:1;s:18:"edit_theme_options";b:1;s:21:"publish_others_events";b:1;s:18:"edit_others_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:23:"delete_others_locations";b:1;s:22:"manage_others_bookings";b:1;s:15:"edit_categories";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:14:"publish_events";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:24:"edit_others_tribe_events";b:1;s:26:"delete_others_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:27:"delete_private_tribe_events";b:1;s:25:"edit_private_tribe_events";b:1;s:25:"read_private_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:24:"edit_others_tribe_venues";b:1;s:26:"delete_others_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:27:"delete_private_tribe_venues";b:1;s:25:"edit_private_tribe_venues";b:1;s:25:"read_private_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:28:"edit_others_tribe_organizers";b:1;s:30:"delete_others_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:31:"delete_private_tribe_organizers";b:1;s:29:"edit_private_tribe_organizers";b:1;s:29:"read_private_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;s:10:"copy_posts";b:1;}s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:12:"redirect_url";s:20:"_um_default_homepage";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:21:"_um_redirect_homepage";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:6:"author";a:35:{s:4:"name";s:6:"Author";s:12:"capabilities";a:55:{s:12:"upload_files";b:1;s:10:"edit_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:4:"read";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:12:"delete_posts";b:1;s:22:"delete_published_posts";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;}s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:12:"redirect_url";s:20:"_um_default_homepage";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:21:"_um_redirect_homepage";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:10:"subscriber";a:16:{s:4:"name";s:10:"Subscriber";s:12:"capabilities";a:8:{s:4:"read";b:1;s:7:"level_0";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:19:"upload_event_images";b:1;s:16:"read_tribe_event";b:1;s:20:"read_tribe_organizer";b:1;s:16:"read_tribe_venue";b:1;}s:22:"_um_can_access_wpadmin";i:0;s:24:"_um_can_not_see_adminbar";i:1;s:21:"_um_can_edit_everyone";i:0;s:23:"_um_can_delete_everyone";i:0;s:20:"_um_can_edit_profile";i:1;s:22:"_um_can_delete_profile";i:1;s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:13:"redirect_home";s:20:"_um_default_homepage";i:1;s:16:"_um_can_view_all";i:1;s:28:"_um_can_make_private_profile";i:0;s:30:"_um_can_access_private_profile";i:0;s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";}s:12:"super_editor";a:2:{s:4:"name";s:12:"Super Editor";s:12:"capabilities";a:66:{s:12:"create_users";b:1;s:19:"delete_others_pages";b:1;s:19:"delete_others_posts";b:1;s:12:"delete_pages";b:1;s:12:"delete_posts";b:1;s:20:"delete_private_posts";b:1;s:22:"delete_published_pages";b:1;s:22:"delete_published_posts";b:1;s:17:"edit_others_pages";b:1;s:17:"edit_others_posts";b:1;s:10:"edit_posts";b:1;s:18:"edit_private_pages";b:1;s:18:"edit_private_posts";b:1;s:20:"edit_published_posts";b:1;s:18:"edit_theme_options";b:1;s:11:"edit_themes";b:1;s:10:"edit_users";b:1;s:10:"list_users";b:1;s:12:"manage_links";b:1;s:13:"promote_users";b:1;s:13:"publish_pages";b:1;s:13:"publish_posts";b:1;s:4:"read";b:1;s:18:"read_private_pages";b:1;s:18:"read_private_posts";b:1;s:12:"remove_users";b:1;s:15:"unfiltered_html";b:1;s:13:"update_themes";b:1;s:12:"upload_files";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:20:"delete_private_pages";b:1;s:12:"delete_users";b:1;s:10:"edit_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"delete_events";b:1;s:16:"delete_locations";b:1;s:23:"delete_others_locations";b:1;s:14:"edit_locations";b:1;s:15:"manage_bookings";b:1;s:14:"manage_options";b:1;s:22:"manage_others_bookings";b:1;s:21:"publish_others_events";b:1;s:21:"read_others_locations";b:1;s:11:"edit_events";b:1;s:15:"edit_categories";b:1;s:18:"edit_others_events";b:1;s:16:"edit_recurrences";b:1;s:14:"publish_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;}}s:11:"contributor";a:15:{s:12:"capabilities";a:27:{s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;}s:22:"_um_can_access_wpadmin";i:0;s:24:"_um_can_not_see_adminbar";i:1;s:21:"_um_can_edit_everyone";i:0;s:23:"_um_can_delete_everyone";i:0;s:20:"_um_can_edit_profile";i:1;s:22:"_um_can_delete_profile";i:1;s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:13:"redirect_home";s:20:"_um_default_homepage";i:1;s:16:"_um_can_view_all";i:1;s:28:"_um_can_make_private_profile";i:0;s:30:"_um_can_access_private_profile";i:0;s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";}s:8:"employer";a:31:{s:4:"name";s:8:"Employer";s:12:"capabilities";a:3:{s:4:"read";b:1;s:10:"edit_posts";b:0;s:12:"delete_posts";b:0;}s:15:"_um_synced_role";s:8:"employer";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:7:"pending";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:219:"Thank you for applying for membership to be an employer for IENs and post job listings on our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:37:"https://care4nurses.org/job-dashboard/";s:16:"_um_after_logout";s:13:"redirect_home";s:23:"_um_logout_redirect_url";s:0:"";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:18:"_um_can_view_roles";a:1:{i:0;s:13:"um_caremember";}}s:13:"um_caremember";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:10:"CAREmember";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:18:"_um_can_view_roles";a:1:{i:0;s:8:"employer";}s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:7:"pending";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:166:"Thank you for applying for membership to CARE. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:37:"https://care4nurses.org/welcome";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:9:"um_member";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:6:"Member";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"0";s:18:"_um_can_view_roles";s:0:"";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:12:"redirect_url";s:20:"_um_auto_approve_url";s:55:"https://care4nurses.org/carewebinar/information-session";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:55:"https://care4nurses.org/carewebinar/information-session";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:8:"um_admin";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:5:"Admin";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"1";s:24:"_um_can_not_see_adminbar";s:1:"0";s:21:"_um_can_edit_everyone";s:1:"1";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"1";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:18:"_um_can_view_roles";s:0:"";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"1";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:14:"redirect_admin";s:22:"_um_login_redirect_url";s:0:"";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:31:"https://care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:14:"backwpup_admin";a:2:{s:4:"name";s:14:"BackWPup Admin";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:1;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:1;s:16:"backwpup_restore";b:1;}}s:14:"backwpup_check";a:2:{s:4:"name";s:21:"BackWPup jobs checker";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:0;s:19:"backwpup_jobs_start";b:0;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:0;s:23:"backwpup_backups_delete";b:0;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:0;s:17:"backwpup_settings";b:0;s:16:"backwpup_restore";b:0;}}s:15:"backwpup_helper";a:2:{s:4:"name";s:20:"BackWPup jobs helper";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:0;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:0;s:16:"backwpup_restore";b:0;}}}
EOT;

        $serialStage=<<<EOT
a:13:{s:13:"administrator";a:34:{s:4:"name";s:13:"Administrator";s:12:"capabilities";a:195:{s:13:"switch_themes";b:1;s:11:"edit_themes";b:1;s:16:"activate_plugins";b:1;s:12:"edit_plugins";b:1;s:10:"edit_users";b:1;s:10:"edit_files";b:1;s:14:"manage_options";b:1;s:17:"moderate_comments";b:1;s:17:"manage_categories";b:1;s:12:"manage_links";b:1;s:12:"upload_files";b:1;s:6:"import";b:1;s:15:"unfiltered_html";b:1;s:10:"edit_posts";b:1;s:17:"edit_others_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:10:"edit_pages";b:1;s:4:"read";b:1;s:8:"level_10";b:1;s:7:"level_9";b:1;s:7:"level_8";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:17:"edit_others_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"publish_pages";b:1;s:12:"delete_pages";b:1;s:19:"delete_others_pages";b:1;s:22:"delete_published_pages";b:1;s:12:"delete_posts";b:1;s:19:"delete_others_posts";b:1;s:22:"delete_published_posts";b:1;s:20:"delete_private_posts";b:1;s:18:"edit_private_posts";b:1;s:18:"read_private_posts";b:1;s:20:"delete_private_pages";b:1;s:18:"edit_private_pages";b:1;s:18:"read_private_pages";b:1;s:12:"delete_users";b:1;s:12:"create_users";b:1;s:17:"unfiltered_upload";b:1;s:14:"edit_dashboard";b:1;s:14:"update_plugins";b:1;s:14:"delete_plugins";b:1;s:15:"install_plugins";b:1;s:13:"update_themes";b:1;s:14:"install_themes";b:1;s:11:"update_core";b:1;s:10:"list_users";b:1;s:12:"remove_users";b:1;s:13:"promote_users";b:1;s:18:"edit_theme_options";b:1;s:13:"delete_themes";b:1;s:6:"export";b:1;s:19:"manage_capabilities";b:1;s:21:"publish_others_events";b:1;s:18:"edit_others_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:23:"delete_others_locations";b:1;s:22:"manage_others_bookings";b:1;s:15:"edit_categories";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:14:"publish_events";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:24:"edit_others_tribe_events";b:1;s:26:"delete_others_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:27:"delete_private_tribe_events";b:1;s:25:"edit_private_tribe_events";b:1;s:25:"read_private_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:24:"edit_others_tribe_venues";b:1;s:26:"delete_others_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:27:"delete_private_tribe_venues";b:1;s:25:"edit_private_tribe_venues";b:1;s:25:"read_private_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:28:"edit_others_tribe_organizers";b:1;s:30:"delete_others_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:31:"delete_private_tribe_organizers";b:1;s:29:"edit_private_tribe_organizers";b:1;s:29:"read_private_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;s:29:"tablepress_import_tables_wptr";b:1;s:23:"tablepress_edit_options";b:1;s:24:"NextGEN Gallery overview";b:1;s:19:"NextGEN Use TinyMCE";b:1;s:21:"NextGEN Upload images";b:1;s:22:"NextGEN Manage gallery";b:1;s:19:"NextGEN Manage tags";b:1;s:29:"NextGEN Manage others gallery";b:1;s:18:"NextGEN Edit album";b:1;s:20:"NextGEN Change style";b:1;s:22:"NextGEN Change options";b:1;s:24:"NextGEN Attach Interface";b:1;s:19:"manage_job_listings";b:1;s:16:"edit_job_listing";b:1;s:16:"read_job_listing";b:1;s:18:"delete_job_listing";b:1;s:17:"edit_job_listings";b:1;s:24:"edit_others_job_listings";b:1;s:20:"publish_job_listings";b:1;s:25:"read_private_job_listings";b:1;s:19:"delete_job_listings";b:1;s:27:"delete_private_job_listings";b:1;s:29:"delete_published_job_listings";b:1;s:26:"delete_others_job_listings";b:1;s:25:"edit_private_job_listings";b:1;s:27:"edit_published_job_listings";b:1;s:24:"manage_job_listing_terms";b:1;s:22:"edit_job_listing_terms";b:1;s:24:"delete_job_listing_terms";b:1;s:24:"assign_job_listing_terms";b:1;s:10:"copy_posts";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:1;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:1;s:16:"backwpup_restore";b:1;s:20:"cf7_db_form_view7408";b:1;s:21:"cf7_db_form_edit_7408";b:1;s:20:"cf7_db_form_view3510";b:1;s:21:"cf7_db_form_edit_3510";b:1;s:20:"cf7_db_form_view3509";b:1;s:21:"cf7_db_form_edit_3509";b:1;s:20:"cf7_db_form_view3508";b:1;s:21:"cf7_db_form_edit_3508";b:1;s:20:"cf7_db_form_view3507";b:1;s:21:"cf7_db_form_edit_3507";b:1;s:19:"cf7_db_form_view591";b:1;s:20:"cf7_db_form_edit_591";b:1;s:19:"cf7_db_form_view592";b:1;s:20:"cf7_db_form_edit_592";b:1;s:23:"wf2fa_activate_2fa_self";b:1;s:25:"wf2fa_activate_2fa_others";b:1;s:21:"wf2fa_manage_settings";b:1;}s:22:"_um_can_access_wpadmin";s:1:"1";s:24:"_um_can_not_see_adminbar";s:1:"0";s:21:"_um_can_edit_everyone";s:1:"1";s:23:"_um_can_delete_everyone";s:1:"1";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:20:"_um_default_homepage";i:1;s:15:"_um_after_login";s:14:"redirect_admin";s:16:"_um_after_logout";s:12:"redirect_url";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"1";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:6:"editor";a:35:{s:4:"name";s:6:"Editor";s:12:"capabilities";a:109:{s:17:"moderate_comments";b:1;s:12:"manage_links";b:1;s:12:"upload_files";b:1;s:15:"unfiltered_html";b:1;s:10:"edit_posts";b:1;s:17:"edit_others_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:10:"edit_pages";b:1;s:4:"read";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:17:"edit_others_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"publish_pages";b:1;s:12:"delete_pages";b:1;s:19:"delete_others_pages";b:1;s:22:"delete_published_pages";b:1;s:12:"delete_posts";b:1;s:19:"delete_others_posts";b:1;s:22:"delete_published_posts";b:1;s:20:"delete_private_posts";b:1;s:18:"edit_private_posts";b:1;s:18:"read_private_posts";b:1;s:20:"delete_private_pages";b:1;s:18:"edit_private_pages";b:1;s:18:"read_private_pages";b:1;s:18:"edit_theme_options";b:1;s:21:"publish_others_events";b:1;s:18:"edit_others_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:23:"delete_others_locations";b:1;s:22:"manage_others_bookings";b:1;s:15:"edit_categories";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:14:"publish_events";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:24:"edit_others_tribe_events";b:1;s:26:"delete_others_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:27:"delete_private_tribe_events";b:1;s:25:"edit_private_tribe_events";b:1;s:25:"read_private_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:24:"edit_others_tribe_venues";b:1;s:26:"delete_others_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:27:"delete_private_tribe_venues";b:1;s:25:"edit_private_tribe_venues";b:1;s:25:"read_private_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:28:"edit_others_tribe_organizers";b:1;s:30:"delete_others_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:31:"delete_private_tribe_organizers";b:1;s:29:"edit_private_tribe_organizers";b:1;s:29:"read_private_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;s:10:"copy_posts";b:1;}s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:12:"redirect_url";s:20:"_um_default_homepage";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:21:"_um_redirect_homepage";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:6:"author";a:35:{s:4:"name";s:6:"Author";s:12:"capabilities";a:55:{s:12:"upload_files";b:1;s:10:"edit_posts";b:1;s:20:"edit_published_posts";b:1;s:13:"publish_posts";b:1;s:4:"read";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:12:"delete_posts";b:1;s:22:"delete_published_posts";b:1;s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:20:"publish_tribe_events";b:1;s:27:"edit_published_tribe_events";b:1;s:29:"delete_published_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:20:"publish_tribe_venues";b:1;s:27:"edit_published_tribe_venues";b:1;s:29:"delete_published_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;s:24:"publish_tribe_organizers";b:1;s:31:"edit_published_tribe_organizers";b:1;s:33:"delete_published_tribe_organizers";b:1;s:22:"tablepress_edit_tables";b:1;s:24:"tablepress_delete_tables";b:1;s:22:"tablepress_list_tables";b:1;s:21:"tablepress_add_tables";b:1;s:22:"tablepress_copy_tables";b:1;s:24:"tablepress_import_tables";b:1;s:24:"tablepress_export_tables";b:1;s:32:"tablepress_access_options_screen";b:1;s:30:"tablepress_access_about_screen";b:1;}s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:12:"redirect_url";s:20:"_um_default_homepage";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:13:"_um_is_custom";s:1:"0";s:12:"_um_priority";s:0:"";s:18:"_um_can_edit_roles";s:0:"";s:20:"_um_can_delete_roles";s:0:"";s:18:"_um_can_view_roles";s:0:"";s:21:"_um_redirect_homepage";s:0:"";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:22:"_um_login_redirect_url";s:0:"";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";}s:10:"subscriber";a:16:{s:4:"name";s:10:"Subscriber";s:12:"capabilities";a:8:{s:4:"read";b:1;s:7:"level_0";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:19:"upload_event_images";b:1;s:16:"read_tribe_event";b:1;s:20:"read_tribe_organizer";b:1;s:16:"read_tribe_venue";b:1;}s:22:"_um_can_access_wpadmin";i:0;s:24:"_um_can_not_see_adminbar";i:1;s:21:"_um_can_edit_everyone";i:0;s:23:"_um_can_delete_everyone";i:0;s:20:"_um_can_edit_profile";i:1;s:22:"_um_can_delete_profile";i:1;s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:13:"redirect_home";s:20:"_um_default_homepage";i:1;s:16:"_um_can_view_all";i:1;s:28:"_um_can_make_private_profile";i:0;s:30:"_um_can_access_private_profile";i:0;s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";}s:12:"super_editor";a:2:{s:4:"name";s:12:"Super Editor";s:12:"capabilities";a:66:{s:12:"create_users";b:1;s:19:"delete_others_pages";b:1;s:19:"delete_others_posts";b:1;s:12:"delete_pages";b:1;s:12:"delete_posts";b:1;s:20:"delete_private_posts";b:1;s:22:"delete_published_pages";b:1;s:22:"delete_published_posts";b:1;s:17:"edit_others_pages";b:1;s:17:"edit_others_posts";b:1;s:10:"edit_posts";b:1;s:18:"edit_private_pages";b:1;s:18:"edit_private_posts";b:1;s:20:"edit_published_posts";b:1;s:18:"edit_theme_options";b:1;s:11:"edit_themes";b:1;s:10:"edit_users";b:1;s:10:"list_users";b:1;s:12:"manage_links";b:1;s:13:"promote_users";b:1;s:13:"publish_pages";b:1;s:13:"publish_posts";b:1;s:4:"read";b:1;s:18:"read_private_pages";b:1;s:18:"read_private_posts";b:1;s:12:"remove_users";b:1;s:15:"unfiltered_html";b:1;s:13:"update_themes";b:1;s:12:"upload_files";b:1;s:7:"level_7";b:1;s:7:"level_6";b:1;s:7:"level_5";b:1;s:7:"level_4";b:1;s:7:"level_3";b:1;s:7:"level_2";b:1;s:7:"level_1";b:1;s:7:"level_0";b:1;s:20:"delete_private_pages";b:1;s:12:"delete_users";b:1;s:10:"edit_pages";b:1;s:20:"edit_published_pages";b:1;s:13:"delete_events";b:1;s:16:"delete_locations";b:1;s:23:"delete_others_locations";b:1;s:14:"edit_locations";b:1;s:15:"manage_bookings";b:1;s:14:"manage_options";b:1;s:22:"manage_others_bookings";b:1;s:21:"publish_others_events";b:1;s:21:"read_others_locations";b:1;s:11:"edit_events";b:1;s:15:"edit_categories";b:1;s:18:"edit_others_events";b:1;s:16:"edit_recurrences";b:1;s:14:"publish_events";b:1;s:20:"delete_others_events";b:1;s:21:"edit_others_locations";b:1;s:17:"publish_locations";b:1;s:24:"publish_recurring_events";b:1;s:28:"edit_others_recurring_events";b:1;s:30:"delete_others_recurring_events";b:1;s:21:"edit_event_categories";b:1;s:23:"delete_event_categories";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;}}s:11:"contributor";a:15:{s:12:"capabilities";a:27:{s:11:"edit_events";b:1;s:14:"edit_locations";b:1;s:13:"delete_events";b:1;s:15:"manage_bookings";b:1;s:16:"delete_locations";b:1;s:16:"edit_recurrences";b:1;s:21:"read_others_locations";b:1;s:21:"edit_recurring_events";b:1;s:23:"delete_recurring_events";b:1;s:19:"upload_event_images";b:1;s:19:"read_private_events";b:1;s:22:"read_private_locations";b:1;s:16:"edit_tribe_event";b:1;s:16:"read_tribe_event";b:1;s:18:"delete_tribe_event";b:1;s:19:"delete_tribe_events";b:1;s:17:"edit_tribe_events";b:1;s:16:"edit_tribe_venue";b:1;s:16:"read_tribe_venue";b:1;s:18:"delete_tribe_venue";b:1;s:19:"delete_tribe_venues";b:1;s:17:"edit_tribe_venues";b:1;s:20:"edit_tribe_organizer";b:1;s:20:"read_tribe_organizer";b:1;s:22:"delete_tribe_organizer";b:1;s:23:"delete_tribe_organizers";b:1;s:21:"edit_tribe_organizers";b:1;}s:22:"_um_can_access_wpadmin";i:0;s:24:"_um_can_not_see_adminbar";i:1;s:21:"_um_can_edit_everyone";i:0;s:23:"_um_can_delete_everyone";i:0;s:20:"_um_can_edit_profile";i:1;s:22:"_um_can_delete_profile";i:1;s:15:"_um_after_login";s:16:"redirect_profile";s:16:"_um_after_logout";s:13:"redirect_home";s:20:"_um_default_homepage";i:1;s:16:"_um_can_view_all";i:1;s:28:"_um_can_make_private_profile";i:0;s:30:"_um_can_access_private_profile";i:0;s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";}s:8:"employer";a:31:{s:4:"name";s:8:"Employer";s:12:"capabilities";a:3:{s:4:"read";b:1;s:10:"edit_posts";b:0;s:12:"delete_posts";b:0;}s:15:"_um_synced_role";s:8:"employer";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:7:"pending";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:219:"Thank you for applying for membership to be an employer for IENs and post job listings on our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:37:"http://care4nurses.org/job-dashboard/";s:16:"_um_after_logout";s:13:"redirect_home";s:23:"_um_logout_redirect_url";s:0:"";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:18:"_um_can_view_roles";a:1:{i:0;s:13:"um_caremember";}}s:13:"um_caremember";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:10:"CAREmember";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:18:"_um_can_view_roles";a:1:{i:0;s:8:"employer";}s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:7:"pending";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:166:"Thank you for applying for membership to CARE. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:9:"um_member";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:6:"Member";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"0";s:24:"_um_can_not_see_adminbar";s:1:"1";s:21:"_um_can_edit_everyone";s:1:"0";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"0";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"0";s:18:"_um_can_view_roles";s:0:"";s:28:"_um_can_make_private_profile";s:1:"0";s:30:"_um_can_access_private_profile";s:1:"0";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:12:"redirect_url";s:20:"_um_auto_approve_url";s:61:"https://stage.care4nurses.org/carewebinar/information-session";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:12:"redirect_url";s:22:"_um_login_redirect_url";s:61:"https://stage.care4nurses.org/carewebinar/information-session";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:8:"um_admin";a:35:{s:13:"_um_is_custom";s:1:"1";s:4:"name";s:5:"Admin";s:12:"_um_priority";s:0:"";s:22:"_um_can_access_wpadmin";s:1:"1";s:24:"_um_can_not_see_adminbar";s:1:"0";s:21:"_um_can_edit_everyone";s:1:"1";s:18:"_um_can_edit_roles";s:0:"";s:23:"_um_can_delete_everyone";s:1:"1";s:20:"_um_can_delete_roles";s:0:"";s:20:"_um_can_edit_profile";s:1:"1";s:22:"_um_can_delete_profile";s:1:"1";s:16:"_um_can_view_all";s:1:"1";s:18:"_um_can_view_roles";s:0:"";s:28:"_um_can_make_private_profile";s:1:"1";s:30:"_um_can_access_private_profile";s:1:"1";s:20:"_um_default_homepage";s:1:"1";s:21:"_um_redirect_homepage";s:0:"";s:10:"_um_status";s:8:"approved";s:20:"_um_auto_approve_act";s:16:"redirect_profile";s:20:"_um_auto_approve_url";s:0:"";s:24:"_um_login_email_activate";s:1:"0";s:20:"_um_checkmail_action";s:12:"show_message";s:21:"_um_checkmail_message";s:147:"Thank you for registering. Before you can login we need you to activate your account by clicking the activation link in the email we just sent you.";s:17:"_um_checkmail_url";s:0:"";s:22:"_um_url_email_activate";s:0:"";s:18:"_um_pending_action";s:12:"show_message";s:19:"_um_pending_message";s:170:"Thank you for applying for membership to our site. We will review your details and send you an email letting you know whether your application has been successful or not.";s:15:"_um_pending_url";s:0:"";s:15:"_um_after_login";s:14:"redirect_admin";s:22:"_um_login_redirect_url";s:0:"";s:16:"_um_after_logout";s:12:"redirect_url";s:23:"_um_logout_redirect_url";s:37:"https://stage.care4nurses.org/welcome";s:16:"_um_after_delete";s:13:"redirect_home";s:23:"_um_delete_redirect_url";s:0:"";s:12:"capabilities";a:1:{s:4:"read";b:1;}}s:14:"backwpup_admin";a:2:{s:4:"name";s:14:"BackWPup Admin";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:1;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:1;s:16:"backwpup_restore";b:1;}}s:14:"backwpup_check";a:2:{s:4:"name";s:21:"BackWPup jobs checker";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:0;s:19:"backwpup_jobs_start";b:0;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:0;s:23:"backwpup_backups_delete";b:0;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:0;s:17:"backwpup_settings";b:0;s:16:"backwpup_restore";b:0;}}s:15:"backwpup_helper";a:2:{s:4:"name";s:20:"BackWPup jobs helper";s:12:"capabilities";a:12:{s:4:"read";b:1;s:8:"backwpup";b:1;s:13:"backwpup_jobs";b:1;s:18:"backwpup_jobs_edit";b:0;s:19:"backwpup_jobs_start";b:1;s:16:"backwpup_backups";b:1;s:25:"backwpup_backups_download";b:1;s:23:"backwpup_backups_delete";b:1;s:13:"backwpup_logs";b:1;s:20:"backwpup_logs_delete";b:1;s:17:"backwpup_settings";b:0;s:16:"backwpup_restore";b:0;}}}
EOT;
        $ctr = 0;
        foreach([$serialLive,$serialStage] as $serialize) {
            // WP_CLI::line("Serialized value:");
            // WP_CLI::line( $serial );
            ++$ctr;
            $res = maybe_unserialize( $serial );
            if(is_null( $res ) || empty( $res )) {
                WP_CLI::line( "$ctr. Unserialized Value is empty or null!");
            }
            else {
                WP_CLI::line("$ctr. Unserialized Value:");
                $out = print_r( $res, true );
                WP_CLI::line( $out );
            }
        }
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
     * @param $n The number of entrants
     */
    private function byeCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS 
        || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $exp  =  TournamentDirector::calculateExponent( $n );
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

        $exp2 =  TournamentDirector::calculateExponent( $n );
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

        $exp2 =  TournamentDirector::calculateExponent( $n );
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

