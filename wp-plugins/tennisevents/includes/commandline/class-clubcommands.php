<?php

WP_CLI::add_command( 'tennis clubs', 'ClubCommands' );

/**
 * Implements all commands for creating and deleting Clubs
 */
class ClubCommands extends WP_CLI_Command {

    private const MINCLUBNAMESIZE = 2;
    
    /**
     * Show All Clubs with their Events
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     wp tennis clubs show
     *
     * @when after_wp_load
     */
    function show( $args, $assoc_args ) {
        $allClubs = Club::find();
        if( count( $allClubs ) > 0 ) {
            WP_CLI::line( "Tennis Clubs" );
            $items = array();
            foreach( $allClubs as $club ) {
                $name = $club->getName();
                $clubId = $club->getID();
                $allEvents = Event::find( array( 'club' => $clubId ) );
                $evts = '';
                foreach( $allEvents as $evt ) {
                    $evts = $this->strEvents( $evt, 0 );
                }
                $items[] = array( 'ID' => $clubId, 'Name' => $club->getName(), 'Events' => $evts );
            }
            WP_CLI\Utils\format_items( 'table', $items, array( 'ID', 'Name', 'Events') );
        } 
        else {
         WP_CLI::warning( "No clubs found." );
        }
    }

    /**
     * Create a new club
     *
     * ## OPTIONS
     * 
     * <clubname>
     * : The name of the new club
     *
     * [--<field>=<value>]
     * : To provide number of courts for this club
     * 
     * ## EXAMPLES
     *
     *     # Create a new club with 4 courts
     *     wp tennis clubs create "My New Club" --courts=4
     * 
     * @when after_wp_load
     */
    function create( $args, $assoc_args ) {
        list( $clubName ) = $args;
        $numCourts = array_key_exists( 'courts', $assoc_args )  ? $assoc_args["courts"] : 0;

        if( strlen($clubName) > self::MINCLUBNAMESIZE ) {
            $club = new Club( $clubName );
            $affected = $club->save();
            for( $i=0; $i < $numCourts; $i ++ ) {
                $court = new Court();
                $club->addCourt( $court );
            }
            WP_CLI::success(sprintf( "(%d) '%s' was created with %d courts.",$club->getID(), $clubName, $numCourts ) );
        }
        else {
            WP_CLI::warn(sprintf( "Club name needs to be larger than %d characters!", self::MINCLUBNAMESIZE) );
        }
    }

    /**
     * Delete a club
     *
     * ## OPTIONS
     * 
     * <clubId>
     * : The Id of the new club
     * 
     * ## EXAMPLES
     *
     *     # Delete a club
     *     wp tennis clubs delete 2
     * 
     * @when after_wp_load
     */
    function delete( $args, $assoc_args ) {
        list( $clubId ) = $args;
        $club = Club::get( $clubId );
        if( !is_null( $club ) ) {
            $club->delete();
            WP_CLI::success( sprintf("Club (%d) '%s' was deleted.",$club->getID(), $club->getName() ) );
        }
        else{
            WP_CLI::warn("No such club." );
        }

    }
    
    /**
     * Attach an event to a club
     *
     * ## OPTIONS
     * <eventId>
     * :The Id of the event
     * 
     * <clubId>
     * :TheId of the club
     *
     * ## EXAMPLES
     *
     *     # Attach event 6 to club 1
     *     $ wp tennis club attach 1 6
     *
     * @when after_wp_load
     */
    public function attach( $args, $assoc_args ) {
        list( $eventId, $clubId ) = $args;

        $event = Event::get( $eventId );
        $club  = Club::get ( $clubId );

        if( is_null( $event ) ) {
            WP_CLI::error("No such event.");
        }

        if( is_null( $club ) ) {
            WP_CLI::error("No such club.");
        }

        if( $club->addEvent( $event ) ) {
            wP_CLI::success( sprintf("Attached '%s' to '%s'", $event->getName(), $club->getName() ) );
        }
        else {
            WP_CLI::error( sprintf("Unable to attach '%s' to '%s'", $event->getName(), $club->getName() ) );
        }

    }
    
    private function strEvents( Event $evt, int $level = 0 ) {
        $id = $evt->getID();
        $name = $evt->getName();
        $spacer = $level === 0 ? '->' : ':';
        $strEvent = sprintf( "%d %s(%d)%s", $level, $name, $id, $spacer );
        if( count( $evt->getChildEvents() ) > 0 ) {
            ++$level;
            foreach( $evt->getChildEvents() as $child ) {
                $strEvent .= $this->strEvents( $child, $level );
            }
        }
        return $strEvent;
    }
}
