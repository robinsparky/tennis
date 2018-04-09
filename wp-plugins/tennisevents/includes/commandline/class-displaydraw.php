<?php

WP_CLI::add_command( 'tennis display', 'DisplayCommands' );

/**
 * Implements all display commands.
 */
class DisplayCommands extends WP_CLI_Command {

    /**
     * Display Clubs
     *
     * ## EXAMPLES
     *
     *     wp tennis display clubs
     *
     * @when after_wp_load
     */
    function clubs( $args, $assoc_args ) {
        $allClubs = Club::find();
        if( count( $allCubs ) > 0 ) {
            WP_CLI::warning( "List of Tennis Clubs" );
            foreach( $allClubs as $club ) {
                $name = $club->getName();
                $id = $club->getID();
                WP_CLI::success("$id. $name");
            }
        }
        else {
         WP_CLI::warning( "No clubs found." );
        }
    }

    /**
     * Display Events for a Club
     *
     * ## OPTIONS
     *
     * <clubId>
     * : The numeric Id of the tennis club
     * 
     * ## EXAMPLES
     *
     *     wp tennis display events 260
     *
     * @when after_wp_load
     */
    function events( $args, $assoc_args ) {
        list( $clubId ) = $args;
        $allEvents = Event::find( array( 'club' => $clubId ) );
        if( count( $allEvents ) > 0 ) {
            $club = Club::get( $clubId );
            $name = $club->getName();
            WP_CLI::line( "Events For Club '$name'" );
            foreach( $allEvents as $evt ) {
                $this->showEvent( $evt );
            }
        }
        else {
            WP_CLI::warning( "No events were found for club with Id '$clubId'" );
        }
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
     *     wp tennis display draw 1 12
     *     wp tennis display matches 2 6
     *
     * @when after_wp_load
     */
    function draw( $args, $assoc_args ) {
        list( $clubId, $eventId ) = $args;
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $this->getEvent( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    $club = Club::get( $clubId );
                    $name = $club->getName();
                    $evtName = $target->getName();
                    WP_CLI::line( "Draw for '$evtName' at '$name'");
                    $td = new TournamentDirector( $target, $target->getMatchType() );
                    $report = $td->arrShowDraw();
                    foreach( $report as $line ) {
                        WP_CLI::line( $line );
                    }
                }
            }
        }
        else {
            WP_CLI::warning( "tennis display draw ... could not any events for club with Id '$clubId' and event with Id '$eventId'" );
        }
        if( !$found ) {
            WP_CLI::warning( "tennis display draw ... could not event with Id '$eventId' for club with Id '$clubId'" );
        }
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

        list( $clubId, $eventId ) = $args;
        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $this->getEvent( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    $club = Club::get( $clubId );
                    $name = $club->getName();
                    $evtName = $target->getName();
                    WP_CLI::line( "Matches for '$evtName' at '$name'");
                    $td = new TournamentDirector( $target, $target->getMatchType() );
                    $report = $td->arrShowMatches( 0 );
                    foreach( $report as $line ) {
                        WP_CLI::line( $line );
                    }
                    $report = $td->arrShowMatches( 1 );
                    foreach( $report as $line ) {
                        WP_CLI::line( $line );
                    }
                }
            }
        }
        else {
            WP_CLI::warning( "tennis display match ... could not any events for club=$clubId and event=$eventId" );
        }
        
        if( !$found ) {
            WP_CLI::warning( "tennis display match ... could not event=$eventId for club=$clubId" );
        }
    }
    
    private function showEvent( Event $evt, int $level = 0 ) {
        $id = $evt->getID();
        $name = $evt->getName();
        $tabs = str_repeat( "\t", $level );
        WP_CLI::line("$tabs $id. $name($level)");
        if( count( $evt->getChildEvents() ) > 0 ) {
            foreach( $evt->getChildEvents() as $child ) {
                $this->showEvent( $child, ++$level );
            }
        }
    }

    private function getEvent( Event $evt, int $descendantId ) {
        static $attempts = 0;
        $test = $evt->getID();
        if( $descendantId === $evt->getID() ) return $evt;

        if( count( $evt->getChildEvents() ) > 0 ) {
            if( ++$attempts > 10 ) return null;
            foreach( $evt->getChildEvents() as $child ) {
                $test = $child->getID();
                if( $descendantId === $child->getID() ) {
                    return $child;
                }
                else { 
                    return $this->getEvent( $child, $descendantId );
                }
            }
        }
        else {
            return null;
        }
    }

}
