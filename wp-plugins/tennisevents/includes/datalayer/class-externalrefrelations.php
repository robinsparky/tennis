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
class ExternalRefRelations {
	
	/**
	 * Remove a relationship between and Club and an external reference
	 */
	static function remove(string $target, int $Id, string $extRef ):int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($target, $Id, $extRef)");

		$result = 0;
		global $wpdb;
		if( isset($extRef) && isset($Id) ) {
			switch( $target ) {
				case 'club':					
					$table = $wpdb->prefix . 'tennis_external_club';
					$col_ID = 'club_ID';
					break;
				case 'event':
					$table = $wpdb->prefix . 'tennis_external_event';
					$col_ID = 'event_ID';
					break;
				default:
					return 0; //early return
			}
			$wpdb->delete($table,array( $col_ID => $Id, 'external_ID' => $extRef ),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		if( $wpdb->last_error ) {
			error_log("$loc: Target=$target Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: Target=$target deleted $result rows");
		return $result;
	}

	/**
	 * Create a relationship between a Club and external reference
	 */
	static function add(string $target, int $Id, string $extRef ):int {
		$loc = __CLASS__ . '::' .  __FUNCTION__;
		error_log("{$loc}($target, $Id, $extRef)");

		$result = 0;
		global $wpdb;
				
		switch( $target ) {
			case 'club':					
				$table = $wpdb->prefix . 'tennis_external_club';
				$col_ID = 'club_ID';
				break;
			case 'event':
				$table = $wpdb->prefix . 'tennis_external_event';
				$col_ID = 'event_ID';
				break;
			default:
				return $result; //early return
		}
		
		$query = "SELECT IFNULL(count(*),0) FROM $table
				  WHERE {$col_ID}=%d and external_ID='%s';";
		$safe = $wpdb->prepare( $query, $Id, $extRef );
		$num = $wpdb->get_var( $safe );
		error_log("$loc: Target=$target number found=$num");

		if( isset( $extRef ) && isset( $Id ) && $num == 0 ) {
			$wpdb->insert($table, array( $col_ID => $Id, 'external_ID' => $extRef )
			              ,array('%d','%s'));
			$result = $wpdb->rows_affected;
		}

		if($wpdb->last_error) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: Target=$target added $result external reference rows");
		return $result;
	}

	/**
	 * Fetch all external references for a given club id
	 */
	public static function fetchExternalRefs(string $target, int $Id ) {
		$loc = __CLASS__ .'::' .  __FUNCTION__;

		global $wpdb;

		$result = array();
		
		switch( $target ) {
			case 'club':					
				$table = $wpdb->prefix . 'tennis_external_club';
				$col_ID = 'club_ID';
				break;
			case 'event':
				$table = $wpdb->prefix . 'tennis_external_event';
				$col_ID = 'event_ID';
				break;
			default:
				return $result; //early return
		}
		
		$query = "SELECT external_ID FROM {$table}
				  WHERE {$col_ID}=%d;";
		$safe = $wpdb->prepare( $query, $Id );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		$found = count( $rows );
		error_log("$loc: Target=$target ID='$Id' and the number external refs found={$found}");

		$result = array_map( function( $row ) { return $row['external_ID']; }, $rows );

		return $result;
	}
	
} //end of class