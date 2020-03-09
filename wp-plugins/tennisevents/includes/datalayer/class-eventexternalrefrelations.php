<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Provides functions to support the more complex
 * data operations such as maintaining intersection tables.
 * @class  EventExternalRefRelations
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class EventExternalRefRelations {
	
	/**
	 * Remove a relationship between an Event and an external reference
	 */
	static function remove(int $eventId, $extRef ):int {
		$loc = __CLASS__ . __FUNCTION__;
		$result = 0;
		global $wpdb;
		if( isset($extRef) && isset($eventId) ) {
			$table = $wpdb->prefix . 'tennis_external_event';
			$wpdb->delete($table,array( 'event_ID'=>$eventId, 'external_ID' => $extRef ),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		if( $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result rows");
		return $result;
	}

	/**
	 * Create a relationship between an Event and external reference
	 */
	static function add( int $eventId, $extRef ):int {
		$loc = __CLASS__ . __FUNCTION__;

		$result = 0;
		global $wpdb;
		
		$query = "SELECT IFNULL(count(*),0) FROM {$wpdb->prefix}tennis_external_event
				  WHERE event_ID=%d and external_ID='%s';";
		$safe = $wpdb->prepare( $query, $eventId, $extRef );
		$num = $wpdb->get_var( $safe );
		error_log("$loc: number found=$num");

		if( isset( $extRef ) && isset( $eventId ) && $num == 0 ) {
			$table = $wpdb->prefix . 'tennis_external_event';
			$wpdb->insert($table,array( 'event_ID'=>$eventId, 'external_ID' => $extRef ),array('%d','%s'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: added $result rows");
		return $result;
	}

	/**
	 * Fetch all external references for a given event id
	 */
	public static function fetchExternalRefs( int $event_ID ) {
		$loc = __CLASS__ . __FUNCTION__;

		$result = 0;
		global $wpdb;
		
		$query = "SELECT external_ID FROM {$wpdb->prefix}tennis_external_event
				  WHERE event_ID=%d;";
		$safe = $wpdb->prepare( $query, $event_ID );
		$rows = $wpdb->get_results( $safe );
		error_log("$loc: For event '$event_ID' the number external refs found=$wpdb->rows_affected");
		return $rows;
	}
	
} //end of class