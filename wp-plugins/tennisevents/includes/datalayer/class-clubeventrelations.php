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
		if(isset($clubId) && isset($eventId)) {
			$table = $wpdb->prefix . 'tennis_club_event';
			$wpdb->insert($table,array('club_ID'=>$clubId, 'event_ID'=>$eventId),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			if(!strpos($wpdb->last_error,'Duplicate entry')) {
				throw new Exception($wpdb->last_error);
			}
			else {
				error_log("ClubEventRelations.add: Last error='$wpdb->last_error'");
			}
		}
		
		error_log("ClubEventRelations.add: added $result rows");
		return $result;
	}
	
} //end of class