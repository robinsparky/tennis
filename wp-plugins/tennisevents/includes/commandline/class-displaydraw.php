<?php
/**
 * Implements display commands.
 */
class DisplayCommands extends WP_CLI_Command {

    /**
     * Displays Draws and Matches
     *
     * ## OPTIONS
     *
     * <clubId>
     * : The numeric Id of the tennis club
     * 
     * <eventId>
     * : The numeric Id of the event within club
     *
     * ## EXAMPLES
     *
     *     wp tennis display draw 1 12
     *     wp tennis display matches 2 6
     *
     * @when after_wp_load
     */
    function draw( $args, $assoc_args ) {
        list( $clubId, $eventId ) = $args;

        WP_CLI::success("tennis display draw for club=$clubId and event=$eventId");
        
        //WP_CLI::$type( "Hello, $name!" );
    }
    /**
     * Displays Draws and Matches
     *
     * ## OPTIONS
     * 
     * <clubId>
     * : The numeric Id of the tennis club
     * 
     * <eventId>
     * : The numeric Id of the event within club
     *
     * ## EXAMPLES
     *
     *     wp tennis display matches 2 6
     *
     * @when after_wp_load
     */
    function match( $args, $assoc_args ) {
        list( $clubId, $eventId ) = $args;

        WP_CLI::success("tennis display match for club=$clubId and event=$eventId");
        
        //WP_CLI::$type( "Hello, $name!" );
    }
}

WP_CLI::add_command( 'tennis display', 'DisplayCommands' );