<?php
use api\events\EventManager;

WP_CLI::add_command( 'tennis events', 'EventCommands' );

/**
 * Implements all commands for creating and deleting Events
 */
class EventCommands extends WP_CLI_Command {

    /**
     * Show Events for a Club
     *
     * ## OPTIONS
     *
     * <clubId>
     * : The numeric Id of the tennis club
     * 
     * ## EXAMPLES
     *
     *     wp tennis show events 260
     *
     * @when after_wp_load
     */
    function show( $args, $assoc_args ) {
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
     * Create a new event; standalone or associated with a club
     *
     * ## OPTIONS
     * <eventname>
     * :The name of the event
     * 
     * [--<field>=<value>]
     * :The club id to attach to the created event
     * 
     *
     * ## EXAMPLES
     *
     *     # Create event for club  with id 2
     *     $ wp tennis events create "My New Event" --clubId=2
     *
     * @when after_wp_load
     */
    function create( $args, $assoc_args ) {
        list( $evtName ) = $args;
        $clubId = array_key_exists( 'clubId', $assoc_args )  ? $assoc_args["clubId"] : 0;

        try {
            if( $clubId > 0 ) {
                $club = Club::get( $clubId );
                if( is_null( $club ) ) {
                    WP_CLI::error( sprintf("Club with id=%d does not exist.", $clubId ) );
                }

                $event = new Event( $evtName );
                //TODO: get event type from args
                $event->setEventType( EventType::TOURNAMENT );
                $event->addClub( $club );
                $event->save();
                WP_CLI::success(sprintf("Created event (%d) '%s' attached to %s'", $event->getID(), $event->getName(), $club->getName() ) );
            }
            else {
                $event = new Event( $evtName );
                $event->setEventType( EventType::TOURNAMENT );
                $event->save();
                WP_CLI::success(sprintf("Created event (%d) '%s'", $event->getID(), $event->getName() ) );
            }
        }
        catch ( Exception $ex ) {
            WP_CLI::error( sprintf( "Create event failed: %s", $ex->getMessage() ) );
        }
    }

    /**
     * Create a new sub-event
     *
     * ## OPTIONS
     * <eventname>
     * :The name of the event
     * 
     * <parentId>
     * :The id of the parent event
     * 
     * [<matchtype>]
     * :The type of matches in the brackets. Where 1.1 is mens singles; 2.1 is women's singles etc.
     * ---
     * default: 1.1
     * options:
     *   - 1.1
     *   - 2.1
     *   - 1.2
     *   - 2.2
     *   - 2.3
     * ---
     * 
     * [<format>]
     * :The format of brackets. e.g. selim is single elimination
     * ---
     * default: selim
     * options:
     *   - selim
     *   - delim
     *   - games
     *   - sets
     * ---
     * 
     * ## EXAMPLES
     *
     *     # Create sub event with parent id=1  and which defaults to men's singles
     *     $ wp tennis events createsub "Guys playing other guys" 1
     * 
     *     # Create sub event with parent id=1 and with match type of mixed doubles
     *     $ wp tennis events createsub "Name of event" 1 mixedoubles
     *
     * @when after_wp_load
     */
    function createsub( $args, $assoc_args ) {
        list( $evtName, $parentId, $matchType, $format ) = $args;
        $matchType = (float) $matchType;
        try {
            $parentEvent = Event::get( $parentId );
            $subEvent = new Event( $evtName );
            $parentEvent->addChild( $subEvent );
            $subEvent->setMatchType( $matchType );
            $subEvent->setFormat( $format );
            $parentEvent->save();
            WP_CLI::success(sprintf("wp tennis events createsub ... Added sub event '%s' to parent event '%s'",$evtName, $parentEvent->getName() ) );
        }
        catch ( Exception $ex ) {
            WP_CLI::error( sprintf( "wp tennis events createsub ... failed: %s", $ex->getMessage() ) );
        }
    }

    /**
     * Delete an event. This will delete all child events and tournaments!!!
     *
     * ## OPTIONS
     * <eventId>
     * :The Id of the event
     * 
     * ## EXAMPLES
     *
     *     # Delete event with id 6
     *     $ wp tennis event delete 6
     *
     * @when after_wp_load
     */
    public function delete( $args, $assoc_args ) {
        
        list( $eventId ) = $args;

        Event::deleteEvent( $eventId );

        WP_CLI::success("Deleted event with id $eventId");
    }
    
    /**
     * Attach a club to an event
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
     *     # Attach club 1 to event 6
     *     $ wp tennis event attach 6 1
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

        if( $event->addClub( $club ) ) {
            wP_CLI::success( sprintf("Attached '%s' to '%s'", $club->getName(), $event->getName() ) );
        }
        else {
            WP_CLI::error( sprintf("Unable to attach '%s' to '%s'", $club->getName(), $event->getName() ) );
        }

    }

    /**
     * 
     */
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

}
