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
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        list($round, $source, $dest ) = $args;

        $count = 1000 * $dest;
        $progress = \WP_CLI\Utils\make_progress_bar( 'Moving matches', $count );
        for ( $i = 0; $i < $count; $i++ ) {
            // uses wp_insert_user() to insert the user
            $progress->tick();
        }
        $progress->finish();

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
     *
     * @when after_wp_load
     */
    function moveup( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        list($round, $source, $steps ) = $args;

        $count = 1000 * $steps;
        $progress = \WP_CLI\Utils\make_progress_bar( 'Moving matches', $count );
        for ( $i = 0; $i < $count; $i++ ) {
            // uses wp_insert_user() to insert the user
            $progress->tick();
        }
        $progress->finish();
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
     *     wp tennis movedown <round> <source> <steps>
     *
     * @when after_wp_load
     */
    function movedown( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        list($round, $source, $steps ) = $args;

        $count = 1000 * steps;
        $progress = \WP_CLI\Utils\make_progress_bar( 'Moving matches', $count );
        for ( $i = 0; $i < $count; $i++ ) {
            // uses wp_insert_user() to insert the user
            $progress->tick();
        }
        $progress->finish();
    }
}
