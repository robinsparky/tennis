<?php
namespace commandline;

use \WP_CLI;
use \WP_CLI_Command;

use commonlib\GW_Support;
use commonlib\GW_Debug;

use datalayer\Club;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Match;
use datalayer\Entrant;

WP_CLI::add_command( 'tennis env', 'commandline\EnvironmentCommands' );

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
     * <bracketName>
     * : The name of the bracket
     * 
     * ## EXAMPLES
     *
     *     wp tennis env set 240 545 Main
     *
     * @when after_wp_load
     */
    function set( $args, $assoc_args ) {

        $tsc = CmdlineSupport::preCondtion();

        error_clear_last();
        list( $clubId, $eventId, $bracketName ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... clubId, eventId, bracketName ");
            exit;
        }

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
                $bracket = $target->getBracket( $bracketName );
                if( is_null( $bracket ) ) {
                    throw new Exception("Invalid Bracket: {$bracketName}");
                }
                set_transient( CmdlineSupport::ENVNAME, array($clubId, $eventId, $bracketName), 1 * HOUR_IN_SECONDS );
                WP_CLI::success( "Tennis commandline environment set ($clubId, $eventId, $bracketName)." );
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
     * Show environment for tennis commands
     *
     * ## EXAMPLES
     *
     *     wp tennis env show
     *
     * @when after_wp_load
     */
    function show( $args, $assoc_args ) {
        
        CmdlineSupport::preCondtion();

        $env = get_transient( CmdlineSupport::ENVNAME );

        if( is_array( $env ) &&  count( $env ) === 3 ) {
            list( $clubId, $eventId, $bracketName ) = $env;
            WP_CLI::success("Club Id is '$clubId' and Event Id is '$eventId' and Bracket is '{$bracketName}' ");
        } else {
            WP_CLI::warning("Tennis commandline environment is not set.");
        }
    }
    
}
