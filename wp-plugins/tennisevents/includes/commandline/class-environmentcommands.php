<?php

WP_CLI::add_command( 'tennis env', 'EnvironmentCommands' );

/**
 * Implements all commands for managing the tennis commandline environment
 */
class EnvironmentCommands extends WP_CLI_Command {

    /**
     * Set the environment for tennis commands
     *
     * ## OPTIONS
     *
     * <clubId>
     * : The id of the club of interest
     * 
     * <eventId>
     * : The id of the event of interest
     * 
     * ## EXAMPLES
     *
     *     wp tennis env set 240 545
     *
     * @when after_wp_load
     */
    function set( $args, $assoc_args ) {

        $tsc = CmdlineSupport::preCondtion();

        list( $clubId, $eventId ) = $args;
        $club = Club::get( $clubId );
        $clubEvts = Event::find( array( 'club'=>$clubId ) );
        $found = false;
        $target = null;
        if( count( $clubEvts ) > 0 ) {
            foreach( $clubEvts as $evt ) {
                $target = $tsc->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                set_transient( CmdlineSupport::ENVNAME, array($clubId, $eventId), 1 * HOUR_IN_SECONDS );
                WP_CLI::success( "Tennis commandline environment set ($clubId, $eventId)." );
            }
            else {
                WP_CLI::error( "Event with Id '$eventId' does not exist for club with Id '$clubId'");
            }
        }
        else {
            WP_CLI::error( "No events found for the Club with Id '$clubId'" );
        }
    }

    /**
     * Delete an environment for tennis commands
     *
     * ## EXAMPLES
     *
     *     wp tennis env delete
     *
     * @when after_wp_load
     */
    function delete( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        delete_transient( CmdlineSupport::ENVNAME );
        WP_CLI::success("Tennis commandline environment deleted.");
    }

    
    /**
     * Get environment for tennis commands
     *
     * ## EXAMPLES
     *
     *     wp tennis env get
     *
     * @when after_wp_load
     */
    function get( $args, $assoc_args ) {
        
        CmdlineSupport::preCondtion();

        $env = get_transient( CmdlineSupport::ENVNAME );

        if( is_array( $env ) &&  count( $env ) === 2 ) {
            list( $clubId, $eventId ) = $env;
            WP_CLI::success("Club Id is '$clubId' and Event Id is '$eventId'");
        } else {
            WP_CLI::warning("Tennis commandline environment is not set.");
        }
    }
    
}
