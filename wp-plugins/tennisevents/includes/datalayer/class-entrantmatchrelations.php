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
	private static $tablename = 'tennis_match_entrant';
	/**
	 * Remove a relationship between an Entrant and a Match
	 */
	static function remove(int $eventId, int $roundNum, int $matchNum, int $pos ):int {
		$result = 0;
		global $wpdb;
		if(isset($eventId) && isset($roundNum) && isset($matchNum) && isset($pos)) {
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete($table
			             ,array('match_event_ID'=>$eventId,'match_round_num'=>$roundNum,'mmatch_num'=>$matchNum,'entrant_position'=>$pos)
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
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->insert($table
			             ,array('match_event_ID'=>$eventId,'match_round_num'=>$roundNum,'match_num'=>$matchNum,'entrant_position'=>$pos)
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