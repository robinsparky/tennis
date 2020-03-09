<?php

WP_CLI::add_command( 'tennis signup', 'SignupCommands' );

/**
 * Implements all commands for manipulating tennis event signup
 */
class SignupCommands extends WP_CLI_Command {

    /**
     * Shows the Signup for an Event.
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     # Display the signup for club 2, event 6.
     *     $ wp tennis signup show --clubId=2 --eventId=6
     * 
     *     # Display the draw for club and event defined in the tennis command environment.
     *     $ wp tennis signup show
     *
     * @when after_wp_load
     */
    function show( $args, $assoc_args ) {

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
                $bracket = $target->getWinnersBracket();
                WP_CLI::line( "Signup for '$evtName' at '$name'");
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                $items = array();
                $entrants = $td->getSignup();
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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                try {
                    if( $td->addEntrant( $player, $seed ) ) {
                        WP_CLI::success("tennis signup add ... signed up $player");
                    }
                    else {
                        WP_CLI::error("tennis signup add ... unable to signed up $player");
                    }
                }
                catch( Exception $ex ) {
                    $mess = $ex->getMessage();
                    WP_CLI::error("tennis signup add ... unable to signed up $player because '$mess'" );
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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                try {
                    if( $td->removeEntrant( $player) ) {
                        WP_CLI::success("tennis signup remove ... $player");
                    }
                    else {
                        WP_CLI::error("tennis signup remove ... unable to remove $player");
                    }
                }
                catch( Exception $ex ) {
                    $mess = $ex->getMessage();
                    WP_CLI::error("tennis signup remove ... unable to remove $player because '$mess'" );
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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                if( $td->removeSignup() ) {
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

    /**
     * Move a postion to another position in the signup.
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * <source>
     * The source position number
     * 
     * <destination>
     * The destination position number
     * 
     * ## EXAMPLES
     *
     *  wp tennis signup move 10 16 bracketname
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();
        list( $clubId, $eventId ) = $support->getEnvError();

        error_clear_last();
        list( $source, $dest, $bracketName ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... source destination bracketname ");
            exit;
        }
        
        $fromId = "M($eventId,$source)";
        $toId   = "M($eventId,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                $td = new TournamentDirector( $target );
                if( $td->moveEntrant( $source, $dest ) ) {
                    WP_CLI::success("Position moved.");
                }
                else {
                    WP_CLI::warning("Position was not moved");
                }
            }
            else {
                WP_CLI::warning( "tennis signup move ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup move ... could not find any events for club with Id '$clubId'" );
        }

    }

    /**
     * Resequence the signup.
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *  wp tennis signup resequence bracketname
     *
     * @when after_wp_load
     */
    function resequence( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();
        list( $clubId, $eventId) = $support->getEnvError();
        error_clear_last();
        list( $bracketName ) = $args;
        $last_error = error_get_last();
        if( !is_null( $last_error  ) ) {
            WP_CLI::error("Wrong args for ... bracketname ");
            exit;
        }

        // error_clear_last();
        // list( $source, $dest ) = $args;
        // $last_error = error_get_last();
        // if( !is_null( $last_error  ) ) {
        //     WP_CLI::error("Wrong args for ... source destination ");
        //     exit;
        // }

        $evts = Event::find( array( "club" => $clubId ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = $support->getEventRecursively( $evt, $eventId );
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
            if( $found ) {
                $club = Club::get( $clubId );
                $name = $club->getName();
                $evtName = $target->getName();
                try {
                    $affected = $target->resequenceSignup();
                    WP_CLI::success("Signup resequenced $affected rows.");
                }
                catch( Exception $ex ) {
                    WP_CLI::error( $ex->getMessage() );
                }
            }
            else {
                WP_CLI::warning( "tennis signup resequence ... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup resequence ... could not find any events for club with Id '$clubId'" );
        }

    }

    /**
     * Load the signup for this tennis event from an XML file
     * The target club and event must first be set using 'tennis env'
     * 
     * ## EXAMPLES
     *
     *     wp tennis signup load <filename>
     *
     * @when after_wp_load
     */
    function load( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        list( $filename ) = $args;

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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                $ents = $this->readDatabase( $filename );
                $num = 0;
                try {
                    foreach( $ents as $ent ) {
                        $name = $ent['name'];
                        $seed = isset( $ent['seed'] ) ? (int)$ent['seed'] : 0;
                        $td->addEntrant($name,$seed);
                        ++$num;
                        $player = sprintf("Added: %s(%d)", $name, $seed );
                        WP_CLI::line( $player );
                    }
                    if( $num > 0 ) {
                        WP_CLI::success("Added $num players");
                    }
                    else {
                        WP_CLI::error("Failed to load any players.");
                    }
                }
                catch( Exception $ex ) {
                    $mess = $ex->getMessage();
                    WP_CLI::error("tennis signup load ... unable to load $player because '$mess'" );
                }
            }
            else {
                WP_CLI::warning( "tennis signup load... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis signup load ... could not find any events for club with Id '$clubId'" );
        }
    }

    
    private function readDatabase( $filename ) 
    {
        // read the XML database of players
        $data = implode( "", file( $filename ) );

        if( false === $data) {
            WP_CLI::error("No such file $filename");
        }

        $parser = xml_parser_create();
        xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
        xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
        xml_parse_into_struct( $parser, $data, $values, $tags );
        xml_parser_free( $parser );

        // $err = sprintf("XML error: %s at line %d"
        //               ,xml_error_string(xml_get_error_code( $parser ) )
        //               ,xml_get_current_line_number( $parser ) );
        // print_r($err);

        $tdb = array();
        // loop through the structures
        foreach ( $tags as $key=>$val ) {
            if ( $key == "player" ) {
                $playerData = $val;
                // each contiguous pair of array entries are the 
                // lower and upper range for each player definition
                for ( $i=0; $i < count( $playerData ); $i += 2 )  {
                    $offset = $playerData[$i] + 1;
                    $len = $playerData[$i + 1] - $offset;
                    $tdb[] = $this->parsePlayer( array_slice( $values, $offset, $len ) );
                }
            } else {
                continue;
            }
        }
        return $tdb;
    }

    private function parsePlayer( $mvalues ) 
    {
        for ( $i=0; $i < count( $mvalues ); $i++ ) {
            $p[$mvalues[$i]["tag"]] = $mvalues[$i]["value"];
        }
        return $p; //new Entrant($p['name'],p['seed']);
    }

}
