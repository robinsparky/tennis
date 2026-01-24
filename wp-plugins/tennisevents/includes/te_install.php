<?php
use cpt\TennisEventCpt;
use cpt\TennisClubCpt;
use commonlib\BaseLogger;
use api\TournamentDirector;
use api\TennisConfigurationException;
use datalayer\Club;
use datalayer\Event;

/**
 * Installation related functions and actions.
 *
 * @author   Robin Smith
 * @category Admin
 * @package  Tennis Events
 * @version  1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * TE_Install Class.
 */
class TE_Install {
	const TOURNAMENTDIRECTOR_ROLENAME = "tennis_tournament_director";
	const CHAIRUMPIRE_ROLENAME = "tennis_chair_umpire";
	const TENNISPLAYER_ROLENAME = "tennis_player";

	const SCORE_MATCHES_CAP = 'score_matches';
	const RESET_MATCHES_CAP = 'reset_matches';
	const MANAGE_EVENTS_CAP = 'manage_events';

	static public $tennisRoles=array(self::TENNISPLAYER_ROLENAME => "Tennis Player"
									,self::CHAIRUMPIRE_ROLENAME  => "Chair Umpire"
								    ,self::TOURNAMENTDIRECTOR_ROLENAME => "Tournament Director");
	
	static private $chairUmpireCaps=array(self::SCORE_MATCHES_CAP => 1
											,self::RESET_MATCHES_CAP => 0
											,self::MANAGE_EVENTS_CAP => 0 );

	static private $tournamentDirectorCaps=array(self::SCORE_MATCHES_CAP => 1
												,self::RESET_MATCHES_CAP => 1
												,self::MANAGE_EVENTS_CAP => 1 );

	private $dbTableNames; 
	private $log;

	/**
	 * A reference to an instance of this class.
	 *
	 * @since 0.1.0
	 *
	 * @var   TE_Install singleton
	 */
	private static $instance;

    /**
    * TE_Install Singleton
    *
    * @return   TE_Install
    * @since    1.2.0
    */
    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
	} // end getInstance
	
	private function __construct()	{
		$this->log = new BaseLogger( true );

		global $wpdb;
		$this->dbTableNames = array("club"					=> $wpdb->prefix . "tennis_club"
								   ,"court"					=> $wpdb->prefix . "tennis_court"
								   ,"event"					=> $wpdb->prefix . "tennis_event"
								   ,"bracket"				=> $wpdb->prefix . "tennis_bracket"
								   ,"entrant"				=> $wpdb->prefix . "tennis_entrant"
								   ,"match"					=> $wpdb->prefix . "tennis_match"
								   ,"set"					=> $wpdb->prefix . "tennis_set"
								   ,"player"				=> $wpdb->prefix . "tennis_player"
								   ,"team"					=> $wpdb->prefix . "tennis_team"
								   ,"squad"					=> $wpdb->prefix . "tennis_squad"
								   ,"squad_player"			=> $wpdb->prefix . "tennis_team_squad_player"
								   ,"player_entrant"		=> $wpdb->prefix . "tennis_player_entrant"
								   ,"match_entrant"			=> $wpdb->prefix . "tennis_match_entrant"
								   ,"match_team_player"		=> $wpdb->prefix . "tennis_match_team_squad_player"
								   ,"court_booking"			=> $wpdb->prefix . "tennis_court_booking"
								   ,"match_court_booking"	=> $wpdb->prefix . "tennis_match_court_booking"
								   ,"club_event"			=> $wpdb->prefix . "tennis_club_event"
								   ,"external_event"        => $wpdb->prefix . "tennis_external_event"
								   ,"external_club"			=> $wpdb->prefix . "tennis_external_club"
								);
		
        add_filter( 'query_vars', array( $this,'add_query_vars_filter' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_script' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_style' ) );

	}

	public function getDBTablenames() {
		return $this->dbTableNames;
	}

	/**
	 * Activate Tennis Events.
	 */
	public function activate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc Start+++++++++++++++++++++++++++++++");

		// clear the permalinks after the post type has been registered
		flush_rewrite_rules();

		$this->addRoles();
		$this->addCap();
		$this->create_options();
		$this->createSchema();
		add_filter( 'wp_nav_menu_items', array( $this,'add_todaysdate_in_menu' ), 10, 2 );
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}

	public function deactivate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc Start+++++++++++++++++++++++++++++++");
		// unregister the post type, so the rules are no longer in memory
		unregister_post_type( TennisEventCpt::CUSTOM_POST_TYPE );
		unregister_post_type( TennisClubCpt::CUSTOM_POST_TYPE );
		// clear the permalinks to remove our post type's rules from the database
		flush_rewrite_rules();

		//remove roles?
		//$this->removeCap();
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}

	public function uninstall() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc Start+++++++++++++++++++++++++++++++");
		// exit if uninstall constant is not defined
		if (!defined('WP_UNINSTALL_PLUGIN')) exit;

		$this->removeCap();
		$this->removeRoles();
		$this->delete_options();
		$this->delete_customPostTypes();
		$this->delete_customTaxonomies();
		$this->delete_transients();
		$this->delete_cron_jobs();
		$this->delete_user_meta();
		$this->dropSchema();
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}
	
	protected function create_options() {
		update_option( TennisEvents::OPTION_NAME_VERSION , TennisEvents::VERSION, false );
	}
	
	/**
	 * Delete transient data
	 */
	protected function delete_transients() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		//delete any existing transients
		// $transients = array(
		// 	'myplugin_transient_1',
		// 	'myplugin_transient_2',
		// 	'myplugin_transient_3',
		// );
		// foreach ($transients as $transient) {
		// 	delete_transient($transient);
		// }
	}

	/**
	 * Delete cron jobs
	 */
	protected function delete_cron_jobs() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		//delete cron jobs
		// $timestamp = wp_next_scheduled('myplugin_cron_event');
		// wp_unschedule_event($timestamp, 'myplugin_cron_event');

	}

	/**
	 * Delete options for this plugin
	 */
	protected function delete_options() {
		delete_option( TennisEvents::OPTION_NAME_VERSION );
		delete_option( TennisEvents::OPTION_NAME_SEEDED );
	}

	/**
	 * Delete user meta data
	 */
	protected function delete_user_meta() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
	// 	$users = get_users();
	// 	foreach ($users as $user) {
	// 		delete_user_meta($user->ID, 'myplugin_user_meta');
	// 	}
	}

	/**
	 * Delete Custom Post Types for this plugin:
	 * TennisEventCpt, TennisClubCpt
	 */
	protected function delete_customPostTypes() {
		$eventposts = get_posts( array( 'post_type' => TennisEventCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		foreach( $eventposts as $cpt ) {
			wp_delete_post( $cpt->ID, true );
		}
		$clubposts = get_posts( array( 'post_type' => TennisClubCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		foreach( $clubposts as $cpt ) {
			wp_delete_post( $cpt->ID, true );
		}
	}

	/**
	 * Delete custom taxonomies 
	 */
	protected function delete_customTaxonomies() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$this->delete_customTerms(TennisEventCpt::CUSTOM_POST_TYPE_TAX);
		$this->delete_customTerms(TennisClubCpt::CUSTOM_POST_TYPE_TAX);

		/*
		//Events
		$terms = get_terms( array('taxonomy' => TennisEventCpt::CUSTOM_POST_TYPE_TAX
									,'fields' => 'ids'
									,'hide_empty' => false));
		
		if( is_wp_error($terms) ) {
			$this->log->error_log("$loc: for tax='Tennis Event Tax' with error='{$terms->get_error_message()}'.");
		}
		else {
			foreach($terms as $term_id ) {
				$this->log->error_log("$loc: for tax='Tennis Event Tax' term id='{$term_id}'.");

				if( wp_delete_category( $term_id) ) {
					$this->log->error_log("$loc: for tax='Tennis Event Tax' deleted '{$term_id}' successfully.");
				}
				else {					
					$this->log->error_log("$loc: for tax='Tennis Event Tax' delete '{$term_id}' failed.");
				}
			}
		}
		
		//Clubs
		$terms = get_terms( array('taxonomy'=>TennisClubCpt::CUSTOM_POST_TYPE_TAX
							,'fields' => 'ids'
							,'hide_empty'=>false));
		if( is_wp_error($terms) ) {
			$this->log->error_log("$loc: for tax='Tennis Club Tax' with error='{$terms->get_error_message()}'.");
		}
		else {
			foreach($terms as $term_id ) {					
				$this->log->error_log("$loc: for tax='Tennis Club Tax' term id='{$term_id}'.");
				echo("$loc: for tax='Tennis Club Tax' term id='{$term->term_id}'.");
				if( wp_delete_category( $term_id) ) {
					$this->log->error_log("$loc: for tax='Tennis Club Tax' deleted '{$term_id}' successfully.");
				}
				else {					
					$this->log->error_log("$loc: for tax='Tennis Club Tax' delete '{$term_id}' failed.");
				}
			}
		}
		*/
	}
	
	/**
	 * Delete custom taxonomies using sql
	 * @param string $taxonomy is the slug of the custom taxonomy
	 */
	protected function delete_customTerms($taxonomy) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		global $wpdb;
	
		$tax_table = $wpdb->prefix . 'term_taxonomy';
		$terms_table = $wpdb->prefix . 'terms';

		$query = "SELECT t.name, t.term_id
				FROM  {$terms_table} AS t
				INNER JOIN {$tax_table}  AS tt
				ON t.term_id = tt.term_id
				WHERE tt.taxonomy = '%s'";
	
		$safe = $wpdb->prepare( $query, $taxonomy );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
	
		foreach ($rows as $row) {				
			$this->log->error_log("$loc: for tax='$taxonomy', term id='{$row['term_id']}'.");

			if( wp_delete_term( intval($row['term_id']), $taxonomy ) ) {
				$this->log->error_log("$loc: for tax='{$taxonomy}' deleted '{$row['term_id']}' successfully.");
			}
			else {
				$this->log->error_log("$loc: for tax='{$taxonomy}' delete '{$row['term_id']}' failed.");
			}
		}
	}

	/**
	 * Add the roles of 'Tennis Player', 'Tournament Director', 'Chair Umpire', etc.
	 */
	private function addRoles() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$caps = array();
		foreach(self::$tennisRoles as $slug => $name ) {
			switch($slug) {
				case self::TOURNAMENTDIRECTOR_ROLENAME:			
					$caps = array_merge(self::$tournamentDirectorCaps, get_role('subscriber')->capabilities );
					break;
				case self::CHAIRUMPIRE_ROLENAME:
					$caps = array_merge(self::$chairUmpireCaps, get_role('subscriber')->capabilities );
					break;
				case self::TENNISPLAYER_ROLENAME;
				default;
					$caps = get_role('subscriber')->capabilities;
			}

			$this->log->error_log($caps, "$loc: {$name} caps...");
			remove_role( $slug );
			$result = add_role( $slug, $name, $caps );
	
			if( null !== $result ) {
				$this->log->error_log( "Role '{$name}' added." );
			}
			else {
				$this->log->error_log( "Could not add role '{$name}'." );
			}
		} //foreach
	}

	/**
	 * Remove the roles of 'Tennis Player', 'Tournament Director', etc.
	 */
	protected function removeRoles() {
		foreach (self::$tennisRoles as $slug => $name) {
			remove_role( $slug );
		}
	}
	
    /**
	 * Add the new tennis capabilities to all roles having the 'manage_options' capability
	 */
    private function addCap() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

        $roles = get_editable_roles();

        foreach ($GLOBALS['wp_roles']->role_objects as $key => $role) {
            if (isset($roles[$key]) && $role->has_cap('manage_options')) {
				foreach(self::$tournamentDirectorCaps as $cap => $grant) {
                	$role->add_cap( $cap );
				}
            }
        }
	}

	/**
	 * Remove the tennis-specific custom capabilities
	 */
	private function removeCap() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$roles = get_editable_roles();
		foreach ($GLOBALS['wp_roles']->role_objects as $key => $role) {
			if (isset($roles[$key]) && $role->has_cap('manage_options')) {
				foreach(self::$tournamentDirectorCaps as $cap => $grant) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Create the Tennis Events schema
	 * TODO: test using dbDelta
	 */
	public function createSchema( bool $withReports=false ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		global $wpdb;
		if($withReports) $wpdb->show_errors(); 
		
		$club_table 				= $this->dbTableNames["club"];
		$court_table 				= $this->dbTableNames["court"];
		$event_table 				= $this->dbTableNames["event"];
		$bracket_table 				= $this->dbTableNames["bracket"];
		$entrant_table 				= $this->dbTableNames["entrant"];
		$match_table 				= $this->dbTableNames["match"];
		$set_table 					= $this->dbTableNames["set"];
		$player_table 				= $this->dbTableNames["player"];
		$team_table 				= $this->dbTableNames["team"];
		$squad_table 				= $this->dbTableNames["squad"];
		$team_squad_player_table 	= $this->dbTableNames["squad_player"];
		$player_entrant_table 		= $this->dbTableNames["player_entrant"];
		$match_entrant_table 		= $this->dbTableNames["match_entrant"];
		$match_player_table 		= $this->dbTableNames["match_team_player"];
		$booking_table 				= $this->dbTableNames["court_booking"];
		$booking_match_table 		= $this->dbTableNames["match_court_booking"];
		$club_event_table			= $this->dbTableNames["club_event"];
		$ext_event_ref_table        = $this->dbTableNames["external_event"];
		$ext_club_ref_table         = $this->dbTableNames["external_club"];

		//Check if schema already installed
		$newSchema = true;
		if( $wpdb->get_var("SHOW TABLES LIKE '$club_table'") == $club_table ) {
			$newSchema = false;
		}

		//Temporarily until can test/fix dbDelta usage 
		//see https://codex.wordpress.org/Creating_Tables_with_Plugins
		if( ! $newSchema ) return;

		/**
		 * Club or venue that provides tennis courts
		 */
		$sql = "CREATE TABLE `$club_table` ( 
					`ID` INT NOT NULL AUTO_INCREMENT,
					`name` VARCHAR(255) NOT NULL,
					PRIMARY KEY (`ID`) 
				) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $club_table" : "$club_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$club_table");
		}
		
		/**
		 * This table enables a relationship
		 * between clubs and external clubs in a foreign schema table 
		 * Namely, the custom post type in WordPress called TennisClubCPT 
		 * ,
			CONSTRAINT `fk_ext_club`
			FOREIGN KEY (`club_ID`)
				REFERENCES `$club_table` (`ID`)
				ON DELETE CASCADE
				ON UPDATE NO ACTION
		 */
		$sql = "CREATE TABLE `$ext_club_ref_table` (
			`club_ID` INT NOT NULL,
			`external_ID` NVARCHAR(100) NOT NULL,
			INDEX USING BTREE (`external_ID`),
			PRIMARY KEY (`club_ID`, `external_ID`)
			) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $ext_club_ref_table" : "$ext_event_ref_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$ext_club_ref_table");
		}

		/**
		 * Court is the surface on which you play tennis!
		 * ,
				CONSTRAINT `fk_court_club`
				FOREIGN KEY (`club_ID`)
				  REFERENCES `$club_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE NO ACTION
		 */
		$sql = "CREATE TABLE `$court_table` (
				`club_ID` INT NOT NULL,
				`court_num` INT NOT NULL,
				`court_type` VARCHAR(45) NOT NULL DEFAULT 'hard',
				PRIMARY KEY (`club_ID`,`court_num`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $court_table" : "$court_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$court_table");
		}

		/**
		 * Court bookings are recorded in this table.
		 * ,
			CONSTRAINT `fk_club_court_booking`
			FOREIGN KEY (`club_ID`,`court_num`)
			  REFERENCES $court_table (`club_ID`,`court_num`)
			  ON DELETE CASCADE
			  ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE $booking_table (
			`ID` INT NOT NULL AUTO_INCREMENT,
			`club_ID` INT NOT NULL,
			`court_num` INT NOT NULL,
			`book_date` DATE NULL,
			`book_time` TIME(6) NULL,
			PRIMARY KEY (`ID`)
			) ENGINE=MyISAM;";	
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $booking_table" : "$booking_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$booking_table");
		}

		/**
		 * Events are hierarchical entities
		 * representing leagues, tournaments, ladder, etc.
		 * For example an event called 'Year End Tournament' 
		 * having sub-events: 'Mens Singles', 'Mens Doubles', 'Womens Doubles', etc.
		 * ,
				CONSTRAINT `fk_hierarchy`
				FOREIGN KEY (`parent_ID`)
				  REFERENCES `$event_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$event_table` (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(256) NOT NULL,
				`parent_ID` INT NULL COMMENT 'parent event',
				`event_type` VARCHAR(25) NULL COMMENT 'tournament, league, ladder',
				`score_type` VARCHAR(25) NULL COMMENT 'best2of3, best3or5, fast4, pro-set etc',
				`match_type` VARCHAR(10) COMMENT 'singles, doubles',
				`gender_type` VARCHAR(10) COMMENT 'males, females or mixed',
				`age_min` INT DEFAULT 1,
				`age_max` INT DEFAULT 99,
				`format` VARCHAR(25) NULL COMMENT 'elimination, round robin',
				`signup_by` DATE NULL,
				`start_date` DATE NULL,
				`end_date` DATE NULL,
				`num_brackets` INT DEFAULT 1,
				PRIMARY KEY (`ID`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $event_table" : "$event_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$event_table");
		}
	
		/**
		 * This table enables a relationship
		 * between events and external events in a foreign schema table 
		 * Namely, the custom post type in WordPress called TennisEventCPT 
		 * ,
				CONSTRAINT `fk_ext_event`
				FOREIGN KEY (`event_ID`)
					REFERENCES `$event_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION
		 */
		$sql = "CREATE TABLE `$ext_event_ref_table` (
				`event_ID` INT NOT NULL,
				`external_ID` NVARCHAR(100) NOT NULL,
				INDEX `external_event` USING BTREE (`external_ID`),
				PRIMARY KEY (`event_ID`, `external_ID`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $ext_event_ref_table" : "$ext_event_ref_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$ext_event_ref_table");
		}

		/**
		 * This table enables a many-to-many relationship
		 * between clubs and events 
		 * paving the way for interclub leagues and tournaments
		 * ,
				CONSTRAINT `fk_club_event`
				FOREIGN KEY (`club_ID`)
					REFERENCES `$club_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION,
				CONSTRAINT `fk_event_club`
				FOREIGN KEY (`event_ID`)
					REFERENCES `$event_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION
		 */
		$sql = "CREATE TABLE `$club_event_table` (
				`club_ID` INT NOT NULL,
				`event_ID` INT NOT NULL,
				PRIMARY KEY(`club_ID`,`event_ID`)
				) ENGINE=MyISAM;";	
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $club_event_table" : "Created table '$club_event_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$club_event_table");
		}

		/**
		 * Brackets divide a draw into sections such as Main vs Consolation
		 * or Box1, Box2, etc for Ladders
		 * ,
			CONSTRAINT `fk_bracket_event`
			FOREIGN KEY (`event_ID`)
			   REFERENCES `$event_table` (`ID`)
			  ON DELETE CASCADE
			  ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$bracket_table` (
			`event_ID` INT NOT NULL,
			`bracket_num` INT NOT NULL,
			`is_approved` TINYINT NOT NULL DEFAULT 0,
			`name` VARCHAR(256) NOT NULL,
			PRIMARY KEY (`event_ID`,`bracket_num`)
			) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $bracket_table" : "Created table '$bracket_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$bracket_table");
		}

		/**
		 * An entrant into a tournament event.
		 * The relationship between the event and all entrants is called a draw.
		 * This can be a single player or a doubles pair.
		 * ,
				CONSTRAINT `fk_entrant_bracket`
				FOREIGN KEY  (`event_ID`,`bracket_num`)
				  REFERENCES `$bracket_table` (`event_ID`,`bracket_num`)
				  ON DELETE CASCADE
				  ON UPDATE NO ACTION,
				CONSTRAINT `fk_entrant_event`
				FOREIGN KEY  (`event_ID`)
				  REFERENCES `$event_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE NO ACTION
		 */
		$sql = "CREATE TABLE `$entrant_table` (
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
				`position` INT NOT NULL,
				`name` VARCHAR(100) NOT NULL,
				`seed` INT NULL,
				PRIMARY KEY  (`event_ID`,`bracket_num`,`position`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $entrant_table" : "Created table '$entrant_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$entrant_table");
		}
		
		/**
		 * A tennis match within a round within an event
		 * Holds pointers to the next round creating a linked list
		 * , 
				CONSTRAINT `fk_match_bracket`
				FOREIGN KEY (`event_ID`,`bracket_num`) 
				  REFERENCES `$bracket_table` (`event_ID`,`bracket_num`) 
				  ON DELETE CASCADE 
				  ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$match_table` (
				`event_ID` INT NOT NULL, 
				`bracket_num` INT NOT NULL DEFAULT 0, 
				`round_num` INT NOT NULL  DEFAULT 0, 
				`match_num` INT NOT NULL  DEFAULT 0, 
				`match_date` DATETIME NULL, 				
				`expected_end` DATETIME NULL, 
				`is_bye` TINYINT DEFAULT 0, 
				`next_round_num` INT DEFAULT 0, 
				`next_match_num` INT DEFAULT 0, 
				`comments` VARCHAR(255) NULL, 
				PRIMARY KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $match_table" : "Created table '$match_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$match_table");
		}
		
		/**
		 * Assigns entrants to a matches within a round within an event
		 * ,
			CONSTRAINT `fk_entrant_match`
			FOREIGN KEY (`match_event_ID`,`match_bracket_num`,`match_round_num`,`match_num`)
				REFERENCES `$match_table` (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				ON DELETE CASCADE
				ON UPDATE CASCADE,
			CONSTRAINT `fk_match_entrant`
			FOREIGN KEY (`match_event_ID`,`match_bracket_num`,`entrant_position`)
				REFERENCES `$entrant_table` (`event_ID`,`bracket_num`,`position`)
				ON DELETE CASCADE
				ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$match_entrant_table` (
			`match_event_ID` INT NOT NULL,
			`match_bracket_num` INT NOT NULL,
			`match_round_num` INT NOT NULL,
			`match_num` INT NOT NULL,
			`entrant_position` INT NOT NULL,
			`is_visitor` TINYINT DEFAULT 0,
			PRIMARY KEY(`match_event_ID`,`match_bracket_num`,`match_round_num`,`match_num`,`entrant_position`)) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $match_entrant_table" : "Created table '$match_entrant_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$match_entrant_table");
		}

		/**
		 * Sets scores are kept here.
		 * ,
				CONSTRAINT `fk_set_match`
				FOREIGN KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				  REFERENCES `$match_table` (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				  ON DELETE CASCADE
				  ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$set_table` (
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
				`round_num` INT NOT NULL,
				`match_num` INT NOT NULL,
				`set_num` INT NOT NULL,
				`home_wins` INT NOT NULL DEFAULT 0,
				`visitor_wins` INT NOT NULL DEFAULT 0,
				`home_tb_pts` INT NOT NULL DEFAULT 0,
				`visitor_tb_pts` INT NOT NULL DEFAULT 0,
				`home_ties` INT NOT NULL DEFAULT 0,
				`visitor_ties` INT NOT NULL DEFAULT 0,
				`early_end` TINYINT DEFAULT 0 COMMENT '0 means set completed normally, 1 means abnormal end home defaulted, 2 means abnormal end visitor defaulted, see comments for details',
				`comments` VARCHAR(512), 
				PRIMARY KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`,`set_num`)
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $set_table" : "Created table '$set_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$set_table");
		}

		/**
		 * A tennis team from a league for example
		 * ,
			CONSTRAINT `fk_team_club`
			FOREIGN KEY (`club_ID`)
				REFERENCES `$club_table` (`ID`)
				ON DELETE SET NULL
				ON UPDATE CASCADE,
			CONSTRAINT `fk_team_event`
			FOREIGN KEY (`event_ID`)
				REFERENCES `$event_table` (`ID`)
				ON DELETE CASCADE
				ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$team_table` (
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
				`team_num` INT NOT NULL,
				`name` VARCHAR(100) NOT NULL,
			PRIMARY KEY (`event_ID`,`bracket_num`,`team_num`)
			) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $team_table" : "Created table '$team_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$team_table");
		}

		/**
		 * Teams can be divided into squads
		 * CONSTRAINT `fk_squad_team`
		 *	  FOREIGN KEY (`event_ID`,`bracket_num`,`team_num`)
		 *		REFERENCES `$team_table` (`event_ID`,`bracket_num`,`team_num`)
		 *		ON DELETE CASCADE
		 *		ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE `$squad_table` (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
			  	`team_num` INT NOT NULL,
			  	`name` VARCHAR(25) NOT NULL,
			  Primary KEY (`ID`),
			  INDEX event_team_idx (`event_ID`,`bracket_num`,`team_num`)
			) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $squad_table" : "Created table '$squad_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$squad_table");
		}
		
		/**
		 * All the info about a tennis player
		 */
		$sql = "CREATE TABLE `$player_table` (
			  `ID`            INT NOT NULL AUTO_INCREMENT,
			  `event_ID`      INT NOT NULL,
			  `bracket_num`   INT NOT NULL,
			  `first_name`    VARCHAR(45) NULL,
			  `last_name`     VARCHAR(45) NOT NULL,
			  `gender`        VARCHAR(1) NOT NULL DEFAULT 'M',
			  `birthdate`     DATE NULL,
			  `skill_level`   DECIMAL(4,1) NULL DEFAULT 2.0,
			  `emailHome`     VARCHAR(100),
			  `emailBusiness` VARCHAR(100),
			  `phoneHome`     VARCHAR(45),
			  `phoneMobile`   VARCHAR(45),
			  `phoneBusiness` VARCHAR(45),
			  `is_spare`      TINYINT DEFAULT 0,
			  PRIMARY KEY (`ID`)) ENGINE=MyISAM,
			  INDEX event_bracket_idx (`event_ID`,`bracket_num`);";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $player_table" : "Created table '$player_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$player_table");
		}
		
		/**
		 * This table maps players to a team's squad
		 */
		$sql = "CREATE TABLE `$team_squad_player_table` ( 
				`squad_ID`  INT NOT NULL,
				`player_ID` INT NOT NULL,
				`is_captain` TINYINT DEFAULT 0,
    			PRIMARY KEY (`squad_ID`,`player_ID`)
				) ENGINE=MyISAM;";	
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $team_squad_player_table" : "Created table '$team_squad_player_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$team_squad_player_table");
		}

		/**
		 * The player_entrant table is an intersection
		 * between an an entrant in a draw and the player
		 * NOT USED; probably can be removed
		 * ,
				CONSTRAINT `fk_entrant_player`
				FOREIGN KEY (`player_ID`)
					REFERENCES `$player_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE CASCADE,
				CONSTRAINT `fk_player_entrant`
				FOREIGN KEY (`event_ID`,`bracket_num`,`position`)
					REFERENCES $entrant_table (`event_ID`,`bracket_num`,`position`)
					ON DELETE CASCADE
					ON UPDATE CASCADE
		 */
		// $sql = "CREATE TABLE `$player_entrant_table` (
		// 		`player_ID`  INT NOT NULL,
		// 		`event_ID`   INT NOT NULL,
		// 		`bracket_num`   INT NOT NULL,
		// 		`position`  INT NOT NULL 
		// 		) ENGINE=MyISAM;";
		// if( $newSchema ) {
		// 	$res = $wpdb->query( $sql );
		// 	$res = false === $res ? $wpdb->last_error . " when creating $player_entrant_table" : "Created table '$player_entrant_table'";
		// 	$this->log->error_log( $res );
		// }
		// else {			
		// 	$this->log->error_log( dbDelta( $sql ), "$player_entrant_table");
		// }

		/**
		 * The match_team_player table is the intersection
		 * between a tennis match, team, squad and a player
		 * It supports the scheduling of team's members for a match
		 * It could also be used to track performance of pairs of players within a team
		 * NOTE: that the entrant for a team tennis match is still the team and squad, not individual players
		 */
		$sql = "CREATE TABLE `$match_player_table` (
				`squad_ID`  int(11) NOT NULL,
				`player_ID` int(11) NOT NULL,
				`round_num` int(11) NOT NULL,
				`match_num` int(11) NOT NULL,
				PRIMARY KEY (`squad_ID`,`player_ID`,`round_num`,`match_num`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $match_player_table" : "Created table '$match_player_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$match_player_table");
		}

		/**
		 * This table is the intersection between
		 * a court booking and a tennis match
		 * ,
				CONSTRAINT `fk_match_booking`
				FOREIGN KEY (`booking_ID`)
					REFERENCES $booking_table (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION,
				CONSTRAINT `fk_booking_match`
				FOREIGN KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`)
					REFERENCES $match_table (`event_ID`,`bracket_num`,`round_num`,`match_num`)
					ON DELETE CASCADE
					ON UPDATE CASCADE
		 */
		$sql = "CREATE TABLE $booking_match_table (
				`booking_ID` INT NOT NULL,
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
				`round_num` INT NOT NULL,
				`match_num` INT NOT NULL
				) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $booking_match_table" : "Created table '$booking_match_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$booking_match_table");
		}

		return $wpdb->last_error;

	} //end add schema
	
	/**
	 * Drop the Tennis Events schema
	 */
	public function dropSchema(bool $withReports=false) {
		global $wpdb;
		if($withReports) $wpdb->show_errors(); 

		//NOTE: The order is important
		$sql = "DROP TABLE IF EXISTS ";
		$sql = $sql       . $this->dbTableNames["match_court_booking"];
		$sql = $sql . "," . $this->dbTableNames["court_booking"];
		$sql = $sql . "," . $this->dbTableNames["match_team_player"];
		$sql = $sql . "," . $this->dbTableNames["player_entrant"]; //NOT USED
		$sql = $sql . "," . $this->dbTableNames["match_entrant"];
		$sql = $sql . "," . $this->dbTableNames["squad_player"];
		$sql = $sql . "," . $this->dbTableNames["club_event"];
		$sql = $sql . "," . $this->dbTableNames["external_event"];
		$sql = $sql . "," . $this->dbTableNames["external_club"];
		$sql = $sql . "," . $this->dbTableNames["squad"];
		$sql = $sql . "," . $this->dbTableNames["team"];
		$sql = $sql . "," . $this->dbTableNames["player"];
		$sql = $sql . "," . $this->dbTableNames["set"];
		$sql = $sql . "," . $this->dbTableNames["match"];
		$sql = $sql . "," . $this->dbTableNames["entrant"];
		$sql = $sql . "," . $this->dbTableNames["bracket"];
		$sql = $sql . "," . $this->dbTableNames["event"];
		$sql = $sql . "," . $this->dbTableNames["court"];
		$sql = $sql . "," . $this->dbTableNames["club"];

		return $wpdb->query( $sql );
	}
	
	//shortcode: tennis_brackets clubId=number eventId=number
	public function do_shortcode( $atts, $content = null )
    {
		$loc = __CLASS__  . "::" . __FUNCTION__;

		$myshorts = shortcode_atts(array("clubId" => 1, "eventId" => 2 ), $atts, 'rts_prod_cat' );
		extract($myshorts);
		
		$club = Club::get( $clubId );
		$event = Event::get( $eventId );
		$clubName = $club->getName();
		$evtName = $event->getName();
		$out = '<table>' . PHP_EOL;
		if( !is_null( $club ) && !is_null( $event ) ) {
		
			$td = new TournamentDirector( $target );
			$entrants = $td->getSignup();
			$bracketSignupSize = count( $entrants );
			$matches = $td->getMatches();
			$umpire  = $td->getChairUmpire();
			$brackets = $target->getBrackets();

			foreach( $brackets as $bracket ) {
				
				error_log( sprintf( "%s:  Matches for '%s' at '%s'", $loc, $evtName, $clubName ) );
				error_log( sprintf( "%s:  %s Bracket: %d Rounds", $loc, $bracket->getName(), $td->totalRounds() ) );

				//NOTE: Does not support preliminary rounds!!!
				$numRounds = 0;
				foreach( $matches as $match ) {
					$round   = $match->getRoundNumber();
					$mn      = $match->getMatchNumber();
					if( 0 === $round ) {
						throw new TennisConfigurationException( __( "Cannot display brackets with preliminary rounds", TennisEvents::TEXT_DOMAIN ) );
					}
					

					$status  = $umpire->matchStatusEx( $match );
					$score   = $umpire->strGetScores( $match );
					$winner  = $umpire->matchWinner( $match );
					$winner  = is_null( $winner ) ? 'tba': $winner->getName();
					$home    = $match->getHomeEntrant();
					$hname   = !is_null( $home ) ? sprintf( "%d %s", $home->getPosition(), $home->getName() ) : 'tba';
					$hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';

					$visitor = $match->getVisitorEntrant();
					$vname   = 'tba';
					$vseed   = '';
					if( isset( $visitor ) ) {
						$vname   = sprintf( "%d %s", $visitor->getPosition(), $visitor->getName()  );
						$vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
					}

					$cmts    = $match->getComments();
					$cmts    = isset( $cmts ) ? $cmts : '';
					
					$rowspan = pow( 2, $round - 1 );
					$out += sprintf( "<td id=\"%d\" rowspan=\"%d\">", $mn, $rowspan );
				}
			}
		}

		$out .= '</table>';
		
        return $out; //always return something

	}

	public function enqueue_style() {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('tennis_css',$plugin_url . '../css/tennisevents.css');
	}

	public function enqueue_script() {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		$plugin_url = plugin_dir_url(__FILE__);
		wp_register_script( 'tennis_js', $plugin_url . 'js/te-support.js', array('jquery'),false,true );
		wp_register_script( 'tennis_js', $plugin_url . 'js/create-drawtable.js', array('jquery'),false,true );
	}

	//Need one extra query parameter
	public function add_query_vars_filter( $vars ) {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		$vars[] = "te_vars";
		return $vars;
	}

	public function add_todaysdate_in_menu( $items, $args ) {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		$this->log->error_log($items,"$loc: items");
		$this->log->error_log($args,"$loc: args");
		
		if( $args->theme_location == 'primary')  {
			$todaysdate = date('l jS F Y');
			$items .=  '<li>' . $todaysdate .  '</li>';
		}
		return $items;
	}
	
}