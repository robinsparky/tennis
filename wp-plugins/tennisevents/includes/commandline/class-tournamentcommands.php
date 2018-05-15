<?php

WP_CLI::add_command( 'tennis tourney', 'TournamentCommands' );

/**
 * Implements all commands for manipulating a tournament's tennis matches
 */
class TournamentCommands extends WP_CLI_Command {

    /**
     * Shows Matches for a Tournament
     *
     * ## OPTIONS
     * 
     *
     * ## EXAMPLES
     *
     *     # Show matches for club 2, event 6.
     *     $ wp tennis tourney show --clubId=2 --eventId=6
     * 
     *     # Show matches for club and event defined in the tennis command environment.
     *     $ wp tennis tourney show
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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                WP_CLI::line( sprintf( "Matches for '%s' at '%s'", $evtName, $name ) );
                WP_CLI::line( sprintf( "%s Bracket: %d Rounds", $bracket->getName(), $td->totalRounds() ) );
                $matches = $td->getMatches();
                $umpire  = $td->getChairUmpire();
                $items   = array();
                foreach( $matches as $match ) {
                    $round   = $match->getRoundNumber();
                    $mn      = $match->getMatchNumber();
                    $status  = $umpire->matchStatus( $match );
                    $score   = $umpire->strGetScores( $match );
                    $winner  = $umpire->matchWinner( $match );
                    $winner  = is_null( $winner ) ? 'tba': $winner->getName();
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
                                    , "Comments" => $cmts
                                    , "Winner" => $winner );
                }
                WP_CLI\Utils\format_items( 'table', $items, array( 'Round', 'Match Number', 'Status', 'Score', 'Home Name', 'Home Seed', 'Visitor Name', 'Visitor Seed', 'Comments', 'Winner' ) );
            }
            else {
                WP_CLI::warning( "tennis display match ... could not event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis display match ... could not any events for club with Id '$clubId'" );
        }
    }

    /**
     * Create the preliminary rounds for the current signup
     * The target club and event must first be set using 'tennis env set'
     *
     * ## OPTIONS
     * 
     * [<method>]
     * : Choose algorithm
     * ---
     * default: recurse
     * options:
     *   - recurse
     *   - challenger
     *   - bye
     * ---
     * 
     * [--shuffle=<values>]
     * : If present causes draw to be randomized
     * ---
     * default: no
     * options:
     *   - yes
     *   - no
     * ---
     * 
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney initialize challenger
     *  wp tennis tourney initialize shuffle
     *
     * @when after_wp_load
     */
    function initialize( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list($method) = $args;

        $shuffle = $assoc_args["shuffle"];
        if( strcasecmp("yes", $shuffle) === 0 ) $shuffle = true;
        else $shuffle = false;

        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        
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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                
                try {
                    $numMatches = $td->schedulePreliminaryRounds( $method, $shuffle );
                    if( $numMatches > 0 ) {
                        WP_CLI::success( "tennis match initialize ... generated $numMatches preliminary matches" );
                    }
                    else {
                        throw new Exception( "Failed to generate any matches." );
                    }
                }
                catch( Exception $ex ) { 
                    WP_CLI::error( sprintf( "tennis match initialize ... %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match initialize... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match initialize... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Delete all matches for this tennis tournament
     * The target club and event must first be set using 'tennis env'
     * 
     * ## OPTIONS
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney reset
     *     wp tennis tourney reset --force=true
     *
     * @when after_wp_load
     */
    function reset( $args, $assoc_args ) {

        CmdlineSupport::preCondtion();

        $force  = array_key_exists( 'force', $assoc_args )  ? $assoc_args["force"] : '';
        if( strcasecmp( $force,'true') === 0 ) $force = true;
        else $force = false;

        $env = CmdlineSupport::instance()->getEnvError();
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
                    if( $td->removeBrackets( $force ) ) {
                        WP_CLI::success("tennis tourney reset ... accomplished.");
                    }
                    else {
                        WP_CLI::error("tennis tourney reset ... unable to reset.");
                    }
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney reset ... unable to reset: %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Move a match to a given point in the draw.
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
     *     wp tennis tourney move 1 10 16
     *     wp tennis tourney move 1 10 16 comments='This is a comment'
     *
     * @when after_wp_load
     */
    function move( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list( $round, $source, $dest ) = $args;
        list( $clubId, $eventId ) = $support->getEnvError();
        
        $fromId = "M($eventId,$round,$source)";
        $toId   = "M($eventId,$round,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved from $fromId to $toId on $stamp";

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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                if( $td->matchMove($bracket->getName(), $round, $source, $dest, $cmts ) ) {
                    WP_CLI::success("Match moved.");
                }
                else {
                    WP_CLI::warning("Match was not moved");
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }

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
     *     wp tennis tourney moveby 0 1 2
     *     wp tennis tourney moveby 1 10 -3 --comments='This is a comment'
     *
     * @when after_wp_load
     */
    function moveby( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        list($round, $source, $steps ) = $args;
        list( $clubId, $eventId ) = $support->getEnvError();
        
        $result = 0;
        $dest   = $source + $steps;
        if( $steps > 256 || $dest < 1 ) {
            WP_CLI::error("Invalid step value");
        }

        $fromId = "Match($round,$source)";
        $toId   = "Match($round,$dest)";
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $cmts = array_key_exists( "comments", $assoc_args ) ? $assoc_args["comments"] : "Commandline: moved up from $fromId by $steps to $toId on $stamp";

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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                if( $td->matchMove($bracket->getName(), $round, $source, $dest, $cmts ) ) {
                    WP_CLI::success("Match moved.");
                }
                else {
                    WP_CLI::warning("Match was not moved");
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }
    }
    
    /**
     * Approve the preliminary matches
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney approve
     *
     * @when after_wp_load
     */
    function approve( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();
        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;
        
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
                $td = new TournamentDirector( $target, $target->getMatchType() );
                try {
                    $td->approve();
                    // $adj = $td->getAdjacencyMatrix();
                    // print_r( $adj );
                    WP_CLI::line(sprintf("Total Rounds=%d", $td->totalRounds()));
                    $records = $td->strAdjacencyMatrix();
                    foreach( $records as $line ) {
                        WP_CLI::line( $line );
                    }
                    // WP_CLI\Utils\format_items( 'table', $items, $headings );
                    // WP_CLI::success("tennis tourney approve ... accomplished.");
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney approve ...  %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney ... could not find any events for club with Id '$clubId'" );
        }
    }

    
    /**
     * Record the score for a match
     * The target club and event must first be set using 'tennis env set'
     * 
     * ## OPTIONS
     * 
     * <roundnum>
     * : The round number
     * 
     * <matchnum>
     * : The match number
     * 
     * <setnum>
     * : The set number
     * 
     * [--type=<type>]
     * : 
     * ---
     * default: home
     * options:
     *   - home
     *   - visitor
     * ---
     * 
     * [--<field>=<value>]
     * :The home player's tie breaker score
     * 
     * ## EXAMPLES
     *
     *  wp tennis tourney score 1 3 2 --home=6 --visitor=6
     *  wp tennis tourney score 1 3 2 --hometb=7 --visitortb=3
     *
     * @when after_wp_load
     */
    function score( $args, $assoc_args ) {

        $support = CmdlineSupport::preCondtion();

        $env = $support->getEnvError();
        list( $clubId, $eventId ) = $env;

        //TODO: FIX THIS >>> need to identify the bracket!!!!!!!!s
        //Get the round, match and set numbers from the args
        list( $roundnum, $matchnum, $setnum ) = $args;
        
        
        $home      = array_key_exists( 'home', $assoc_args )  ? $assoc_args["home"] : 0;
        $visitor   = array_key_exists( 'visitor', $assoc_args )  ? $assoc_args["visitor"] : 0;
        $hometb    = array_key_exists( 'hometb', $assoc_args )  ? $assoc_args["hometb"] : 0;
        $visitortb = array_key_exists( 'visitortb', $assoc_args )  ? $assoc_args["visitortb"] : 0;

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
                $bracket = $target->getWinnersBracket();
                $td = new TournamentDirector( $target, $bracket->getMatchType() );
                try {
                    $match = $bracket->getMatch( $roundnum, $matchnum );
                    if( !isset( $match ) ) {
                        throw new InvalidTournamentException("No such match.");
                    }

                    $umpire = $td->getChairUmpire();
                    $umpire->recordScores($match, $setnum, $home, $hometb, $visitor, $visitortb );
                    WP_CLI::success( sprintf("Recorded score %d(%d) : %d(%d) in match '%s'", $home, $hometb, $visitor, $visitortb, $match->title() ) );
                }
                catch( Exception $ex ) {
                    WP_CLI::error( sprintf("tennis tourney score ...  %s", $ex->getMessage() ) );
                }
            }
            else {
                WP_CLI::warning( "tennis match tourney score... could not find event with Id '$eventId' for club with Id '$clubId'" );
            }
        }
        else {
            WP_CLI::warning( "tennis match tourney score ... could not find any events for club with Id '$clubId'" );
        }
    }

    /**
     * Simulate alternatives for preliminary rounds
     * ## OPTIONS
     * <n>
     * : Number of players
     * 
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney simulate 15
     *
     * @when before_wp_load
     */

    function simulate( $args, $assoc_args ) {

        list( $n ) = $args;

        // $byePoss = $this->byePossibilities( $n );
        // $titles = array_keys( $byePoss[0] );
        // WP_CLI::line("Using Byes");
        // WP_CLI\Utils\format_items( 'table', $byePoss, $titles );
        
        // $challengerPoss = $this->challengerPossibilities( $n );
        // $titles = array_keys( $challengerPoss[0] );
        // WP_CLI::line("Using Challenger Round");
        // WP_CLI\Utils\format_items( 'table', $challengerPoss, $titles );

        $defbyes        = $this->byeCount( $n );
        $defchallengers = $this->challengerCount( $n );
        WP_CLI::line(sprintf("Default # of byes=%d, Default # of challengers=%d", $defbyes, $defchallengers ) );

        if( $defchallengers < $defbyes ) {
            WP_CLI::success("Use $defchallengers challengers.");
        }
        else {
            WP_CLI::success("Use $defbyes byes");
        }
    }

    /**
     * Test PHP code
     * 
     * ## OPTIONS
     * <n>
     * : Number of players
     * 
     * ## EXAMPLES
     *
     *     wp tennis tourney test 15
     *
     * @when before_wp_load
     */
    function test( $args, $assoc_args ) {        
        /*
         * 1. Reference test
         */
        $array = array(00, 11, 22, 33, 44, 55, 66, 77, 88, 99);
        $this->ref($array, 2, $ref);
        $ref[0] = 'xxxxxxxxx';
        var_dump($ref);
        var_dump($array);

        /*
         * 2. Overloaded constructors test
         * Proved that this cannot handle references in the overloaded functions' args
         */
        // $obj1 = new stdClass;
        // $obj1->name = 'Robin';
        // try {
        // $test = new Test( $obj1 );
        // }
        // catch( Exception $ex ) {
        //     WP_CLI::error( $ex->getMessage() );
        // }
    }

    /**
     * Calculates the number of byes in round 1
     * to cause the number of players in round 2 be a power of 2
     * The number of players and the number of byes must be 
     * of the same parity (i.e.both even or both odd)
     */
    private function byeCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp  =  $this->calculateExponent( $n );
        $highexp = $lowexp + 1;
        $target  = pow( 2, $lowexp ); //or 2 * $lowexp
        $result  = 2 * $target - $n; // target = (n + b) / 2
        // echo "$loc: n=$n; lowexp=$lowexp; highexp=$highexp; target=$target; byes=$result; " . PHP_EOL;
        if( !($n & 1) && ($result & 1) ) $result = -1;
        elseif( ($n & 1) && !($result & 1) ) $result = -1;
        elseif( $this->isPowerOf2( $n ) ) $result = 0;
        
        // $elimRange = range( 1, $pow2 - 1 );
        // foreach( $elimRange as $byes ) {
        //     $round1 = $n - $byes;
        //     if( $round1 & 1 ) continue;
        //     else {
        //         $round2 = $byes + $round1 / 2;
        //         if( $this->isPowerOf2( $round2 ) ) {
        //             $result = $byes;
        //             break;
        //         }
        //     }
        // }
        return $result;
    }

    /**
     * Calculate the number of challengers (if using early round 0)
     * to bring round 1 to a power of 2
     * The number of players and the number of challengers must be of opposite parity
     * (i.e. if one is odd the other must be even and visa versa )
     */
    private function challengerCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp   =  $this->calculateExponent( $n );
        $highexp  = $lowexp + 1;
        $target   = pow(2, $lowexp );
        $result   = $n - $target;
        $round1   = $n - $result; // players in r1 = target = (n - 2p + p)
        // echo "$loc: n=$n; lowexp=$lowexp; highexp=$highexp; round1=$round1; target=$target; challengers=$result; " . PHP_EOL;
        if( ($round1 & 1) ) $result = -1;
        elseif( $this->isPowerOf2( $n ) ) $result = 0;
        
        // $elimRange = range( 1, $pow2 - 1 );
        // foreach( $elimRange as $challengers ) {
        //     if( $challengers & 1 ) continue;
        //     $round1 = $n - $challengers;
        //     if( $round1 & 1 ) continue;
        //     if( $this->isPowerOf2( $round1 ) ) {
        //         $result = $challengers;
        //         break;
        //     }
        // }

        return $result;
    }

    private function byePossibilities( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = array();
        if( $n <= 8 || $n > pow( 2, 8 ) ) return $result;

        $exp2 =  $this->calculateExponent( $n );
        $pow2 = pow(2, $exp2 );
        $maxToEliminate = $n - $pow2;
        $elimRange = range( 1, $pow2 - 1 );

        foreach( $elimRange as $byes ) {
            $possibility = array();
            $possibility["Signup"] = $n;
            $possibility["Elimination"] = $byes;
            $possibility["Round 1"] = $n - $byes;
            $possibility["Round 2"] = $byes + ($n - $byes)/2;
            array_push ($result, $possibility );
        }

        return $result;
    }

    private function challengerPossibilities( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = array();
        if( $n < 0 || $n > pow( 2, 8 ) && $n & 1 ) return $result;

        $exp2 =  $this->calculateExponent( $n );
        $pow2 = pow(2, $exp2 );
        $maxToEliminate = $n - $pow2;
        $elimRange = range( 1, $pow2 - 1 );
        
        foreach( $elimRange as $challengers ) {
            $possibility = array();
            $possibility["Signup"] = $n;
            $possibility["Elimination"] = $challengers;
            $possibility["Round 0"] = 2 * $challengers;
            $possibility["Round 1"] = $n - $challengers;
            array_push ($result, $possibility );
        }

        return $result;
    }

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is less than that size (or integer)
     */
	private function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) > $size ) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }
    
    /**
     * Determine if this integer is a power of 2
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
     */
	private function isPowerOf2( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) === $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }



    private function ref( &$array, int $idx = 1, &$ref = array() )
    {
            //$ref = array();
            $ref[] = &$array[$idx];
    }

}

abstract class OverloadedConstructors {
   public final function __construct() {
      $self = new ReflectionClass($this);
      $constructors = array_filter($self->getMethods(ReflectionMethod::IS_PUBLIC), function(ReflectionMethod $m) {
         return substr($m->name, 0, 11) === '__construct';
      });
      if(sizeof($constructors) === 0)
         trigger_error('The class ' . get_called_class() . ' does not provide a valid constructor.', E_USER_ERROR);
      $number = func_num_args();
      $arguments = func_get_args();
      $ref_arguments = array();
      foreach($constructors as $constructor) {
         if(($number >= $constructor->getNumberOfRequiredParameters()) &&
            ($number <= $constructor->getNumberOfParameters())) {
            $parameters = $constructor->getParameters();
            reset($parameters);
            foreach($arguments as $arg) {
               $parameter = current($parameters);
               if($parameter->isArray()) {
                  if(!is_array($arg)) {
                     continue 2;
                  }
               } 
               elseif(($expectedClass = $parameter->getClass()) !== null) {
                  if(!(is_object($arg) && $expectedClass->isInstance($arg))) {
                     continue 2;
                  }
               }
               next($parameters);
            }
            $constructor->invokeArgs($this, $arguments);
            return;
         }
      }
      trigger_error('The required constructor for the class ' . get_called_class() . ' did not exist.', E_USER_ERROR);
   }
}

class Test extends OverloadedConstructors {
    public function __construct1(array $arg) {
        print_r( $arg );
        WP_CLI::success('First construct');
    }

    public function __construct2(stdClass &$test) {
        print_r( $test );
        WP_CLI::success('Second construct');
    }

    public function __construct3($optional = null) {
        print_r( $optional );
        WP_CLI::success('Third construct');
    }
 }
