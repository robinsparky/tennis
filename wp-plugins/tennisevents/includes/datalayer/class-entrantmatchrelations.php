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
class EntrantMatchRelations {
	
	/**
	 * Remove a relationship between an Entrant and a Match
	 */
	static function remove(int $eventId, int $roundNum, int $matchNum, int $pos ):int {
		$result = 0;
		global $wpdb;
		if(isset($eventId) && isset($roundNum) && isset($matchNum)) {
			$table = $wpdb->prefix . 'tennis_entrant_match';
			$wpdb->delete($table
			             ,array('event_ID'=>$eventId,'round_num'=>$roundNum,'match_num'=>$matchNum,'entrant_position'=>$pos)
			             ,array('%d','%d','%d','%d'));
			$result = $wpdb->rows_affected;
		}
		if($wpdb->last_error) {
			error_log("EntrantMatchRelations.remove: Last error='$wpdb->last_error'");
		}
		
		error_log("EntrantMatchRelations.remove: deleted $result rows");
		return $result;
	}

	/**
	 * Create a relationship between a Entrant and a Match
	 */
	static function add( int $eventId, int $roundNum, int $matchNum, int $pos ):int {
		$result = 0;
		global $wpdb;
		if(isset($eventId) && isset($roundNum) && isset($matchNum) && isset($pos)) {
			$table = $wpdb->prefix . 'tennis_entrant_match';
			$wpdb->insert($table
			             ,array('event_ID'=>$eventId,'round_num'=>$roundNum,'match_num'=>$matchNum,'entrant_position'=>$pos)
			             ,array('%d','%d','%d','%d'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			if(!strpos($wpdb->last_error,'Duplicate entry')) {
				throw new Exception($wpdb->last_error);
			}
			else {
				error_log("EntrantMatchRelations.add: Last error='$wpdb->last_error'");
			}
		}
		
		error_log("EntrantMatchRelations.add: added $result rows");
		return $result;
	}
	
} //end of class