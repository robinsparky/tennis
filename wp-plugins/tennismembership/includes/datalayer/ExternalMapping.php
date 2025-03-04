<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Provides functions to support the more complex
 * data operations such as maintaining intersection tables.
 * @class  ExternalMap
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class ExternalMapping {

	public const ExternalMapTable = 'externalmap';
	
	/**
	 * Remove a relationship between and Club and an external reference
	 */
	static function remove(string $subject, int $Id, string $extRef = "" ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($subject, $Id, $extRef)");

		$subject = strtolower($subject);
		$result = 0;
		$table = TM()->getInstaller()->getDBTablenames()[ExternalMapping::ExternalMapTable];

		global $wpdb;
		if( !empty( $extRef ) ) {
			//Delete specific intersection for this subject
			$wpdb->delete($table,array( 'internal_ID' => $Id, 'external_ID' => $extRef, 'subject' => $subject ),array('%d','%d','%s'));
		}
		else {
			//Delete all intersections for this subject
			$wpdb->delete($table,array( 'internal_ID' => $Id, 'subject' => $subject ),array('%d','%s'));
		}
		$result = $wpdb->rows_affected;
	
		if( $wpdb->last_error ) {
			error_log("{$loc}($subject, $Id, $extRef) Last error='$wpdb->last_error'");
		}
		
		error_log("{$loc}($subject, $Id, $extRef) deleted $result rows");
		return $result;
	}

	/**
	 * Create a relationship between a subject and external reference
	 */
	static function add(string $subject, int $Id, string $extRef ) : int {
		$loc = __CLASS__ . '::' .  __FUNCTION__;
		$table = TM()->getInstaller()->getDBTablenames()[ExternalMapping::ExternalMapTable];
		error_log("{$loc}($subject, $Id, $extRef) uses table {$table}");


		$subject = strtolower($subject);
		$result = 0;

		global $wpdb;
		
		$query = "SELECT IFNULL(count(*),0) FROM {$table}
				  WHERE internal_ID=%d and external_ID='%s' and subject='%s';";
		$safe = $wpdb->prepare( $query, $Id, $extRef, $subject );
		$num = $wpdb->get_var( $safe );
		error_log("{$loc}($subject, $Id, $extRef): found $num rows");

		if( isset( $extRef ) && isset( $Id ) && $num == 0 ) {
			$result = $wpdb->insert($table, array( 'internal_ID' => $Id, 'external_ID' => $extRef, 'subject' => $subject ),array('%d','%s','%s'));
		}

		if(!$result && $wpdb->last_error !== '') {
			$mess = "{$loc}($subject, $Id, $extRef): Last error='$wpdb->last_error'";
			error_log($mess);	
			throw new \InvalidArgumentException($mess);
		}
		
		error_log("{$loc}($subject, $Id, $extRef): added $result external mapping rows");
		return $result;
	}

	/**
	 * Fetch all external references for a given internal id and subject
	 */
	public static function fetchExternalRefs(string $subject, int $Id ) {
		$loc = __CLASS__ .'::' .  __FUNCTION__;
		error_log("{$loc}($subject, $Id)");

		$table = TM()->getInstaller()->getDBTablenames()[ExternalMapping::ExternalMapTable];

		$subject = strtolower($subject);
		$result = array();

		global $wpdb;
		$query = "SELECT external_ID FROM {$table}
				  WHERE internal_ID=%d and subject='%s';";
		$safe = $wpdb->prepare( $query, $Id, $subject );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		$found = count( $rows );
		error_log("{$loc}($subject, $Id) the number external refs found={$found}");

		$result = array_map( function( $row ) { return $row['external_ID']; }, $rows );

		return $result;
	}

	/**
	 * Fetch all Subject Ids for a given external reference
	 */
	public static function fetchInternalIds(string $subject, string $ref ) {
		$loc = __CLASS__ .'::' .  __FUNCTION__;
		error_log("{$loc}($subject, $ref)");

		$table = TM()->getInstaller()->getDBTablenames()[ExternalMapping::ExternalMapTable];

		$subject = strtolower($subject);
		global $wpdb;

		$result = array();
		
		$query = "SELECT internal_ID FROM {$table}
				  WHERE external_ID='%s' and subject=='%s';";
		$safe = $wpdb->prepare( $query, $ref, $subject );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		$found = count( $rows );
		error_log("{$loc}($subject, $ref) the number ids found={$found}");

		$result = array_map( function( $row ) { return $row['internal_ID']; }, $rows );

		return $result;
	}
	
} //end of class