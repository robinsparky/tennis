<?php

WP_CLI::add_command( 'tennis signup', 'SignupCommands' );

/**
 * Implements all commands for manipulating tennis event signup
 */
class SignupCommands extends WP_CLI_Command {

    /**
     * Add a player to the tennis event
     * The target club and event must first be set using 'tennis env'
     *
     * ## EXAMPLES
     *
     *     wp tennis signup add <name> <seed> 
     * 
     * ## EXAMPLES
     *
     *     wp tennis signup add "Robin Smith" 1
     *
     * @when after_wp_load
     */
    function add( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        list( $player, $seed ) = $args;

        $env = CmdlineSupport::get_instance()->getEnv();
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
                if( $td->addEntrant( $player, $seed ) ) {
                    WP_CLI::success("tennis signup add ... signed up $player");
                }
                else {
                    WP_CLI::error("tennis signup add ... unable to signed up $player");
                }
            }
            else {
                WP_CLI::warning( "tennis signup add ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup add ... could not find any events for club with Id '$clubId'" );
        }
    }
    
    /**
     * Remove a player from the tennis event
     * The target club and event must first be set using 'tennis env'
     *
     * ## EXAMPLES
     *
     *     wp tennis signup remove <name>
     * 
     * ## EXAMPLES
     *
     *     wp tennis signup remove "Robin Smith"
     *
     * @when after_wp_load
     */
    function remove( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();
        list( $player, $seed ) = $args;

        $env = CmdlineSupport::get_instance()->getEnv();
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
                if( $td->removeEntrant( $player) ) {
                    WP_CLI::success("tennis signup remove ... $player");
                }
                else {
                    WP_CLI::error("tennis signup remove ... unable to remove $player");
                }
            }
            else {
                WP_CLI::warning( "tennis signup remove... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup remove ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Reset the signup for this tennis event
     * The target club and event must first be set using 'tennis env'
     * 
     * ## EXAMPLES
     *
     *     wp tennis signup reset
     *
     * @when after_wp_load
     */
    function reset( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        $env = CmdlineSupport::get_instance()->getEnv();
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
                if( $td->removeDraw() ) {
                    WP_CLI::success("tennis signup reset");
                }
                else {
                    WP_CLI::error("tennis signup reset ... unable to reset");
                }
            }
            else {
                WP_CLI::warning( "tennis signup reset... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup reset ... could not find any events for club with Id '$clubId'" );
        }
    }

}
