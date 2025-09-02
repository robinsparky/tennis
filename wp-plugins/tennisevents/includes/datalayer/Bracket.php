<?php
namespace datalayer;

use commonlib\GW_Debug;
use commonlib\GW_Support;
use DateInterval;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Brackets
 * A Bracket is a subset of matches associated with an Event.
 * For example, there can be a Main bracket and a Consolation bracket.
 * Another take could be a grouping of the Event's matches into sets of 4.
 * @class  Bracket
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Bracket extends AbstractData
{ 
    //Known names of brackets for Single and Double Elimination brackets
    public const WINNERS = "Main";
    public const LOSERS  = "Losers";
    public const CONSOLATION = "Consolation";

	//table name
	public static $tablename = 'tennis_bracket';

	//Attributes
    private $event_ID;
    private $bracket_num = 0;
    private $is_approved = false;
    private $name;

    //Event to which this bracket belongs ( fetched using $event_ID )
    private $event;

    //All entrants signed up for this bracket in this event
    private $signup = array();	

    //Matches in this bracket
    private $matches;
    private $matchHierarchy = array();

	/*************** Static methods ******************/
	/**
	 * Find Brackets referenced in a given Event
	 */
	static public function find( ...$fk_criteria ) {
		global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        error_log("{$loc}: fk_criteria ... ");
        error_log(print_r($fk_criteria,true));
		// $strTrace = GW_Debug::get_debug_trace_Str(5);	
		// error_log("{$loc}: {$strTrace}");

		$table = $wpdb->prefix . self::$tablename;
		$col = array();

        //All Brackets belonging to specified Event
        $eventId = $fk_criteria[0];            
        $sql = "SELECT event_ID, bracket_num, is_approved, `name` 
                FROM $table 
                WHERE event_ID = %d;";
        $safe = $wpdb->prepare( $sql, $eventId );
        $rows = $wpdb->get_results( $safe, ARRAY_A );

        error_log("$loc: Sql ...");
        error_log($safe);

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
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        error_log("{$loc}: pks ... ");
        error_log(print_r($pks,true));	
		// $strTrace = GW_Debug::get_debug_trace_Str(3);	
		// error_log("{$loc}: {$strTrace}");
        
        global $wpdb;

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
    
	/**
	 * Delete this Bracket and all Entrants in the signup
     *  and all Matches associated with this Bracket
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @return int Number of rows affected
	 */
	public static function deleteBracket( int $eventId = 0, int $bracketNum = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
        if( 0 === $eventId || 0 === $bracketNum ) return $result;

        global $wpdb;
        $table = $wpdb->prefix . self::$tablename;

        //Delete the matches
        $result += self::deleteAllMatches( $eventId, $bracketNum );

        //Delete the entrants
        $result += self::deleteAllEntrants( $eventId, $bracketNum );

        //Delete the bracket
        $where = array( 'event_ID' => $eventId, 'bracket_num' => $bracketNum );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;

        return $result;
    }

    /**
     * Delete all matches associated with the identified Bracket
     * And removes all relationships between these Matches and their Entrants
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @return int Number of rows affected
     */
    public static function deleteAllMatches( int $eventId = 0, int $bracketNum = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
        if( 0 === $eventId || 0 === $bracketNum ) return $result;

        global $wpdb;
        //Delete all entrants for all matches for the identified Bracket
        $result += EntrantMatchRelations::removeAllFromBracket( $eventId, $bracketNum );

        //Delete all sets for all matches in the identified Bracket
        $table = $wpdb->prefix . Set::$tablename;
        $where = array( 'event_ID' => $eventId, 'bracket_num' => $bracketNum );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;
        
        //Delete all matches for the identified Bracket
        $table = $wpdb->prefix . TennisMatch::$tablename;
        $where = array( 'event_ID' => $eventId, 'bracket_num' => $bracketNum );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;

        return $result;
    }
    
    /**
     * Delete all signups associated with the identified Bracket
     * Throws exception if matches exist for the identified bracket.
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @return int Number of rows affected
     */
    public static function deleteAllEntrants( int $eventId = 0, int $bracketNum = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
        if( 0 === $eventId || 0 === $bracketNum ) return $result;
        
        global $wpdb;
		$table = $wpdb->prefix . TennisMatch::$tablename;
		$query = "SELECT IFNULL(COUNT(*),0) from $table
				  WHERE event_ID=%d AND bracket_num=%d;";
		$safe = $wpdb->prepare( $query, $eventId, $bracketNum );
		$num = $wpdb->get_var( $safe );

        if( $num > 0 ) {
            throw new InvalidBracketException("Cannot delete Entrants if Matches exist!");
        }
        
        $table = $wpdb->prefix . Entrant::$tablename;
        $where = array( 'event_ID' => $eventId, 'bracket_num' => $bracketNum );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result = $wpdb->rows_affected;

        return $result;
    }
    
    /**
     * Delete the given entrant for the identified Bracket
     * WARNING: Should not do this if matches exist for the identified bracket.
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @param int $position The position of the entrant in the signup
     * @return int Number of rows affected
     */
    public static function deleteEntrant( int $eventId = 0, int $bracketNum = 0, $position = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log("$loc($eventId,$bracketNum,$position)");

		$result = 0;
        if( 0 === $eventId || 0 === $bracketNum || 0 === $position ) return $result;
        
        //Delete all match assignments for this Entrant
        $result += EntrantMatchRelations::removeAllFromEntrant( $eventId, $bracketNum, $position );

        //Delete this Entrant from the signup
        global $wpdb;
        $table = $wpdb->prefix . Entrant::$tablename;
        $where = array( 'event_ID' => $eventId, 'bracket_num' => $bracketNum, 'position' => $position );
        $formats_where = array( '%d', '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;

        return $result;
    }


	/***************************** Instance Methods ******************************/
    public function __construct() {
        parent::__construct( true );
    }

    
	public function __destruct() {
        //static $numBrackets = 0;
        //$loc = __CLASS__ . '::' . __FUNCTION__;
        //++$numBrackets;
        //$this->log->error_log("{$loc} ... bracket#{$numBrackets}");

		if( isset( $this->matches ) ) {
			foreach($this->matches as &$match) {
				unset( $match );
			}
        }
        
		//destroy entrants
		if( isset( $this->signup ) ) {
			foreach( $this->signup as &$ent ) {
				unset( $ent );
			}
        }
        
    }
    
    public function setDirty() {
        if( isset( $this->event ) ) $this->event->setDirty();
        //error_log( sprintf("%s(%s) set dirty", __CLASS__, $this->toString() ) );
        return parent::setDirty();
    }


    public function setBracketNumber( int $bracketnum ) {
        $this->bracket_num = $bracketnum;
        return $this->setDirty();
    }

    public function getBracketNumber() :int {
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
     * Is this bracket approved?
     * @return true if the bracket is approved 
     *         false if the bracket is not approved or if there are no matches assigned
     */
    public function isApproved() {
        if( true == $this->is_approved ) {
            if( 0 === count( $this->getMatches() ) ) {
                $this->is_approved = false;
            }
        }
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

    public function hasEvent() {
        return isset( $this->event );
    }


	/**
	 * Add an Entrant to the draw for this Bracket/Event
	 * This method ensures that Entrants are not added ore than once.
	 * 
	 * @param $name The name of a player in this event
	 * @param $seed The seeding of this player
	 * @return true if succeeds false otherwise
	 */
	public function addToSignup ( string $name, int $seed = null ) {
		$result = false;
		if( isset( $name ) ) {
            $found = false;
            //Escape the single quote because that is how it is in the db
            $test = strtolower(trim(str_replace(["\'","'"],['',''],$name)));
			foreach( $this->getSignup() as $d ) {
				if( $test === strtolower(trim(str_replace(["\'","'"],['',''],$d->getName()))) ) {
					$found = true;
				}
			}
			if( !$found ) {
				$ent = new Entrant( $this->getEventId(), $this->getBracketNumber(), $name, $seed );
				$this->signup[] = $ent;
				$result = $this->setDirty();
			}
		}
		return $result;
	}
	
	/**
	 * Remove an Entrant from the signup
	 * @param string $entrant Entrant in the draw
	 * @return bool true if succeeds false otherwise
	 */
	public function removeFromSignup( string $name ) {
		$result = false;
        
        if( count( $this->getMatches() ) > 0 ) {
            $mess = $this->title() . ": Cannot remove anyone from signup because matches exist.";
            throw new InvalidBracketException( $mess );
        }

        $numDeleted = 0;
        $temp = array();
        //Need to replace single apostrophe with escaped apostrophe
        // because this is how it comes back from the db        
        $test = strtolower(trim(str_replace(["\'","'"],["",""],$name)));

		for( $i = 0; $i < count( $this->getSignup( true ) ); $i++) {
			if( $test === strtolower(trim(str_replace(["\'","'"],["",""],$this->signup[$i]->getName()))) ) {
				$result = $this->setDirty();
                $numDeleted = self::deleteEntrant( $this->getEventId(), $this->getBracketNumber(), $this->signup[$i]->getPosition() );
			}
			else {
				$temp[] = $this->signup[$i];
			}
		}
		$this->signup = $temp;

		return $result;
	}

	/**
	 * Destroy the existing signup.
     * Cannot do this if matches exist.
	 */
	public function removeSignup() {
        $loc = __CLASS__ . "->" . __FUNCTION__;

        if( count( $this->getMatches() ) > 0 ) {
            $mess = $this->title() . ":Cannot remove signup. Remove matches first.";
            throw new InvalidBracketException( $mess );
        }

		foreach( $this->getSignup() as &$dr ) {
			unset( $dr );
		}
		$this->signup = array();

        self::deleteAllEntrants( $this->getEventId(), $this->getBracketNumber() );
		
        return $this->setDirty();
	}
	
	/**
	 * Get the signup for this Event/Bracket
	 * @param $force When set to true will force loading of entrants from db
	 *               This will cause unsaved entrants to be lost.
	 */
	public function getSignup( $force=false ) {
		if( !isset( $this->signup ) || (0 === count( $this->signup)) || $force ) $this->fetchSignup();
		return $this->signup;
	}
	
	/**
	 * Get the size of the signup for this bracket
	 */
	public function signupSize() {
		$this->getSignup();
		return isset( $this->signup ) ? sizeof( $this->signup ) : 0;
	}
	
	/**
	 * Get an entrant by name from the this bracket
	 */
	public function getNamedEntrant( string $name ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        $this->log->error_log("$loc({$name}) ... called by {$calledBy}");
        
		$result = null;

		foreach( $this->getSignup() as $entrant ) {
			if( $name === str_replace(array("\'"),"'",$entrant->getName()) ) {
				$result = $entrant;
				break;
			}
		}
		return $result;
	}
	
    /**
     * Move an entrant from its current position to a new position in the signup.
     * @param int $fromPos The entrant's current position (i.e. place in the lineup)
     * @param int $toPos The intended position in the signup
     * @return int rows affected by this update
     */
	public function moveEntrant( int $fromPos, int $toPos ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}: from '{$fromPos}' to '{$toPos}'");

        $eventId = $this->getEventId();
        $bracketNum = $this->getBracketNumber();
		
		global $wpdb;
        $table = $wpdb->prefix . Entrant::$tablename;
        $fromId = "Entrant($eventId,$bracketNum,$fromPos)";
        $toId = "Entrant($eventId,$bracketNum,$toPos)";
        $tempPos = 99999;
 
		$result = 0;

        //Check position numbers for appropriate ranges
        if( ($fromPos < 1) || ($toPos < 1) || ($toPos >= $tempPos) || ( $fromPos === $toPos ) ) {
			$mess = __("{$loc}: position number(s) out of range ... from {$fromId} to {$toId}).", TennisEvents::TEXT_DOMAIN);
            $this->log->error_log($mess);
			throw new InvalidEventException( $mess );
        }

        $sql = "SELECT count(*) 
                FROM $table WHERE event_ID = %d AND bracket_num=%d AND position = %d;";
                
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum, $fromPos ) );
        $sourceExists = (int) $wpdb->get_var( $safe );
        $this->log->error_log("{$loc}: $fromId to $toId: sourceExists='$sourceExists'");

        if( $sourceExists === 1 ) {
            //Source entrant exists
            //Check if target (i.e. the toPos) exists             
            $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum, $toPos ) );
            $targetExists = (int) $wpdb->get_var( $safe );
            if( $targetExists === 0 ) {
                //Target position number does not exist, 
                // so just update the position number to the target number
                $values = array( 'position' => $toPos);
				$formats_values = array( '%d' );
				
                $where = array( 'event_ID'=>$eventId, 'bracket_num'=>$bracketNum, 'position'=>$fromPos );
                $formats_where  = array( '%d', '%d', '%d' );
        
                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );
                $result = $wpdb->rows_affected;

                if( $wpdb->last_error ) {
                    $mess = "{$loc}: $fromId to $toId: update open position encountered error: '$wpdb->last_error'";
                    $this->log->error_log( $mess );
                    throw new InvalidEntrantException( $mess ); 
                }
                $this->log->error_log( "{$loc}: to open postion $toPos: $result rows affected." );
            }
            else {   
                //Source and target position numbers exist ...
                //First we have to move the source entrant position to a safe place 
                // ... give it a temporary position number
                $values = array( 'position' => $tempPos);
				$formats_values = array( '%d' );
				
                $where          = array( 'event_ID'    => $eventId
                                        ,'bracket_num' => $bracketNum
                                        ,'position'    => $fromPos );
                $formats_where  = array( '%d', '%d', '%d' );

                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "{$loc}:  $fromId to temporary position $tempPos: encountered error: '$wpdb->last_error'";
                    error_log( $mess );
                    throw new InvalidEntrantException( $mess ); 
                }
                $this->log->error_log( "{$loc}: $fromId to temporary position $tempPos: $check rows affected." );

                //Target exists so update match_num by 1 starting from highest to lowest 
                // i.e. from the highest position (but less than temp number) down to the target position
                //Need to start a transaction (default isolation level)
                $wpdb->query( "start transaction;" );

                $sql = "SELECT `event_ID`,`bracket_num`,`position`,`name`,`seed` 
                        FROM $table WHERE event_ID = %d AND bracket_num=%d AND position >= %d and position < %d 
                        ORDER BY position DESC FOR UPDATE;";
                $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum, $toPos, $tempPos ) );
                $trows = $wpdb->get_results( $safe );

                if( $wpdb->last_error ) {
                    $mess = "{$loc}: $fromId to $toId: select for update encountered error: '$wpdb->last_error'";
                    $this->log->error_log( $mess );
                    $wpdb->query( "rollback;" ); 
                    throw new InvalidEntrantException( $mess ); 
                }
                
                foreach( $trows as $trow ) {
                    $oldNum = $trow->position;
                    $newNum = $trow->position + 1;

                    $values = array( 'position' => $newNum );
                    $formats_values = array( '%d' );

                    $where = array( 'event_ID'=>$eventId, 'bracket_num'=>$bracketNum,'position'=>$oldNum );
                    $formats_where  = array( '%d', '%d', '%d' );

                    $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                    if( $wpdb->last_error ) {
                        $mess = "{$loc}: $fromId to $toId: updating $oldNum to $newNum encountered error: '$wpdb->last_error'";
                        $this->log->error_log( $mess );
                        $wpdb->query( "rollback;" ); 
                        throw new InvalidEntrantException( $mess ); 
                    }

                    $result += $wpdb->rows_affected;
                    $this->log->error_log( "{$loc}: making room -> moved position $oldNum to $newNum:  $result cumulative rows affected." );
                }

                //Now update the source's temporary position to the target position
                $values = array( 'position' => $toPos );
                $formats_values = array( '%d' );

                $where = array( 'event_ID'=>$eventId, 'bracket_num'=>$bracketNum, 'position'=>$tempPos );
                $formats_where  = array( '%d', '%d', '%d' );

                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "{$loc}: $fromId to $toId: updating $tempPos to $toPos encountered error: '$wpdb->last_error'";
                    $this->log->error_log( $mess );
                    $wpdb->query( "rollback;" ) ; 
                    throw new InvalidEntrantException( $mess ); 
                }
                $result += $wpdb->rows_affected;
                
                $wpdb->query( "commit;" );  
                $this->log->error_log( "{$loc}: from $tempPos to $toPos: $result cumulative rows affected." );
            }
        }
        elseif( $sourceExists > 1 ) {
            //Error condition
            $mess = __( "{$loc}: from $fromId: multiple positions found." );
            $this->log->error_log( $mess );
            throw new InvalidEntrantException( $mess, 500 );
        }
        elseif( $sourceExists === 0 ) {
            $mess = __( "{$loc}: from $fromId: position does not exist.", TennisEvents::TEXT_DOMAIN );
            $this->log->error_log( $mess );
        }

        return $result;
    }
 
    /**
     * Resequence the signup for the given event
     */
    public function resequenceSignup() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$result = 0;

		global $wpdb;
        $table = $wpdb->prefix . Entrant::$tablename;

        //Start a transaction
		$wpdb->query( "start transaction;" );
		
        //Drop the temp table
		$sql = "DROP TEMPORARY TABLE IF EXISTS temp_entrant;";
		$affected = (int) $wpdb->get_var( $sql );
		if( $wpdb->last_error ) {
			$mess = "{$loc}: drop temp table encountered error: '{$wpdb->last_error}'";
			$this->log->error_log( $mess );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}

        //Create the temp table
        $evtId = $this->getEventId();
        $bracketNum = $this->getBracketNumber();
        $this->log->error_log("{$loc}: eventId='{$evtId}' and bracketNum='{$bracketNum}' ");
		$sql = "CREATE TEMPORARY TABLE temp_entrant ENGINE=MyISAM as 
					SELECT * 
					FROM `$table` 
					WHERE `event_ID` = %d
                    AND `bracket_num` = %d 
					ORDER BY `position` ASC;";
		$safe = $wpdb->prepare( $sql, array($this->getEventId(), $this->getBracketNumber() ) );
		$affected = (int) $wpdb->get_var( $safe );
		if( $wpdb->last_error ) {
			$mess = "{$loc}: create temp table encountered error: '{$wpdb->last_error}'";
			$this->log->error_log( $mess );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}
        $this->log->error_log($affected, "{$loc}: created target temp table with '{$affected}' rows from source table '{$table}'");
		
        //Remove the corresponding records from the source entrant table
		$where = array( "event_ID" => $this->getEventId(), "bracket_num" => $this->getBracketNumber() );
		$affected = $wpdb->delete( $table, $where );
		if( false === $affected ) {
			$mess = "{$loc}: delete from table '{$table}' encountered error: '{$wpdb->last_error}'";
			$this->log->error_log( $mess );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}
		$this->log->error_log("{$loc}: deleted '{$affected}' rows from source table '{$table}'" );
		

        //Copy the temp table into the entrant table incrementing the position with step = 1
		$sql = "SELECT `event_ID`,`bracket_num`,`position`,`name`,`seed` 
				FROM `temp_entrant` 
				ORDER BY event_ID, bracket_num, position ASC;";
		$trows = $wpdb->get_results( $sql );
		$pos = 1;
		foreach( $trows as $trow ) {
			$values = array( 'event_ID' => $trow->event_ID
                           , 'bracket_num' => $trow->bracket_num
						   , 'position' => $pos++ 
						   , 'name' => $trow->name
						   , 'seed' => $trow->seed );

			$this->log->error_log( $values, "{$loc}: inserting into {$table} ..." );

			$formats_values = array( '%d', '%d', '%d', '%s', '%d' );
			$check = $wpdb->insert( $table, $values, $formats_values );

			if( $wpdb->last_error ) {
				$mess = "{$loc}: inserting '{$trow->name}' at postion '{$pos}' encountered error: '{$wpdb->last_error}'";
				$this->log->error_log( $mess );
				$wpdb->query( "rollback;" ); 
				throw new InvalidSignupException( $mess ); 
			}

			$result += $wpdb->rows_affected;
			$this->log->error_log( "{$loc}: inserted last position '{$pos}':  {$result} cumulative rows affected." );
		}
		
        //Finally, commit the transaction
		$wpdb->query( "commit;" );  
		$this->log->error_log( "{$loc}: '{$result}' cumulative rows affected." );
        return $result;
    }

    /**
     * Insert a match after another match. Works for preliminary matches (i.e. round 1 and not approved) only
     * @param int $fromMatchNum The entrant's current position (i.e. place in the lineup)
     * @param int $toMatchNum The the match immediatly after which the from match will be moved.
     * @return array a map of old and new match numbers
     */
	public function insertAfter( int $fromMatchNum, int $toMatchNum ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}: from '{$fromMatchNum}' to '{$toMatchNum}'");

        //Check match numbers for appropriate ranges
        if( $fromMatchNum < 1 || $toMatchNum < 1 || $fromMatchNum === $toMatchNum) {
            return [];
        }

        global $wpdb;  
        $intersectionTable = $wpdb->prefix . EntrantMatchRelations::$tablename;
        $matchTable = $wpdb->prefix . TennisMatch::$tablename;
        $tempMatchTable = $wpdb->prefix . "tempMatch";
        $tempIntersectionTable =  $wpdb->prefix . "tempIntersection";
        $eventId = $this->getEventId();
        $bracketNum = $this->getBracketNumber();
		
		global $wpdb;
        $fromId = "M($eventId,$bracketNum,1,$fromMatchNum)";
        $toId = "M($eventId,$bracketNum,1,$toMatchNum)";

        $sql = "SELECT count(*) 
                FROM $matchTable WHERE event_ID = %d AND bracket_num=%d AND round_num=1 and match_num = %d;";
                
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum, $fromMatchNum ) );
        $sourceExists = (int) $wpdb->get_var( $safe );
        $this->log->error_log("{$loc}: $fromId to $toId: sourceExists='$sourceExists'");

        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum, $toMatchNum ) );
        $targetExists = (int) $wpdb->get_var( $safe );
        $this->log->error_log("{$loc}: $fromId to $toId: targetExists='$targetExists'");
        if(!$sourceExists || !$targetExists) {
            $mess = "Either the source or the target match number does not exist.";
            $this->log->error_log("$loc: $mess");
            throw new InvalidBracketException($mess);
        }
        
        //Drop the temporary match table
		$sql = "DROP TEMPORARY TABLE IF EXISTS {$tempMatchTable};";
		$affected = $wpdb->query( $sql );
		
        //Create temporary table for match data
        $sql = "CREATE TEMPORARY TABLE {$tempMatchTable} ( event_ID int, bracket_num int, round_num int, match_num int
                                                    , match_date datetime, is_bye tinyint(4), next_round_num int, next_match_num int
                                                    , comments varchar(255), new_match_num int default 0
                                                    ) ENGINE=MyISAM;";
		$affected = $wpdb->query( $sql );
        
        //Drop the temporary intersection table
		$sql = "DROP TEMPORARY TABLE IF EXISTS {$tempIntersectionTable};";
		$affected = $wpdb->query( $sql );
		
        //Create temporary table for intersection data
        $sql = "CREATE TEMPORARY TABLE {$tempIntersectionTable} ( match_event_ID int, match_bracket_num int, match_round_num int, match_num int, entrant_position int
                                                                , is_visitor tinyint(4), new_match_num int default 0
                                                                ) ENGINE=MyISAM;";
		$affected = $wpdb->query( $sql );

        /*
        Work begins here
        */
        $wpdb->query("start transaction;");
        $sql = "SELECT mat.match_num
                    FROM {$matchTable} as mat
                    WHERE mat.event_ID = %d
                    AND mat.bracket_num = %d 
                    AND mat.round_num = 1
                    ORDER BY mat.match_num ASC;";
        $safe = $wpdb->prepare( $sql, array((int)$eventId, $bracketNum) );
        $rows = $wpdb->get_results( $safe, ARRAY_A );
        if( $wpdb->last_error ) {
            $mess = "{$loc}({$fromMatchNum}),{$toMatchNum}) encountered error: '$wpdb->last_error'";
            $this->log->error_log( $mess );
            $wpdb->query("rollback");
            throw new InvalidBracketException( $mess ); 
        }

        $cachedMatches = [];
        foreach($rows as $row) {
            $cachedMatches[] = ['oldMatchNum'=>$row['match_num'],'newMatchNum'=>0];
        }

        //Calculate the new match numbers
        reset($cachedMatches);
        $prevMatchNum = 0;
        $foundTarget = false;
        $foundSource = false;
        $newOrder = [];
        foreach( $cachedMatches as $map) {
            if( $map['oldMatchNum'] == $fromMatchNum) {
                $map['newMatchNum'] = $toMatchNum + 1;
                $prevMatchNum = max($toMatchNum + 1, $map['oldMatchNum']);
                $foundSource = true;
                $newOrder[] = $map;
            }
            elseif( $map['oldMatchNum'] == $toMatchNum ) {
                $foundTarget = true;
                $map['newMatchNum'] = $map['oldMatchNum'];
                $prevMatchNum = $map['oldMatchNum'];
                $newOrder[] = $map;
            }
            elseif( $map['oldMatchNum'] < $toMatchNum) {
                $map['newMatchNum'] = $map['oldMatchNum'];
                $prevMatchNum = $map['oldMatchNum'];
                $newOrder[] = $map;
            }
            else {
                if(($map['oldMatchNum'] - $toMatchNum) == 1) {
                    $map['newMatchNum'] = $toMatchNum + 2;
                    $prevMatchNum = $toMatchNum + 2;
                    $newOrder[] = $map;
                }
                else{
                    $map['newMatchNum'] = ++$prevMatchNum;
                    $newOrder[] = $map;
                }
            }
        }
        if( !$foundSource || !$foundTarget) {
            $mess = "{$loc}({$fromMatchNum}),{$toMatchNum}) source and/or target match number not found'";
            $this->log->error_log( $mess );
            $wpdb->query( "rollback;" ) ;
            throw new InvalidBracketException( $mess );
        }

        //Make sure that the new match numbers start with 1 and continue with increments of 1
        $newOrder = $this->record_sort($newOrder,'newMatchNum');
        for($i=0;$i<count($newOrder); $i++) {
            $newOrder[$i]['newMatchNum'] = $i+1;
        }
        
        //Insert match rows into the temp match table
        $sql = "select event_ID, bracket_num, round_num, match_num
                , match_date, is_bye, next_round_num, next_match_num, comments
        from $matchTable       
        where event_ID = %d
        and bracket_num = %d
        and round_num = 1;";
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum ) );
        $matchRows = $wpdb->get_results( $safe, ARRAY_A ); 
        if( $wpdb->last_error ) {
            $mess = "{Retrieving {$matchTable} records encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess ); 
        }
        $affected = $this->insert_multiple_rows($tempMatchTable, $matchRows);
        
        //Insert the intersection records into the temp intersection table
        $sql = "select match_event_ID, match_bracket_num, match_round_num, match_num, entrant_position, is_visitor
        from $intersectionTable       
        where match_event_ID = %d
        and match_bracket_num = %d
        and match_round_num = 1;";
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum ) );
        $intersectionRows = $wpdb->get_results( $safe, ARRAY_A ); 
        if( $wpdb->last_error ) {
            $mess = "{Retrieving {$intersectionTable} records encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess ); 
        }
        $this->insert_multiple_rows($tempIntersectionTable, $intersectionRows);

        //Remove records of interest from intersection table
        $sql = "delete from {$intersectionTable}
                where match_event_ID=%d
                and match_bracket_num=%d
                and match_round_num = 1;";                
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum ) );
        $affected = $wpdb->query($safe); 
        if( $wpdb->last_error ) {
            $mess = "{Deleting from {$intersectionTable} encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess ); 
        }
        $this->log->error_log("$loc: deleted {$affected} rows from {$intersectionTable}");

        //Remove the records of interest from the match table
        $sql = "delete from {$matchTable}
                where event_ID=%d
                and bracket_num=%d
                and round_num = 1;";
        $safe = $wpdb->prepare( $sql, array( $eventId, $bracketNum ) );
        $affected = $wpdb->query($safe); 
        if( $wpdb->last_error ) {
            $mess = "{Deleting from {$matchTable} encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess ); 
        }
        $this->log->error_log("$loc: deleted {$affected} rows from {$matchTable}");

        //Update the new match numbers in the temporary tables
        foreach($newOrder as $map) {
             $oldMatchNum = $map['oldMatchNum'];
             $foundKey = array_search($oldMatchNum,array_column($matchRows,'match_num'));
             if($foundKey !== false ) {
                $newMatchNum = $map['newMatchNum'];
                $sql = "update {$tempMatchTable}
                        set new_match_num=%d
                        where event_ID = %d
                        and bracket_num = %d
                        and round_num = 1
                        and match_num = %d";
                $safe = $wpdb->prepare($sql, array($newMatchNum,$eventId,$bracketNum, $oldMatchNum));
                $affected = $wpdb->query($safe);
                if( $wpdb->last_error ) {
                    $mess = "{Updating {$tempMatchTable} encountered error: '{$wpdb->last_error}'";
                    $this->log->error_log( "$loc: $mess" );
                    $wpdb->query("rollback;");
                    throw new InvalidBracketException( $mess );
                }
                $this->log->error_log("$loc: updated $tempMatchTable; $affected rows were affected using new={$newMatchNum} and where old={$oldMatchNum}");
            }
            else{
                $mess = "Updating {$tempMatchTable}: could not find a key for old match number={$oldMatchNum}";
                $this->log->error_log("$loc: $mess");
                $wpdb->query("rollback;");
                throw new InvalidBracketException($mess);
            }

            $foundKey = array_search($oldMatchNum,array_column($intersectionRows,'match_num'));
            if($foundKey !== false) {
               $newMatchNum = $map['newMatchNum'];
               $sql = "update {$tempIntersectionTable}
                       set new_match_num=%d
                       where match_event_ID = %d
                       and match_bracket_num = %d
                       and match_round_num = 1
                       and match_num = %d";
               $safe = $wpdb->prepare($sql, array($newMatchNum,$eventId,$bracketNum, $map['oldMatchNum']));
               $affected = $wpdb->query($safe);
               if( $wpdb->last_error ) {
                   $mess = "{Updating {$tempIntersectionTable} encountered error: '{$wpdb->last_error}'";
                   $this->log->error_log( "$loc: $mess" );
                   $wpdb->query("rollback;");
                   throw new InvalidBracketException( $mess );
               }
               $this->log->error_log("$loc: updated $tempIntersectionTable; $affected rows were affected using new={$newMatchNum} where old={$oldMatchNum}");
           }
           else{
               $mess = "Updating {$tempIntersectionTable}: could not find a key for old match number={$oldMatchNum}";
               $this->log->error_log("$loc: $mess");
               $wpdb->query("rollback;");
               throw new InvalidBracketException($mess);
           }
        }//end newOrder

        //Finally update the real tables with the new match numbers
        $sql = "select event_ID, bracket_num, round_num, new_match_num as match_num
                , match_date, is_bye, next_round_num, next_match_num, comments 
                from {$tempMatchTable}";
        $trows = $wpdb->get_results($sql, ARRAY_A);
        $affected = $this->insert_multiple_rows($matchTable, $trows);
        if( $wpdb->last_error ) {
            $mess = "{Inserting into {$matchTable} encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess );
        }
        $this->log->error_log("$loc: $affected rows inserted into $matchTable");

        $sql = "select match_event_ID, match_bracket_num, match_round_num, new_match_num as match_num
                ,entrant_position, is_visitor 
                from {$tempIntersectionTable}";
        $trows = $wpdb->get_results($sql, ARRAY_A);
        $affected = $this->insert_multiple_rows($intersectionTable, $trows); 
        if( $wpdb->last_error ) {
            $mess = "{Inserting into {$tempIntersectionTable} encountered error: '{$wpdb->last_error}'";
            $this->log->error_log( "$loc: $mess" );
            $wpdb->query("rollback;");
            throw new InvalidBracketException( $mess );  
        }     
        $this->log->error_log("$loc: $affected rows inserted into $intersectionTable");

        $wpdb->query("commit;");

        $rnd1Matches = $this->getMatchesByRound(1,true);
        foreach($rnd1Matches as $match) {
            $match->getHomeEntrant(true);
            $match->getVisitorEntrant(true);
        }
        $this->save();

        return $newOrder;
    }

    /**
     * Function to insert multiple rows into a database table
     */
    private function insert_multiple_rows( $table, $request ) {
        global $wpdb;
        $column_keys   = '';
        $column_values = '';
        $sql           = '';
        $last_key      = array_key_last( $request );
        $first_key     = array_key_first( $request );
        foreach ( $request as $k => $value ) {
            $keys = array_keys( $value );
     
            // Prepare column keys & values.
            foreach ( $keys as $v ) {
                $column_keys   .= sanitize_key( $v ) . ',';
                $sanitize_value = sanitize_text_field( $value[ $v ] );
                $column_values .= is_numeric( $sanitize_value ) ? $sanitize_value . ',' : "'$sanitize_value'" . ',';
            }
            // Trim trailing comma.
            $column_keys   = rtrim( $column_keys, ',' );
            $column_values = rtrim( $column_values, ',' );
            if ( $first_key === $k ) {
                $sql .= "INSERT INTO {$table} ($column_keys) VALUES ($column_values),";
            } elseif ( $last_key == $k ) {
                $sql .= "($column_values)";
            } else {
                $sql .= "($column_values),";
            }
     
            // Reset keys & values to avoid duplication.
            $column_keys   = '';
            $column_values = '';
        }
        return $wpdb->query( $sql );
    }

    /**
     * This is a function to sort an indexed 2D array by a specified sub array key, either ascending or descending.
     * It is usefull for sorting query results from a database by a particular field after the query has been returned
     * This function can be quite greedy. It recreates the array as a hash to use ksort() then back again
     * By default it will sort ascending but if you specify $reverse as true it will return the records sorted descending
     */
    private function record_sort($records, $field, $reverse=false)
    {
        $hash = array();
    
        foreach($records as $record)
        {
            $hash[$record[$field]] = $record;
        }
    
        ($reverse)? krsort($hash) : ksort($hash);
    
        $records = array();
    
        foreach($hash as $record)
        {
            $records []= $record;
        }
    
        return $records;
    }

    public function matchesByEntrant() {
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        $this->log->error_log("{$loc}");

        $result = array();
        
        foreach( $this->getSignup() as $player ) {
            $matches = array();
            $playerName = $player->getName();
            foreach( $this->getMatches() as $match ) {
                if(!is_null($match->getHomeEntrant(true)) && $playerName === $match->getHomeEntrant()->getName()) {
                    $matches[] = $match;
                }
                elseif(!is_null($match->getVisitorEntrant(true)) && $playerName === $match->getVisitorEntrant()->getName() ) {
                    $matches[] = $match;
                }
            }
            $result[$playerName] = array( $player, $matches );
        }

        return $result;
    }

    /**
     * NOT USED YET ... unfinished
     */
    public function getMatchesByEntrantName() {
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        $this->log->error_log("{$loc}");

        global $wpdb;

        $pks=[$this->getEventId(),$this->getBracketNumber()];

		$table = $wpdb->prefix . self::$tablename;
        $match_entrant_table = $wpdb->prefix . "tennis_match_entrant";
        $entrant_table = $wpdb->prefix . Entrant::$tablename;
        $sql="SELECT ent.name as name, ent.position as position, me.match_event_ID as event_ID
		,me.match_bracket_num as bracket_num, me.match_round_num as round_num, me.match_num as match_num, me.is_visitor as is_visitor
        FROM {$entrant_table}
        INNER JOIN {$match_entrant_table} me on me.entrant_position=ent.position AND me.match_event_ID=ent.event_ID
        INNER JOIN {$table} b ON b.event_ID=me.match_event_ID AND b.bracket_num=me.match_bracket_num  
        WHERE ent.event_ID=%d 
        and   b.bracket_num=%d 
        ORDER BY ent.name,me.match_round_num,me.match_num,me.is_visitor";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

        error_log( sprintf( "%s(B(%d,%d)) -> %d rows returned.", $loc, $pks[0], $pks[1], $wpdb->num_rows ) );
        
		$result=[];
        foreach($rows as $row) {
            $result["eventId"]     = (int) $row["event_ID"];
            $result["bracket_num"] = (int) $row["bracket_num"];
            $result["name"]        = str_replace("\'","'",$row["name"]);
            $result["position"]    = (int) $row["position"];
            $result["round_num"]   = (int) $row["round_num"];
            $result["match_num"]   = (int) $row["match_num"];
            $result["is_visitor"]  = (int) $row["is_visitor"];
        }

		return $result;
    }

    /**
     * Get the early losers in this bracket
     */
    private function getEarlyLosers( $umpire ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $allLosers = $this->getAllLosers( $umpire );
        $earlyLosers = array();
        for($r = 1; $r <= min(2, count($allLosers)); $r++ ) {
            $entrants[] = $allLosers[$r];
            foreach( $entrants as $entrant ) {
                $earlyLosers[] = $entrant;
            }
        }
        return $earlyLosers;
    }


    /**
     * Get all the known losers in this bracket
     * @param $umpire is needed to determine who won a given match
     * @return Array of entrants by round and match number who lost
     */
    public function getAllLosers( $umpire ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $losers = array();
        if( !is_null( $umpire ) ) {
            $allMatches = $this->getMatches();
            foreach( $allMatches as $match ) {
                $home = $match->getHomeEntrant();
                $visitor = $match->getVisitorEntrant();
                $winner = $umpire->matchWinner( $match );
                if( is_null( $winner ) ) continue;

                if( $winner == $home->getName() && !is_null( $visitor ) ) {
                    $losers[$match->getRoundNumber()][$match->getMatchNumber()] = $visitor;
                }
                elseif( $winner == $visitor->getName() ) {
                    $losers[$match->getRoundNumber()][$match->getMatchNumber()] = $home;
                }
            }
        }
        return $losers;
    }
    
    /**
     * Create a new TennisMatch and add it to this Event.
	 * The TennisMatch must pass validity checks
	 * @param int $round The round number for this match
	 * @param int $matchType The type of match @see MatchType class
	 * @param int $matchnum The match number if known
     * @param Entrant $home
     * @param Entrant $visitor
     * @param bool $bye Set to true if the new match is a bye
     * @return TennisMatch if successful; null otherwise
     */
    public function addNewMatch( int $round, float $matchType, $matchnum = 0, Entrant $home = null, Entrant $visitor = null, bool $bye = false ) {
		$result = null;

        if( isset( $home ) ) {
            $this->getMatches();
            $match = new TennisMatch( $this->getEvent()->getID(), $this->bracket_num, $round, $matchnum );
            $match->setIsBye( $bye );				
            //$match->setMatchType( $matchType );
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
     * Add a TennisMatch to this Bracket
	 * The TennisMatch must pass validity checks
     * @param TennisMatch $match
	 * @return true if successful, false otherwise
     */
    public function addMatch( TennisMatch &$match ) {
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
            $result = $this->setDirty();
		}
        
        return $result;
	}

    /**
     * Access all Matches in this Event sorted by round number then match number
	 * @param bool $force When set to true will force loading of matches
	 *               This will cause unsaved matches to be lost.
	 * @return Array of all matches for this event
     */
    public function getMatches( $force = false ):array {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);

        if( !isset( $this->matches ) 
            || (is_array($this->matches) && (0 === count($this->matches))) 
            || $force ) $this->fetchMatches();
        foreach( $this->matches as $match ) {
            $match->setBracket( $this );
        }
        usort( $this->matches, array( __CLASS__, 'sortByRoundMatchNumberAsc' ) );
        return $this->matches;
	}
	
    /**
     * Access all Matches in this Event for a specific round
	 * @param int $rndnum The round number of interest
     * @param bool $force If true forces fetching of matches from db.
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
	 * @param int $rndnum The round number
	 * @param int $matchnum The match number
	 * @return TennisMatch if successful, null otherwise
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
     * Get the total number of matches in this bracket
     */
    public function numMatches():int {
        return $this->getNumberOfMatches();
    }
    
    /**
     * Get the total number of matches in this bracket
     */
    public function getNumberOfMatches():int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return count( $this->getMatches() );
	}
	
    /**
     * Get the number of matches in this Round
	 * @param int $round The round number of the desired matches
	 * @return int Count of matches in the given round
     */
    public function numMatchesByRound( int $round ):int {	
        $loc = __CLASS__ . "::" . __FUNCTION__;	
		return array_reduce( $this->getMatches(), function ( $sum, $m ) use($round) { if( $m->getRound() === $round ) ++$sum; },  0);
    }
    
    /**
     * Get the number of byes in this bracket.
     * Note that the preliminary rounds must have already been scheduled.
     * @return int number of byes
     */
    public function getNumberOfByes() {
		global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

        $byes = 0;
        $bracketTable = $wpdb->prefix . self::$tablename;
        $eventTable = $wpdb->prefix . "tennis_event";
        $matchTable = $wpdb->prefix . "tennis_match";
        // $eventId = $this->getEventId();
        // $bracketNum = $this->getBracketNumber();
      
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
     * @param int $rn the round number of interest
	 * @return int The maximum of all the match numbers in the round
     */
    public function maxMatchNumber( int $rn ):int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        global $wpdb;
        $matchTable = $wpdb->prefix . "tennis_match";

        $sql = "SELECT IFNULL(MAX(match_num),0) FROM $matchTable WHERE event_ID=%d AND bracket_num=%d AND round_num=%d;";
        $safe = $wpdb->prepare( $sql, $this->getID(), $this->bracket_num, $rn );
        $max = (int)$wpdb->get_var( $safe );

        return $max;
    }
	
	/**
	 * Remove the collection of Matches
	 */
	public function removeAllMatches() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $num = 0;
        $this->getMatches();
        foreach( $this->matches as &$match ) {
            $match = null;
            ++$num;
        }
        
        $this->UnApprove();
        $rows = self::deleteAllMatches( $this->getEventId(), $this->getBracketNumber() );
        $rem = count($this->matches);
        $this->log->error_log("$loc: removed {$num}; remaining {$rem}; rows deleted from db {$rows}");
        $this->matches = array();
		
        return $this->setDirty();
    }

    /**
     * This method moves (i.e. swaps) the Home and Visitor of the source TennisMatch
     * with the Home and Visitor of the target match.
     * Only works for Round 1 (i.e. Preliminary round). The Bracket cannot be approved!
     * @param int $sourceMatchNum TennisMatch number of the source match
     * @param int $targetMatchNum TennisMatch number of the target match
     * @return array The 2 matches affected by the swap
     */
    public function swapPlayers(int $sourceMatchNum, int $targetMatchNum ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc({$sourceMatchNum},{$targetMatchNum })");

        $result = [];
        if( $this->isApproved() ) {
            $mess = __("Bracket '{$this->getName()}' has been approved. Cannot swap players.", TennisEvents::TEXT_DOMAIN);
            return new InvalidTennisOperationException();
        }

        $sourceMatch = $this->getMatch(1, $sourceMatchNum );
        $targetMatch = $this->getMatch(1, $targetMatchNum );
        
        if( !isset( $sourceMatch ) || !isset( $targetMatch ) ) {
            return $result;
        }

        if( $sourceMatch->isBye() && $targetMatch->isBye() ) {
            $sourceHome = $sourceMatch->getHomeEntrant();
            $sourceVisitor = null;
            $targetHome = $targetMatch->getHomeEntrant();
            $targetVisitor = null;
            $sourceMatch->setHomeEntrant($targetHome);
            $targetMatch->setHomeEntrant($sourceHome);

            $result["source"] = ["roundNum" => 1, "matchNum" => $sourceMatchNum, "home" => ["name" => $targetHome->getName(), "seed" => $targetHome->getSeed()], "visitor"=> ["name"=>'', "seed" => 0]];
            $result["target"] = ["roundNum" => 1, "matchNum" => $targetMatchNum, "home" => ["name" => $sourceHome->getName(), "seed" => $sourceHome->getSeed()], "visitor"=> ["name"=>'', "seed" => 0]];
        }
        elseif( $sourceMatch->isBye() ) {
            $sourceHome = $sourceMatch->getHomeEntrant();
            $sourceVisitor = null;
            $targetHome = $targetMatch->getHomeEntrant();
            $targetVisitor = $targetMatch->getVisitorEntrant();
            $sourceMatch->setHomeEntrant( $targetHome );
            $sourceMatch->setVisitorEntrant( $targetVisitor );
            $sourceMatch->setIsBye( false );
            $targetMatch->setHomeEntrant( $sourceHome );
            $targetMatch->setVisitorEntrant();
            $targetMatch->setIsBye( true );

            $result["source"] = ["roundNum" => 1, "matchNum" => $sourceMatchNum, "home" => ["name" => $targetHome->getName(), "seed" => $targetHome->getSeed()], "visitor"=> ["name"=>$targetVisitor->getName(), "seed" => $targetVisitor->getSeed()]];
            $result["target"] = ["roundNum" => 1, "matchNum" => $targetMatchNum, "home" => ["name" => $sourceHome->getName(), "seed" => $sourceHome->getSeed()], "visitor"=> ["name"=>'', "seed" => 0]];
        }
        elseif( $targetMatch->isBye() ) {
            $sourceHome = $sourceMatch->getHomeEntrant();
            $sourceVisitor = $sourceMatch->getVisitorEntrant();
            $targetHome = $targetMatch->getHomeEntrant();
            $targetVisitor = null;

            $sourceMatch->setHomeEntrant( $targetHome );
            $sourceMatch->setVisitorEntrant();
            $sourceMatch->setIsBye( true );

            $targetMatch->setHomeEntrant( $sourceHome );
            $targetMatch->setVisitorEntrant( $sourceVisitor );
            $targetMatch->setIsBye( false );

            $result["source"] = ["roundNum" => 1, "matchNum" => $sourceMatchNum, "home" => ["name" => $targetHome->getName(), "seed" => $targetHome->getSeed()], "visitor"=> ["name"=>'', "seed" => 0]];
            $result["target"] = ["roundNum" => 1, "matchNum" => $targetMatchNum, "home" => ["name" => $sourceHome->getName(), "seed" => $sourceHome->getSeed()], "visitor"=> ["name"=>$sourceVisitor->getName(), "seed" => $sourceVisitor->getSeed()]];
    
        }
        else {
            $sourceHome = $sourceMatch->getHomeEntrant();
            $sourceVisitor = $sourceMatch->getVisitorEntrant();
            $targetHome = $targetMatch->getHomeEntrant();
            $targetVisitor = $targetMatch->getVisitorEntrant();

            $sourceMatch->setHomeEntrant($targetHome);
            $sourceMatch->setVisitorEntrant($targetVisitor);
            $targetMatch->setHomeEntrant($sourceHome);
            $targetMatch->setVisitorEntrant($sourceVisitor);

            $result["source"] = ["roundNum" => 1, "matchNum" => $sourceMatchNum, "home" => ["name" => $targetHome->getName(), "seed" => $targetHome->getSeed()], "visitor"=> ["name"=>$targetVisitor->getName(), "seed" => $targetVisitor->getSeed()]];
            $result["target"] = ["roundNum" => 1, "matchNum" => $targetMatchNum, "home" => ["name" => $sourceHome->getName(), "seed" => $sourceHome->getSeed()], "visitor"=> ["name"=>$sourceVisitor->getName(), "seed" => $sourceVisitor->getSeed()]];
    
        }
        return $result;
    }
    
    /**
     * Get the match type
     */
	public function getMatchType() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        return $this->getEvent()->getMatchType();
		// if( $this->numMatches() > 0 ) {
		// 	return $this->matches[0]->getMatchType();
		// }
		// else {
		// 	return $this->getEvent()->getMatchType();
		// }
    }
    
    /**
     * Approve this bracket
     * This causes the match hierarchy to be constructed
     */
    public function approve() {
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        $this->log->error_log( $loc );

        if( 0 == $this->signupSize() ) {
            throw new InvalidBracketException( __("Bracket has no signup.", TennisEvents::TEXT_DOMAIN) );
        }

        if( $this->isApproved() ) {
            throw new InvalidBracketException( __("Bracket already approved. Please reset.", TennisEvents::TEXT_DOMAIN) );
        }

        $this->save();
        $this->matchHierarchy = $this->loadBracketMatches();
        $this->is_approved = true;
        $this->setDirty();

        //Assign expected/target end dates for each match depending on the round it falls into
        if($this->getEvent()->getEventType() === EventType::TOURNAMENT
        && $this->getEvent()->getFormat()    === Format::ELIMINATION) {
            $this->assignRoundTargetDates();
        }

        return $this->matchHierarchy;
    }
    /**
     * Un-Approve this bracket
     */
    public function unApprove() {
        $loc = __CLASS__ . '::' .  __FUNCTION__;
        $this->log->error_log( $loc );

        $this->is_approved = false;
        return $this->setDirty();
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

        $exp = GW_SUpport::calculateExponent( $bracketSignupSize );
        $result = pow( 2, $exp ) / pow( 2, $r - 1 );
        return $result;        
    }

    /**
     * Get the number of rounds for this Bracket's matches
     */
    public function getNumberOfRounds() : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $rounds = array_keys( $this->getMatchHierarchy() );
        $this->log->error_log( $rounds, "$loc - keys for match hier");
        $numRounds = array_reduce( $rounds, function( $carry, $round ) {
                                        if( $round > $carry ) $carry = $round;
                                        return $carry;
                                    }
                            , 0 );
        $this->log->error_log("$loc - num rounds={$numRounds}");
        return $numRounds;
    }
    
    /**
     * Load the matches from db into 
     * a 2-dimensional array[round number][match number]
     * @return array of matches by round and match number
     */
    public function getMatchHierarchy( $force = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);

        if( !$force && !empty( $this->matchHierarchy ) ) return $this->matchHierarchy;

        $matches = $this->getMatches( $force );
        $this->matchHierarchy = [];
        foreach($matches as $match ) {
            $this->matchHierarchy[$match->getRoundNumber()][$match->getMatchNumber()] = $match;
        }

        return $this->matchHierarchy;
    }

    /**
     * Create array of target end dates for each round
     * Places messages in comments of affected matches in this bracket
     * i.e. byes are skipped
     */
    public function assignRoundTargetDates() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);
        $minEvtDays = get_option(TENNISEVENTS::OPTION_MINIMUM_DURATION_SUGGESTIONS,0);
        $leadTime = get_option(TENNISEVENTS::OPTION_MINIMUM_LEADTIME);

        $numRounds = $this->getNumberOfRounds();
        $roundTargets = array();

        $evtId = $this->getEvent()->toString();
        $evtStart = $this->getEvent()->getStartDate();
        $evtEnd   = $this->getEvent()->getEndDate();
        
        $startStr = $evtStart->format( "Y-m-d" );
        $endStr = $evtEnd->format("Y-m-d");

        $evtLen = $evtStart->diff(($evtEnd));
        $evtLenStr = $evtLen->format("%a");
        $evtLenInt = (int) $evtLenStr;
        $this->log->error_log("{$loc}: {$evtId} Starts '{$startStr}', Ends '{$endStr}', interval  is {$evtLenStr} days");

        if( $minEvtDays > 0 && $evtLenInt >  $minEvtDays ) {
            $progress = clone $evtStart;
            $totMatches = 0;
            foreach( $this->getMatches() as $match) {
                if(!$match->isBye()) ++$totMatches;
            }

            $hier = $this->getMatchHierarchy();
            $ratio = $evtLenInt / $totMatches;
            $this->log->error_log("{$loc}: totalMatches = {$totMatches} ratio = {$ratio}");
            for($r=1;$r<=$numRounds;$r++) {
                $numMatches = 0;
                $matches = $hier[$r];
                foreach($matches as $match ) {
                    if(!$match->isBye()) ++$numMatches;
                }
                //$ratio = $numMatches / $totMatches;
                $rndDays = round($ratio * $numMatches);

                //The first round must leave at least the same as the lead time requested to create the draw
                if(1 === $r ) $rndDays = max($rndDays,$leadTime);

                $this->log->error_log("{$loc}: ({$numMatches} matches in round {$r} times {$ratio} gives {$rndDays} rndDays");
                $rndInt = DateInterval::createFromDateString("{$rndDays} days");
                $rndTarget = $progress->add($rndInt);
                $rndTargetStr = $rndTarget->format("Y-m-d");
                $this->log->error_log("{$loc}: round Target Date is '{$rndTargetStr}'");
                $roundTargets[$r] = $rndTarget;
                $progress = clone $rndTarget;
            }

            foreach($this->getMatches() as $match ) {
                if($match->isBye()) continue;
                $target = $roundTargets[$match->getRoundNumber()];
                $targetCmts = sprintf("Complete by %s", $target->format('Y-m-d'));
                $match->setComments($targetCmts);
                $match->setExpectedEndDate_Str($target->format('Y-m-d'));
            }
        }
    }

    /*----------------------------------------- Private Functions --------------------------------*/
    /**
     * Load the bracket's matches in a matrix
     * Checks underlying event's Format to determine method of loading.
     * @return array Array of matches organized as [round][match num]
     */
    private function loadBracketMatches() {

        $format = $this->getEvent()->getFormat();
        switch( $format ) {
            case Format::ELIMINATION:
                return $this->loadSingleElimination();
            break;
            case Format::ROUNDROBIN:
                return $this->getMatchHierarchy( true );
            break;
        }
    }

    /**
     * Loads matches for single elimination format
     */
    private function loadSingleElimination() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log($loc);
        
        $loadedMatches = array();
        //Must be approved?
        $eventSize = $this->signupSize();
        $numRounds = GW_Support::calculateExponent( $eventSize );
        $numToEliminate = $eventSize - pow( 2, $numRounds ) / 2;
        $numExpectedMatches = pow( 2, $numRounds );
        $this->getMatches();

        //First round (i.e. preliminary matches) should be present
        // Just need to set their next pointers
        $numExpectedMatches /= 2;
        $matchesForRound = $this->getMatchesByRound( 1 );
        if( count( $matchesForRound ) != $numExpectedMatches ) {
            $count = count( $matchesForRound );
            throw new InvalidBracketException("Preliminary round has $count matches; should be $numExpectedMatches" );
        }
        foreach( $matchesForRound as $match ) {
            $nextMatchNum = $this->getNextMatchPointer( $match->getMatchNumber());
            $match->setNextMatchNumber( $nextMatchNum );
            $match->setNextRoundNumber( 2 );
            $loadedMatches[$match->getRoundNumber()][$match->getMatchNumber()] = $match;
        }

        //Now fillout the rest
        $numExpectedMatches = pow( 2, $numRounds );
        $this->log->error_log("$loc: Filling out the rest numExpected=$numExpectedMatches");
        for( $r = 1; $r < $numRounds; $r++ ) {
            $numExpectedMatches /= 2;
            $matchesForRound = $this->getMatchesByRound( $r );
            $ct = count($matchesForRound);
            $this->log->error_log("$loc: Matches for Round $r count=$ct");
            $ctr = 0;
            foreach($matchesForRound as $match ) {
                ++$ctr;
                $nextRoundNum = $r + 1;
                $nextMatchNum = $match->getNextMatchNumber();
                if( !isset($nextMatchNum) || $nextMatchNum < 1 ) {
                    $nextMatchNum = $this->getNextMatchPointer( $match->getMatchNumber() );
                    $match->setNextRoundNumber( $nextRoundNum );
                    $match->setNextMatchNumber( $nextMatchNum );
                }
                $this->log->error_log("$loc: Round $r nextRoundNumber=$nextRoundNum nextMatchNum=$nextMatchNum");
                $nextMatch = $this->getMatch( $nextRoundNum, $nextMatchNum );
                if( is_null( $nextMatch ) ) {
                    $nextMatch = new TennisMatch( $this->getEventId(), $this->getID(), $nextRoundNum, $nextMatchNum );
                    //$nextMatch->setMatchType( $this->getEvent()->getMatchType() );
                    $this->addMatch( $nextMatch );
                    $this->log->error_log("$loc: NEW nextMatch = {$nextMatch->toString()}");
                }
                else {
                    $this->log->error_log("$loc: Existing nextMatch = {$nextMatch->toString()}");
                }
                $loadedMatches[ $nextRoundNum ][ $nextMatchNum ] = $nextMatch;
            }
        }

        return $loadedMatches;
    }

    /**
     * Given a match number calculate what the match number in the next round should be.
     * @param int $m TennisMatch number
     * @return int Number of the next match
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
	
	public function isValid() {
		$isvalid = true;
        $mess = '';
        
        if( $this->event_ID < 1 ) {
            $mess = __( "Bracket must have an event id.", TennisEvents::TEXT_DOMAIN );
        }
        elseif( !$this->isNew() &&  $this->bracket_num < 1 ) {
            $mess = __( "Bracket must have a bracket number.", TennisEvents::TEXT_DOMAIN );
        }

		if( strlen( $mess ) > 0 ) {
			throw new InvalidBracketException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Bracket
     * Deletes all matches and their player assigments 
     * as well as all entrants signed up in this Bracket
	 */
	public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = self::deleteBracket( $this->getEventId(), $this->getBracketNumber() );
        $this->log->error_log("{$loc}: {$this->title()} Deleted {$result} rows from db.");

        return $result;
    }
    
    public function toString() {
        return sprintf( "B(%d,%d)", $this->event_ID, $this->bracket_num );
    }

    public function title() {
        return sprintf( "%s-%s", $this->toString(), $this->getName() );
    }
    
	public function save():int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}({$this->toString()})");
		return parent::save();
	}

	/**
	 * Fetch event for this bracket
	 */
	private function fetchEvent() {
		$this->event = Event::get( $this->getEventId() );
    }

	/**
	 * Fetch all Entrants for this bracket.
	 */
	private function fetchSignup() {
		$this->signup = Entrant::find( $this->getEventId(), $this->getBracketNumber() );
	}

    /**
     * Fetch Matches all Matches for this bracket from the database
     */
    private function fetchMatches() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $eventId = $this->getEvent()->getID();
        $bracket_num = $this->getBracketNumber();
        $this->log->error_log("$loc: eventId=$eventId; bracket_num=$bracket_num ");

		$this->matches = TennisMatch::find( $eventId, $bracket_num );
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
        $this->log->error_log("{$loc}({$this->toString()})");
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
            //TODO: do we need this?
            $sql = "SELECT IFNULL(MAX(bracket_num),0) FROM $table WHERE event_ID=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID );
            $this->bracket_num = $wpdb->get_var( $safe ) + 1;
            $this->log->error_log( sprintf("%s(%s) bracket number assigned.", $loc, $this->toString() ) );
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
		
        $this->log->error_log( sprintf("%s(%s) -> %d rows inserted.", $loc, $this->toString(), $result) );

		$result += $this->manageRelatedData();
		return $result;
	}

	/**
	 * Update the Bracket in the database
	 */
	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}({$this->toString()})");
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


        $this->log->error_log( sprintf("%s(%s) -> %d rows updated.", $loc, $this->toString(), $result) );
        
		$result += $this->manageRelatedData();
		return $result;
    }
    
	private function manageRelatedData():int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}({$this->toString()})");

		$result = 0;

		if( isset( $this->matches ) ) {
			foreach( $this->matches as $match ) {
				$result += $match->save();
			}
		}
        
		//Save signups
		if( isset( $this->signup ) ) {
			foreach($this->signup as $ent) {
                $ent->setEventID( $this->getEventId() );
                $ent->setBracketNumber( $this->getBracketNumber() );
                $this->log->error_log("{$loc}({$ent->toString()}) Saving Entrant {$ent->getName()}");
                if( $ent->isValid() ) {
				    $result += $ent->save();
                }
			}
		}
        else {
            $this->log->error_log("{$loc}({$this->toString()}) The signup was empty!");
        }

		return $result;
	}
	
    /**
     * Map incoming data to an instance of Bracket
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData( $obj, $row );
        $obj->event_ID      = (int) $row["event_ID"];
        $obj->bracket_num   = (int) $row["bracket_num"];
        $obj->name          = str_replace("\'","'",$row["name"]);
        $obj->is_approved   = (int) $row["is_approved"];
		$obj->is_approved   = $obj->is_approved === 0 ? false : true;
	}

} //end class
 