<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Provides functions to support relationships between
 * clubs and events.
 * @class  ClubEventRelations
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ClubEventRelations {

	public static $tablename = 'tennis_club_event';
	
	/**
	 * Remove a relationship between a Club and an Event
	 * @param int $clubId Primary key for club
	 * @param int $eventId Primary key for event
	 * @return int Number of rows affected
	 */
	static function remove(int $clubId, int $eventId):int {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
		global $wpdb;
		if(isset($clubId) && isset($eventId)) {
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete($table,array('club_ID'=>$clubId,'event_ID'=>$eventId),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		if($wpdb->last_error) {
			error_log("ClubEventRelations.remove: Last error='$wpdb->last_error'");
		}
		
		error_log("ClubEventRelations.remove: deleted $result rows");
		return $result;
	}
	
	/**
	 * Remove all relationships for the given Club and any Events
	 * @param int $clubId Primary key for the club
	 * @return int Number of rows affected
	 */
	static function removeAllForClub( int $clubId ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		
		$result = 0;
		global $wpdb;
		if( isset($clubId) ) {
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete($table,array('club_ID'=>$clubId ),array('%d'));
			$result = $wpdb->rows_affected;
		}
		if($wpdb->last_error) {
			error_log("$loc: Last DB error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result row(s)");
		return $result;
	}
	
	/**
	 * Remove all relationships for the given Event and any Clubs
	 * @param int $eventId The primary key for the event
	 * @return int Number of rows affected
	 */
	static function removeAllForEvent( int $eventId ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		
		$result = 0;
		global $wpdb;
		if( isset($eventId) ) {
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete($table,array('event_ID'=>$eventId ),array('%d'));
			$result = $wpdb->rows_affected;
		}
		if($wpdb->last_error) {
			error_log("$loc: Last DB error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result row(s)");
		return $result;
	}

	/**
	 * Create a relationship between a Club and an Event
	 * @param int $clubId Primary key for club
	 * @param int $eventId Primary key for event
	 * @return int The rows affected
	 */
	static function add(int $clubId, int $eventId):int {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;
		global $wpdb;
		
		$query = "SELECT IFNULL(count(*),0) FROM {$wpdb->prefix}tennis_club_event
				  WHERE club_ID=%d and event_ID=%d;";
		$safe = $wpdb->prepare($query,$clubId,$eventId);
		$num = $wpdb->get_var($safe);
		error_log("ClubEventRelations::add number found=$num");

		if( isset($clubId) && isset($eventId) && $num == 0 ) {
			$table = $wpdb->prefix . 'tennis_club_event';
			$wpdb->insert($table,array('club_ID'=>$clubId, 'event_ID'=>$eventId),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: added $result rows");
		return $result;
	}
	
} //end of class