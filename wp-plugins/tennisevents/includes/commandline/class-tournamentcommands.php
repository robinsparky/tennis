<?php

WP_CLI::add_command( 'tennis tourney', 'TournamentCommands' );

/**
 * Implements all commands for manipulating a tournament's tennis matches
 */
class TournamentCommands extends WP_CLI_Command {

    /**
     * Create the preliminary rounds for the current signup
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <watershed>
     * : The watershed for creating challenger round first
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney initialize 5
     *
     * @when after_wp_load
     */
    function initialize( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $watershed ) = $args;

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
                $td = new TournamentDirector( $target, $target->getMatchType() );
                try {
                    $randomizeDraw = false;
                    $numMatches = $td->schedulePreliminaryRounds( $randomizeDraw, $watershed );
                    if( $numMatches > 0 ) {
                        WP_CLI::success( "tennis match initialize ... generated $numMatches preliminary matches" );
                    }
                    else {
                        throw new Exception( "Failed to generate any matches." );
                    }
                }
                catch( Exception $ex ) { 
                    WP_CLI::error( sprintf( "tennis match initialize ... %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match initialize... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match initialize... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Delete all matches for this tennis tournament
     * The target club and event must first be set using 'tennis env'
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney reset
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
                $td = new TournamentDirector( $target, $target->getMatchType() );
                if( $td->removeBrackets() ) {
                    WP_CLI::success("tennis match reset ... all matches removed.");
                }
                else {
                    WP_CLI::error("tennis match reset ... unable to remove matches.");
                }
            }
            else {
                WP_CLI::warning( "tennis match reset... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match reset ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Move a match to a given point in the draw.
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <round>
     * : The round containing the match
     * 
     * <source>
     * : The match number of the source match
     * 
     * <destination>
     * : The destination match number
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney move 1 10 16
     *     wp tennis tourney move 1 10 16 comments='This is a comment'
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $round, $source, $dest ) = $args;
        list( $clubId, $eventId ) = $support->getEnvError();

        $fromId = "Match($eventId,$round,$source)";
        $toId   = "Match($eventId,$round,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved from $fromId to $toId on $stamp";

        $result = Match::move( $eventId, $round, $source, $dest, $cmts );
        if( $result > 0 ) {
            WP_CLI::success("Match moved. Affected $result rows.");
        }
        else {
            WP_CLI::warning("Match was not moved");
        }
    }

    /**
     * Move a match forward by given steps.
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <round>
     * : The round containing the match
     * 
     * <source>
     * : The match number of the source match
     * 
     * <steps>
     * : The number of places to advance match number
     *
     * ## EXAMPLES
     *
     *     wp tennis tourney moveup 0 1 2
     *     wp tennis tourney moveup 1 10 3 --comments='This is a comment'
     *
     * @when after_wp_load
     */
    function moveup( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list($round, $source, $steps ) = $args;
        list( $clubId, $eventId ) = $support->getEnvError();
        
        $result = 0;
        if( $steps > 0 && $steps < 257 ) {
            $dest   = $source + $steps;
            $fromId = "Match($eventId,$round,$source)";
            $toId   = "Match($eventId,$round,$dest)";
            
            date_default_timezone_set("America/Toronto");
            $stamp = date("Y-m-d h:i:sa");
            $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved up from $fromId by $steps to $toId on $stamp";
    
            $result =  Match::move( $eventId, $round, $source, $dest, $cmts );
        }

        if( $result > 0 ) {
            WP_CLI::success( "Match moved up. Affected $result rows." );
        }
        else {
            WP_CLI::warning("Match was not moved");
        }
    }
    
    /**
     * Move a match backward by given steps.
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <round>
     * : The round containing the match
     * 
     * <source>
     * : The match number of the source match
     * 
     * <steps>
     * : The number of places to move match number back
     *
     * ## EXAMPLES
     *
     *     wp tennis tourney movedown 0 2 1
     *     wp tennis tuorney movedown 1 13 4 --comments='This is a comment'
     *
     * @when after_wp_load
     */
    function movedown( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $round, $source, $steps ) = $args;
        list( $clubId, $eventId ) = $support->getEnvError();
        
        $result = 0;
        if( $steps > 0 && $steps < 257 ) {
            $dest   = $source - $steps;
            $dest   = max( 1, $dest );
            $fromId = "Match($eventId,$round,$source )";
            $toId   = "Match($eventId,$round,$dest)";
            
            date_default_timezone_set( "America/Toronto" );
            $stamp = date( "Y-m-d h:i:sa" );
            $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved down from $fromId by $steps to $toId on $stamp";
    
            $result =  Match::move( $eventId, $round, $source, $dest, $cmts );
        }

        if( $result > 0 ) {
            WP_CLI::success( "Match moved down. Affected $result rows." );
        }
        else {
            WP_CLI::warning( "Match was not moved" );
        }
    }
}
