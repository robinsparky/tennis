<?php

WP_CLI::add_command( 'tennis match', 'MatchCommands' );

/**
 * Implements all commands for manipulating tennis match objects
 */
class MatchCommands extends WP_CLI_Command {

    /**
     * Move a match to a given destination.
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
     *     wp tennis move 1 10 16
     *     wp tennis move 1 10 16 comments='This is a comment'
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
     *     wp tennis moveup 0 1 2
     *     wp tennis moveup 1 10 3 --comments='This is a comment'
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
     *     wp tennis movedown 0 2 1
     *     wp tennis movedown 1 13 4 --comments='This is a comment'
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
