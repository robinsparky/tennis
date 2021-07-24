<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Provides functions to support the more complex
 * data operations re the intersection between entrants and matches.
 * @class  EntrantMatchRelations
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class EntrantMatchRelations {
	private static $tablename = 'tennis_match_entrant';
	
	/**
	 * Create a relationship between an Entrant and a Match
	 * @param int $eventId The ID of the event
	 * @param int $bracket The bracket number
	 * @param int $roundNum The round number of the match
	 * @param int $matchNum The match number
	 * @param int $pos The position of the entrant in the Bracket signup
	 * @param int $visitor Is this the visitor entrant
	 * @return int Number of rows affected
	 */
	static function add( int $eventId, int $bracket, int $roundNum, int $matchNum, int $pos, $visitor = 0 ):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		$result = 0;
		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;
		$query = "SELECT IFNULL(COUNT(*),0) from $table
				  WHERE match_event_ID=%d AND match_bracket_num=%d AND match_round_num=%d AND match_num=%d AND entrant_position=%d;";
		$safe = $wpdb->prepare( $query, $eventId, $bracket, $roundNum, $matchNum, $pos );
		$num = $wpdb->get_var( $safe );

		//Don't add if already there
		if( $num == 0 ) {
			$wpdb->insert($table
						 ,array('match_event_ID'    => $eventId
						       ,'match_bracket_num' => $bracket
							   ,'match_round_num'   => $roundNum
							   ,'match_num'         => $matchNum
							   ,'entrant_position'  => $pos
							   ,'is_visitor'        => $visitor )
			             ,array('%d', '%d', '%d', '%d', '%d', '%d'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: added $result rows");
		return $result;
	}

	/**
	 * Remove relationship between an Entrant and a Match
	 * @param int $eventId The ID of the event
	 * @param int $bracket The bracket number
	 * @param int $roundNum The round number of the match
	 * @param int $matchNum The match number
	 * @param int $pos The position of the entrant in the Bracket signup
	 * @return int Number of rows affected
	 */
	static function remove(int $eventId, int $bracket, int $roundNum, int $matchNum, int $pos ):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete($table
					 ,array( 'match_event_ID'    => $eventId
						   , 'match_bracket_num' => $bracket
						   , 'match_round_num'   => $roundNum
						   , 'match_num'         => $matchNum
						   , 'entrant_position'  => $pos )
					 ,array( '%d', '%d', '%d', '%d', '%d' ) );
		$result = $wpdb->rows_affected;
		
		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result rows");
		return $result;
	}

	/**
	 * Remove relationships between all Entrants (in a bracket) and a given Match
	 * @param int $eventId The ID of the event
	 * @param int $bracket The bracket number
	 * @param int $roundNum The round number of the match
	 * @param int $matchNum The match number
	 * @return int Number of rows affected
	 */
	static function removeAllFromMatch(int $eventId, int $bracket, int $roundNum, int $matchNum ):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete($table
					 ,array( 'match_event_ID'    => $eventId
						   , 'match_bracket_num' => $bracket
						   , 'match_round_num'   => $roundNum
						   , 'match_num'         => $matchNum )
					 ,array( '%d', '%d', '%d', '%d' ) );
		$result = $wpdb->rows_affected;
		
		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result row(s)");
		return $result;
	}

	
	/**
	 * Remove relationships between all Entrants for the Bracket from all matches
	 * @param int $eventId The ID of the event
	 * @param int $bracket The number of the bracket belonging to the event
	 * @return int The number of rows affected
	 */
	static function removeAllFromBracket(int $eventId = 0, int $bracket = 0 ):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;
		if( 0 === $eventId || 0 === $bracket ) return $result;

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete($table
					 ,array( 'match_event_ID'    => $eventId
						   , 'match_bracket_num' => $bracket )
					 ,array( '%d', '%d' ) );
		$result = $wpdb->rows_affected;
		
		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result row(s)");
		return $result;
	}

	/**
	 * Remove a relationships between all Matches (in a bracket) and a given Entrant
	 * @param int $eventId The ID of the event
	 * @param int $bracket The bracket number
	 * @param int $pos The position of the Entrant in the signup in the Bracket
	 * @return int Number of rows affected
	 */
	static function removeAllFromEntrant(int $eventId, int $bracket, $pos ):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete($table
					 ,array( 'match_event_ID'    => $eventId
						   , 'match_bracket_num' => $bracket
						   , 'entrant_position'  => $pos )
					 ,array( '%d', '%d', '%d' ) );
		$result = $wpdb->rows_affected;
		
		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result rows");
		return $result;
	}
	
} //end of class