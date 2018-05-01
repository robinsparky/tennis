<?php

WP_CLI::add_command( 'tennis display', 'DisplayCommands' );

/**
 * Implements all commands for displaying tennis objects.
 * 
 *
 * ## EXAMPLES
 *
 *     # Display all clubs.
 *     $ wp tennis display clubs
 *     
 *
 *     # Display all events for a club
 *     $ wp tennis display events
 *     
 *
 *     # Display the draw for an event
 *     $ wp tennis display draw
 *     
 *
 *     # Display all matches for an event
 *     $ wp tennis display match
 *     
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
        if( count( $allClubs ) > 0 ) {
            WP_CLI::line( "List of Tennis Clubs" );
            $items = array();
            foreach( $allClubs as $club ) {
                $name = $club->getName();
                $id = $club->getID();
                $items[] = array( 'ID' => $club->getID(),'Name' => $club->getName() );
            }
            WP_CLI\Utils\format_items( 'table', $items, array( 'ID', 'Name' ) );
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
            WP_CLI::line( "Events For '$name'" );
            WP_CLI::line( "--------------------------------------------------");
            $lineNum = 0.0;
            foreach( $allEvents as $evt ) {
                $lineNum += 1.0;
                $this->showEvent( $evt, $lineNum, 0 );
            }
        }
        else {
            WP_CLI::warning( "No events were found for club with Id '$clubId'" );
        }
    }

    /**
     * Display All Clubs with their Events
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     wp tennis display clubsEvents
     *
     * @when after_wp_load
     */
    function clubsEvents( $args, $assoc_args ) {
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
     * Displays Both the Signup and Matches (aka the Draw) for an Event.
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     # Display the signup and matches for club 2, event 6.
     *     $ wp tennis display both --clubId=2 --eventId=6
     * 
     *     # Display the signup and matches for club and event defined in the tennis command environment.
     *     $ wp tennis display draw
     *
     * @when after_wp_load
     */
    function draw( $args, $assoc_args ) {
        $this->signup( $args, $assoc_args );
        $this->match( $args, $assoc_args );
    }

    /**
     * Displays the Signup for an Event.
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     # Display the signup for club 2, event 6.
     *     $ wp tennis display signup --clubId=2 --eventId=6
     * 
     *     # Display the draw for club and event defined in the tennis command environment.
     *     $ wp tennis display signup
     *
     * @when after_wp_load
     */
    function signup( $args, $assoc_args ) {

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
                WP_CLI::line( "Signup for '$evtName' at '$name'");
                $td = new TournamentDirector( $target, $target->getMatchType() );
                $items = array();
                $entrants = $td->getDraw();
                foreach( $entrants as $ent ) {
                    $seed = $ent->getSeed() > 0 ? $ent->getSeed() : ''; 
                    $items[] = array( "Position" => $ent->getPosition()
                                    , "Name" => $ent->getName()
                                    , "Seed" => $seed );
                }
                WP_CLI\Utils\format_items( 'table', $items, array( 'Position', 'Name', 'Seed' ) );
            }
            else {
                WP_CLI::warning( "tennis display draw ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis display draw ... could not find any events for club with Id '$clubId'" );
        }
    }
    
    /**
     * Displays Matches for an Event.
     *
     * ## OPTIONS
     * 
     *
     * ## EXAMPLES
     *
     *     # Display matches for club 2, event 6.
     *     $ wp tennis display match --clubId=2 --eventId=6
     * 
     *     # Display matches for club and event defined in the tennis command environment.
     *     $ wp tennis display match
     *
     * @when after_wp_load
     */
    function match( $args, $assoc_args ) {
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
                $target =  CmdlineSupport::get_instance()->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                WP_CLI::line( "Matches for '$evtName' at '$name'");
                $td = new TournamentDirector( $target, $target->getMatchType() );
                $matches = $td->getMatches();
                $umpire  = $td->getChairUmpire();
                $items   = array();
                foreach( $matches as $match ) {
                    $round   = $match->getRoundNumber();
                    $mn      = $match->getMatchNumber();
                    $status  = $umpire->matchStatus( $match );
                    $score   = $umpire->strGetScores( $match );

                    $home    = $match->getHomeEntrant();
                    $hname   = sprintf( "%d %s", $home->getPosition(), $home->getName() );
                    $hseed   = $home->getSeed() > 0 ? $home->getSeed() : '';

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
                                    , "Comments" => $cmts);
                }
                WP_CLI\Utils\format_items( 'table', $items, array( 'Round', 'Match Number', 'Status', 'Score', 'Home Name', 'Home Seed', 'Visitor Name', 'Visitor Seed', 'Comments' ) );
            }
            else {
                WP_CLI::warning( "tennis display match ... could not event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis display match ... could not any events for club with Id '$clubId'" );
        }
    }
    
    private function showEvent( Event $evt, float $lineNum = 1.0, int $level = 0 ) {
        $id = $evt->getID();
        $name = $evt->getName();
        $tabs = str_repeat( " ", $level );
        if( 0 === $level ) $lineNum = floor( $lineNum );
        WP_CLI::line("$tabs $lineNum. $name ($id)");
        if( count( $evt->getChildEvents() ) > 0 ) {
            ++$level;
            foreach( $evt->getChildEvents() as $child ) {
                $lineNum += 0.1;
                $this->showEvent( $child, $lineNum, $level );
            }
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
