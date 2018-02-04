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
	 * Remove a join between a Club and an Event
	 */
	static function remove(int $clubId, int $eventId):int {
		if(!isset($clubId) || !isset($eventId)) return 0;

		global $wpdb;
		$wpdb->show_errors(); 
		$table = $wpdb->prefix . 'tennis_club_event';

		$wpdb->delete($table,array('club_ID'=>$clubId,'event_ID'=>$eventId),array('%d','%d'));
		$result = $wpdb->rows_affected;


		error_log("ClubEventRelations: Last error='$wpdb->last_error'");
		
		error_log("ClubEventRelations.delete: deleted $result rows");
		return $result;
	}

	/**
	 * Create join between a Club and an Event
	 */
	static function add(int $clubId, int $eventId):int {
		if(!isset($clubId) || !isset($eventId)) return 0;
		global $wpdb;
		$wpdb->show_errors(); 

		$table = $wpdb->prefix . 'tennis_club_event';
		$formats_value = array('%d','%d');
		$values = array('club_ID'=>$clubId, 'event_ID'=>$eventId);
		$wpdb->insert($table,$values,$formats_values);
		$result = $wpdb->rows_affected;

		error_log("ClubEventRelations: Last error='$wpdb->last_error'");
		
		error_log("ClubEventRelations.add: added $result rows");
		return $result;
	}
	
} //end of class