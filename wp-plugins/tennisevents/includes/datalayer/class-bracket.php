<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstractdata.php');
// require_once('class-event.php');
// require_once('class-court.php');

/** 
 * Data and functions for Tennis Brackets
 * @class  Bracket
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Bracket extends AbstractData
{ 
    //Known names of brackets for Single and Double Elimination brackets
    public const WINNERS = "Winners";
    public const LOSERS  = "Losers";
    public const CONSOLATION = "Consolation";

	//table name
	private static $tablename = 'tennis_bracket';

	//Attributes
    private $event_ID;
    private $bracket_num = 0;
    private $is_approved = false;
    private $name;

    //Event to which this bracket belongs ( fetched using $event_ID )
    private $event;

    //Matches in this bracket
    private $matches;
    private $matchesToBeDeleted = array();
    private $matchHierarchy = array();

    //Bracket template
    private $bracketTemplate;
	
	/*************** Static methods ******************/
	/**
	 * Search not used
	 */
	static public function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();

		return $col;
	}

	/**
	 * Find Brackets referenced in a given Event
	 */
	static public function find( ...$fk_criteria ) {
		global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$rows;

        //All clubs belonging to specified Event
        $eventId = $fk_criteria[0];            
        $sql = "SELECT event_ID, bracket_num, is_approved, `name` 
                FROM $table 
                WHERE event_ID = %d;";
        $safe = $wpdb->prepare( $sql, $eventId );
        $rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log( sprintf("%s(E(%d)) -> %d rows returned.", $loc, $eventId, $wpdb->num_rows ) );

		foreach( $rows as $row ) {
            $obj = new Bracket;
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;

	}

	/**
	 * Get instance of a Bracket using it's primary key (event_ID, bracket_num)
	 */
    static public function get( int ... $pks ) {
        global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

		$table = $wpdb->prefix . self::$tablename;
		$sql = "select event_ID, bracket_num, is_approved, name from $table where event_ID=%d and bracket_num=%d";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

        error_log( sprintf( "%s(B(%d,%d)) -> %d rows returned.", $loc, $pks[0], $pks[1], $wpdb->num_rows ) );
        
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new Bracket;
			self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}

	/*************** Instance Methods ****************/

	public function __destruct() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log("$loc ... ");

		if( isset( $this->matches ) ) {
			foreach($this->matches as &$match) {
				unset( $match );
			}
        }
        
		if( isset( $this->matchesToBeDeleted ) ) {
			foreach($this->matchesToBeDeleted as &$match) {
				unset( $match );
			}
		}
    }
    
    public function setDirty() {
        if( isset( $this->event ) ) $this->event->setDirty();
        //error_log( sprintf("%s(%s) set dirty", __CLASS__, $this->toString() ) );
        return parent::setDirty();
    }


    public function setBracketNumber( int $bracketnum ) {
        $this->bracket_num = $bracket;
        return $this->setDirty();
    }

    public function getBracketNumber() {
        return $this->bracket_num;
    }

    /**
     * Set the name of this bracket
     */
	public function setName( string $name ) {
		$this->name = $name;
		return $this->setDirty();
	}
	
    /**
     * Get the name of this object
     */
    public function getName() {
        return isset( $this->name ) ? $this->name : sprintf( "Bracket %d", $this->bracket_num );
    }
    
    /**
     * Approve this bracket
     * This causes the match hierarchy to be constructed
     */
    public function approve( TournamentDirector $td ) {
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        $this->log->error_log($loc);

        if( 0 == $this->signupSize() ) {
            throw new InvalidBracketException( __("Bracket has no signup.", TennisEvents::TEXT_DOMAIN) );
        }

        $umpire = $td->getChairUmpire();
        $bracket->buildBracketTemplate( $umpire );
        $bt = $this->getBracketTemplate();
        $template = $bt->getTemplate();
        $this->buildMatchHierarchy();

        $this->is_approved = true;
        $this->setDirty();

        return $bt;
    }

    /**
     * Is this bracket approved?
     */
    public function isApproved() {
        return $this->is_approved;
    }

    /**
     * Returns ths associated event's ID
     * Defaults to 0
     */
    public function getEventId() {
        return isset( $this->event_ID ) ? $this->event_ID : 0;
    }

    /**
     * Set the event objecyt that owns this bracket
     */
    public function setEvent( Event &$event ) {
        $this->event = $event;
        $this->event_ID = $event->getID();
        $this->setDirty();
    }

    /**
     * Get the event for this bracket
     */
    public function getEvent( $force = false ) {
        if( !isset( $this->event ) || $force ) $this->fetchEvent();
        return $this->event;
    }

    /**
     * Determine the size of the signup
     * For the main draw, this is the actual signup for the event
     * For Losers or Consolation draw, the signup is one-half of the event signup
     * TODO: For other ... ???????????
     */
    public function signupSize() {
        $size =  $this->getEvent()->signupSize();
        if( self::WINNERS === $this->getName() ) {
            return $size;
        }
        elseif( self::LOSERS === $this->getName() ) {
            //TODO: Not true ... needs investigation
            return $size / 2; 
        }
        elseif( self::CONSOLATION === $this->getName() ) {
            return $size / 2;
        }
        return size;
    }

    /**
     * Get this bracket's signup
     * TODO: Figure out how to get losers/consolation signup
     */
    public function getSignup() {
        $result = array();
        if( self::WINNERS === $this->getName() ) {
            $result = $this->getEvent()->getSignup();
        }
        elseif( self::LOSERS === $this->getName() ) {
            
        }
        elseif( self::CONSOLATION === $this->getName() ) {
            
        }
        return $result;
    }
    
    /**
     * Create a new Match and add it to this Event.
	 * The Match must pass validity checks
	 * @param $round The round number for this match
	 * @param $matchType The type of match @see MatchType class
	 * @param $matchnum The match number if known
     * @param $home
     * @param $visitor
     * @param $bye Set to true if the new match is a bye
     * @return Match if successful; null otherwise
     */
    public function addNewMatch( int $round, float $matchType, $matchnum = 0, Entrant $home = null, Entrant $visitor = null, bool $bye = false ) {
		$result = null;

        if( isset( $home ) ) {
            $this->getMatches();
            $match = new Match( $this->getEvent()->getID(), $this->bracket_num, $round, $matchnum );
            $match->setIsBye( $bye );				
            $match->setMatchType( $matchType );
            $match->setBracket( $this );
            if( isset( $home ) ) {
                $match->setHomeEntrant( $home );
            }
            if( isset( $visitor ) ) {
                $match->setVisitorEntrant( $visitor );
            } 
            
            if( $match->isValid() ) {
                $this->matches[] = $match;
                $match->setBracket( $this );
                $this->setDirty();
                $result = $match;
            }
        }
        return $result;
    }

    /**
     * Add a Match to this Bracket
	 * The Match must pass validity checks
     * @param $match
	 * @return true if successful, false otherwise
     */
    public function addMatch( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;

        if( isset( $match ) ) {
            $matches = $this->getMatches();
            foreach( $matches as $m ) {
                //Using the compare attributes version of object equivalance
                if( $match == $m ) break;
            }
            $match->setBracket( $this );
            $match->isValid();
            $this->matches[] = $match;
            $match->setBracket( $this );
            $result = $this->setDirty();
		}
        
        return $result;
	}

    /**
     * Access all Matches in this Event sorted by round number then match number
	 * @param $force When set to true will force loading of matches
	 *               This will cause unsaved matches to be lost.
	 * @return Array of all matches for this event
     */
    public function getMatches( $force = false ):array {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( !isset( $this->matches ) || $force ) $this->fetchMatches();
        foreach( $this->matches as $match ) {
            $match->setBracket( $this );
        }
        usort( $this->matches, array( __CLASS__, 'sortByRoundMatchNumberAsc' ) );
        return $this->matches;
	}
	
    /**
     * Access all Matches in this Event for a specific round
	 * @param $rndnum The round number of interest
	 * @return Array of matches belonging to the round
     */
	public function getMatchesByRound( int $rndnum, $force = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

		$result = array();
		foreach( $this->getMatches( $force ) as $match ) {
			if( $match->getRoundNumber() === $rndnum ) {
				$result[] = $match;
			}
		}
        usort( $result, array( __CLASS__, 'sortByMatchNumberAsc' ) );
		return $result;
    }
    
    /**
     * Get a specific match in this Bracket
	 * @param $rndnum The round number
	 * @param $matchnum The match number
	 * @return Match if successful, null otherwise
     */
	public function getMatch( int $rndnum, int $matchnum, $force = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

		$result = null;
		foreach( $this->getMatches( $force ) as $match ) {
			if( $match->getRoundNumber() === $rndnum  && $match->getMatchNumber() === $matchnum ) {
				$result = $match;
			}
		}
		return $result;
	}

    /**
     * Get the number of matches in this total
     */
    public function numMatches():int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return count( $this->getMatches() );
	}
	
    /**
     * Get the number of matches in this Round
	 * @param $round The round number of the desired matches
	 * @return Count of matches in the given round
     */
    public function numMatchesByRound( int $round ):int {	
        $loc = __CLASS__ . "::" . __FUNCTION__;	
		return array_reduce( function ( $sum, $m ) use( $round ) { if( $m->getRound() === $round ) ++$sum; }, $this->getMatches(), 0);
    }
    
    /**
     * Get the number of byes in this bracket.
     * Note that the preliminary rounds must have already been scheduled.
     * @return number of byes
     */
    public function getNumberOfByes() {
		global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

        $byes = 0;
        $bracketTable = $wpdb->prefix . self::$tablename;
        $eventTable = $wpdb->prefix . "tennis_event";
        $matchTable = $wpdb->prefix . "tennis_match";
        $eventId = $this->getEventId();
        $bracketNum = $this->getBracketNumber();
      
        $sql = "SELECT count(*)
            from $eventTable as e
            inner join $bracketTable as b on b.event_ID = e.ID
            inner join $matchTable as m on m.event_ID = b.event_ID and m.bracket_num = b.bracket_num 
            where m.is_bye = 1 
            and e.ID = %d 
            and b.bracket_num = %d;";
        $safe = $wpdb->prepare( $sql, $this->getEventId(), $this->getBracketNumber() );
        $byes = $wpdb->get_var( $safe );

        error_log( sprintf("%s(E(%d)B(%d)) -> has %d byes.", $loc, $this->getEventId(), $this->getBracketNumber(), $byes ) );
        return $byes;
    }

    /**
     * Get the highest match number used in the given round
	 * in a tournament
     * @param $rn the round number of interest
	 * @return The maximum of all the match numbers in the round
     */
    public function maxMatchNumber( int $rn ):int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        global $wpdb;

        $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d;";
        $safe = $wpdb->prepare( $sql, $this->getID(), $this->bracket_num, $rn );
        $max = (int)$wpdb->get_var( $safe );

        return $max;
    }
	
	/**
	 * Remove the collection of Matches
	 */
	public function removeAllMatches() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
		if( isset( $this->matches ) ) {
			$i=0;
			foreach( $this->matches as $match ) {
				$this->matchesToBeDeleted[] = $match;
				$match = null;
			}
        }
        $this->matches = array();
		return $this->setDirty();
    }
    
    //TODO: Fix this ... use the owning event
	public function getMatchType() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

		if( $this->numMatches() > 0 ) {
			return $this->matches[0]->getMatchType();
		}
		else {
			return 0.0;
		}
	}

    /**
     * Get the BracketTemplate for matches in this Bracket
     */
    public function getBracketTemplate() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( is_null( $this->bracketTemplate ) ) {
            $this->bracketTemplate = new BracketTemplate();
        }
        return $this->bracketTemplate;
    }

    /**
     * Extract a copy of the scheduled preliminary round for this bracket.
     * The copy can be modified as desired without affecting the underlying template.
     * Rounds are counted starting at 1 which is the preliminary round.
     */
    public function extractPreliminaryRound() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $template = $this->getBracketTemplate()->getTemplate();
        //NOTE: use of clone below!
        $result = clone array_slice( $template, 0, 1 )[0];
        return $result;
    }

    /**
     * Get all scheduled rounds after the preliminary round
     */
    public function getScheduledRoundsOfn( ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $template = $this->getBracketTemplate()->getTemplate();
        return array_slice( $template, 1 );
    }

    /**
     * Get the number of scheduled rounds for this bracket
     */
    public function getNumberOfScheduledRounds() : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $result = 0;
        if( $this->isApproved() ) {
            $result = count( $this->getBracketTemplate()->getTemplate() );
        }
        return $result;
    }

    /**
     * Kicks off the building of the match template for this bracket
     * @param $umpire The Chair Umpire for these matches
     * @return Template loaded with match data
     */
    public function buildBracketTemplate( $umpire ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $bracketSignupSize = $this->signupSize();
        $numByes = $this->getNumberOfByes();
        $this->log->error_log("$loc: Bracket signup size=$bracketSignupSize; number of byes=$numByes ");

        $bt = $this->getBracketTemplate();
        $bt->build( $bracketSignupSize, $numByes );
        $template = $bt->getTemplate();

        if( count( $template ) < 1 || count( $template[0] ) < $bracketSignupSize/2 ) {
            throw new InvalidBracketException( __("Bracket template not built correctly!", TennisEvents::TEXT_DOMAIN) );
        }
        $this->matchToTemplate( $umpire, $template );

        return $template;
    }

    public function buildMatchHierarchy() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $bracketSignupSize = $this->signupSize();
        $numByes = $this->getNumberOfByes();
        $this->log->error_log("$loc: Bracket signup size=$bracketSignupSize; number of byes=$numByes ");

        $bt = $this->getBracketTemplate();
        $bt->build( $bracketSignupSize, $numByes );
        $template = $bt->getTemplate();
        $this->matchHierarchy = $this->templateToMatch( $template );
        $this->setDirty();

        return $this->matchHierarchy;
    }

    /**
     * Get the 2-dimensional array of matches for this bracket
     * @return Array of matches by round number, match number
     */
    public function getMatchHierarchy() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        //if approved and hierarchy not loaded yet then load from db
        if( count( $this->matchHierarchy ) < 1  && $this->isApproved() ) {
            $this->matchHierarchy = $this->loadMatchHierarchy();
        }
        return $this->matchHierarchy;
    }

    /**
     * Get the round of number.
     * If it is the first round, then round of is number who signed up
     * Otherwise it is the number expected to be playing in the given round.
     * @param $r The round number
     */
    public function roundOf( int $r ) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $bracketSignupSize = $this->signupSize();
        $result = $bracketSignupSize;
        if( $r <= 1 ) return $result;

        $exp = TournamentDirector::calculateExponent( $bracketSize );
        $result = $pow( 2, $exp ) / pow( 2, $r );
        return $result;        
    }

    /*----------------------------------------- Private Functions --------------------------------*/

    /**
     * This function loads entrant and match information to the template
     * @param $umpire The chair umpire for this tournament
     * @param $template Reference to the match template
     */
    private function matchToTemplate( $umpire, &$template ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);
        
        $eventSize = $this->signupSize();
        $numRounds = TournamentDirector::calculateExponent( $eventSize );
        $numToEliminate = $eventSize - pow( 2, $numRounds );
        $matchesInvolved   = 2 * $numToEliminate;
        
        for( $r = 1; $r <= $numRounds; $r++ ) {
            $dlist = $template[$r - 1];
            //$this->log->error_log( $dlist, "$loc: Round $r");
            $matches = $this->getMatchesByRound( $r );
            foreach( $matches as $match ) {
                $m = $match->getMatchNumber();
                $this->log->error_log("$loc: Found match M($r,$m");
                $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
                for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                    if( ( $dlist->current()->round === $r ) &&  ( $dlist->current()->match_num === $m ) ) {
                        $status  = $umpire->matchStatus( $match );
                        $score   = $umpire->tableGetScores( $match );
                        $winner  = $umpire->matchWinner( $match );
                        $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                        $home    = $match->getHomeEntrant();
                        $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                        $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';

                        $visitor = $match->getVisitorEntrant();
                        $vname   = 'tba';
                        $vseed   = '';
                        if( isset( $visitor ) ) {
                            $vname   = $visitor->getName();
                            $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                        }

                        $cmts    = $match->getComments();
                        $cmts    = isset( $cmts ) ? $cmts : '';
                        $this->log->error_log("$loc: M($r,$m) Home: $hname; Visitor: $vname");
                        $dlist->current()->home = empty($hseed) ? $hname : $hname . "($hseed)";
                        $dlist->current()->visitor = empty($vseed) ? $vname : $vname . "($vseed)";
                        $dlist->current()->score = $score;
                        $dlist->current()->status = $status;
                        $dlist->current()->comments = $cmts;
                        break;
                    } //if
                } //dlist
                $dlist->rewind();
            } //matches
            //$this->log->error_log( $dlist, "$loc: Modified Round $r");
        } //rounds
    }

    /**
     * This function loads existing Matches then uses the template
     * to add next match id's to them and create new matches where they don't yet exist.
     * NOTE: The preliminary round must have already been scheduled and approved.
     *       i.e. the matches for the preliminary must exist in the database.
     * @param $template The template to use for completing the network of matches
     * @return Array of matches by round and match number
     */
    private function templateToMatch( &$template ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);

        $loadedMatches = array();
        
        $eventSize = $this->signupSize();
        $numRounds = TournamentDirector::calculateExponent( $eventSize );

        //force loading existing matches from db
        $this->getMatches( true );
        
        for( $r = 1; $r <= $numRounds; $r++ ) {
            $dlist = $template[ $r - 1 ];
            $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                $m = $dlist->current()->match_num;
                $match = $this->getMatch( $r, $m );
                if( is_null( $match ) ) {
                    $match = new Match( $this->getEventId(), $this->getID(), $r, $m );
                    $match->setMatchType( $this->getEvent()->getMatchType() );
                    $this->addMatch( $match );
                }
                $nextRoundNum = $dlist->current()->next_round_num;
                $nextMatchNum = $dlist->current()->next_match_num;
                if( !isset($nextMatchNum) || $nextMatchNum < 1 ) {
                    $nextRound = $r + 1;
                    $nextMatchNum = $this->getNextMatchPointer( $m );

                 }
                 $match->setNextRoundNumber( $nextRoundNum  );
                 $match->setNextMatchNumber( $nextMatchNum );
                 $loadedMatches[ $r ][ $m ] = $match;
            }
        }
        return $loadedMatches;
    }

    /**
     * Given a match number calculate what the match number in the next round should be.
     * @param $m Match number
     */
    private function getNextMatchPointer( int $m ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($m)");

        if( $m & 1 ) {
            $prevMatchNumber = $m - 1;
        }
        else {
            $prevMatchNumber = $m - 2;
        }
        $prevMatchCount = $prevMatchNumber / 2;
        $nm = $prevMatchCount + 1;
        return $nm;
    }
    
    /**
     * Load the matches from db into 
     * a 2-dimensional array[round number][match number]
     * @return Array of matches by round and match number
     */
    private function loadMatchHierarchy() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);

        $loadedMatches = array();

        //force loading existing matches from db
        $matches = $this->getMatches( true );
        foreach($matches as $match ) {
            $loadedMatches[$match->getRoundNumber()][$match->getMatchNumber()] = $match;
        }

        return $loadedMatches;
    }

    /**
     * Find the linked list for the given round and match numbers
     * If current or next matches the list is returned
     */
    private function findListFor( int $r, int $m ) {
        $result = null;
        foreach( $this->adjacencyMatrix as $dlist ) {
            $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                if( ( $dlist->current()->round_num === $r ) &&  ( $dlist->current()->match_num === $m ) ) {
                    $dlist->rewind();
                    $result = $dlist;
                    break;
                }
            }
        }
        return $result;
    }
	
	public function isValid() {
		$isvalid = true;
        $mess = '';
        
        if( $this->event_ID < 1 ) {
            $mess = __( "Bracket must have an event id." );
        }
        elseif( !$this->isNew() &&  $this->bracket_num < 1 ) {
            $mess = __( "Bracket must have a bracket number." );
        }

		if( strlen( $mess ) > 0 ) {
			throw new InvalidBracketException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Bracket
	 */
	public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$result = 0;
        global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $where = array( 'event_ID' => $this->event_ID, 'bracket_num' => $this->bracket_num  );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result = $wpdb->rows_affected;

		$this->log->error_log( sprintf( "%s(%s) -> deleted %d row(s)", $loc, $this->toString(), $result ) );
		return $result;
    }
    
    public function toString() {
        return sprintf( "B(%d,%d)", $this->event_ID, $this->bracket_num );
    }

    public function title() {
        return sprintf( "%s-%s", $this->toString(), $this->getName() );
    }

	/**
	 * Fetch event for this bracket
	 */
	private function fetchEvent() {
		$this->event = Event::get( $this->event_ID );
    }
    
    /**
     * Fetch Matches all Matches for this bracket from the database
     */
    private function fetchMatches() {
		$this->matches = Match::find( $this->getEvent()->getID(), $this->bracket_num );
	}
    
	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ($a->getMatchNumber() < $b->getMatchNumber()) ? -1 : 1;
	}

    /**
     * Sort matches by round number then match number in ascending order
     * Assumes that across all matches, the max match number is less than $max
     */
	private function sortByRoundMatchNumberAsc( $a, $b, $max = 1000 ) {
        if($a->getRoundNumber() === $b->getRoundNumber() && $a->getMatchNumber() === $b->getMatchNumber()) return 0; 
        $compa = $a->getRoundNumber() * $max + $a->getMatchNumber();
        $compb = $b->getRoundNumber() * $max + $b->getMatchNumber();
        return ( $compa < $compb  ? -1 : 1 );
	}

	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		global $wpdb;

        parent::create();
        
        $table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");

        if( $this->bracket_num > 0 ) {
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d;";
            $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->bracket_num ), 0, 0 );
            
            //If this bracket arleady exists throw exception
            if( $exists > 0 ) {
                $wpdb->query( "UNLOCK TABLES;" );
                $rnd = $this->bracket_num;
                $evtId = $this->event_ID;
                $code = 870;
                throw new InvalidBracketException( "Cannot create Bracket($evtId,$rnd) because it already exists.", $code );
            }
        }
        else {
            //If bracket_num is zero, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(bracket_num),0) FROM $table WHERE event_ID=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID );
            $this->bracket_num = $wpdb->get_var( $safe ) + 1;
            error_log( sprintf("%s(%s) bracket number assigned.", $loc, $this->toString() ) );
        }

        $values  = array('event_ID' => $this->event_ID
                        ,'bracket_num' => $this->bracket_num
                        ,'is_approved' => ($this->is_approved ? 1 : 0 )
                        ,'name'=>$this->name );
		$formats_values = array( '%d', '%d', '%d', '%s' );
		$wpdb->insert( $table, $values, $formats_values );
		$result = $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );
		$this->isnew = false;
		$this->isdirty = false;
		
        $this->log->error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );

		return $result;
	}

	/**
	 * Update the Bracket in the database
	 */
	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		global $wpdb;

		parent::update();

		$values         = array( 'name'        =>$this->name
                               , 'is_approved' => ($this->is_approved ? 1 : 0 ) );
		$formats_values = array( '%s', '%d');
        $where          = array( 'event_ID'    => $this->event_ID
                               , 'bracket_num' => $this->bracket_num );
		$formats_where  = array( '%d', '%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

        $this->log->error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );
		return $result;
    }
    
	private function manageRelatedData():int {
		$result = 0;

		if( isset( $this->matches ) ) {
			foreach( $this->matches as $match ) {
				$result += $match->save();
			}
		}

		//Delete ALL Matches removed from this Bracket
		foreach( $this->matchesToBeDeleted as &$match ) {
			$result += $match->delete();
			unset( $match );			
		}
		$this->matchesToBeDeleted = array();

		return $result;
	}
	
    /**
     * Map incoming data to an instance of Bracket
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData( $obj, $row );
        $obj->event_ID      = (int) $row["event_ID"];
        $obj->bracket_num   = (int) $row["bracket_num"];
        $obj->is_approved   = (int) $row["is_approved"];
        $obj->name          = $row["name"];
		$obj->is_approved   = $obj->is_approved === 0 ? false : true;
	}

} //end class
 