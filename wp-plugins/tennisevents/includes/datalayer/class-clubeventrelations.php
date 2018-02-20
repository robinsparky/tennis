<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Provides functions to support the more complex
 * data operations such as maintinaing intersection tables.
 * @class  DataRelations
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ClubEventRelations {
	
	/**
	 * Remove a relationship between a Club and an Event
	 */
	static function remove(int $clubId, int $eventId):int {
		$result = 0;
		global $wpdb;
		if(isset($clubId) && isset($eventId)) {
			$table = $wpdb->prefix . 'tennis_club_event';
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
	 * Create a relationship between a Club and an Event
	 */
	static function add(int $clubId, int $eventId):int {
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
			error_log("ClubEventRelations.add: Last error='$wpdb->last_error'");
		}
		
		error_log("ClubEventRelations.add: added $result rows");
		return $result;
	}
	
} //end of class