<?php
use commonlib\BaseLogger;
use cpt\ClubMembershipCpt;
use cpt\TennisMemberCpt;

/**
 * Installation functions and actions.
 *
 * @author   Robin Smith
 * @category Admin
 * @package  Tennis Club Membership
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * TM_Install Class.
 */
class TM_Install {
	const PUBLICMEMBER_ROLENAME = "public_member";
	const TENNISPLAYER_ROLENAME = "tennis_member";
	const STAFF_ROLENAME = "staff_member";
	const INSTRUCTOR_ROLENAME = "instructor_coach";

	const SCORE_MATCHES_CAP = 'score_matches';
	const RESET_MATCHES_CAP = 'reset_matches';
	const MANAGE_REGISTRATIONS_CAP = 'manage_registrations';

	static public $registrationManagerCaps = array(self::SCORE_MATCHES_CAP => 1
                                                    ,self::RESET_MATCHES_CAP => 1
                                                    ,self::MANAGE_REGISTRATIONS_CAP => 1 );

	static public $tennisRoles=array(self::TENNISPLAYER_ROLENAME => "Tennis Member"
								    ,self::PUBLICMEMBER_ROLENAME => "Public Member"
								    ,self::STAFF_ROLENAME => "Staff Member"
								    ,self::INSTRUCTOR_ROLENAME => "Instructor or Coach");
	
	static private $tennisMemberCaps=array(self::SCORE_MATCHES_CAP => 1
											,self::RESET_MATCHES_CAP => 0
											,self::MANAGE_REGISTRATIONS_CAP => 0 );

	static private $publicMemberCaps=array(self::SCORE_MATCHES_CAP => 0
											,self::RESET_MATCHES_CAP => 0
											,self::MANAGE_REGISTRATIONS_CAP => 0 );
												
	static private $staffMemberCaps=array(self::SCORE_MATCHES_CAP => 1
											,self::RESET_MATCHES_CAP => 0
											,self::MANAGE_REGISTRATIONS_CAP => 0 );
											
	static private $instructorMemberCaps=array(self::SCORE_MATCHES_CAP => 1
											,self::RESET_MATCHES_CAP => 0
											,self::MANAGE_REGISTRATIONS_CAP => 0 );

	private $dbTableNames; 
	private $log;

	/**
	 * A reference to an instance of this class.
	 *
	 * @since 0.1.0
	 *
	 * @var   TM_Install singleton
	 */
	private static $instance;

    /**
    * TM_Install Singleton
    *
    * @return   TM_Install
    * @since    1.0.0
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
		$this->dbTableNames = array( "person"					=> $wpdb->prefix . "membership_person"
		                            ,"corporation"              => $wpdb->prefix . "membership_corporation"
									,"address"					=> $wpdb->prefix . "membership_address"
									,"registration"				=> $wpdb->prefix . "membership_memberregistration"
									,"membershiptype"			=> $wpdb->prefix . "membership_membershiptype"
									,"membershipcategory"		=> $wpdb->prefix . "membership_membershipcategory"
									,"externalmap"              => $wpdb->prefix . "membership_externalmap"
								);
		
        add_filter( 'query_vars', array( $this,'add_query_vars_filter' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_script' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_style' ) );
	}

	public function getDBTablenames() {
		return $this->dbTableNames;
	}

	/**
	 * Activate Tennis Membership.
	 */
	public function activate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc Start+++++++++++++++++++++++++++++++");

		//wp_delete_file()
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
		unregister_post_type( ClubMembershipCpt::CUSTOM_POST_TYPE );
		unregister_post_type( TennisMemberCpt::CUSTOM_POST_TYPE );
		// clear the permalinks to remove our post type's rules from the database
		flush_rewrite_rules();

		//remove roles?
		//$this->removeCap();
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}

	public function uninstall() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc Start+++++++++++++++++++++++++++++++");
		$this->removeCap();
		$this->removeRoles();
		$this->delete_options();
		$this->delete_customPostTypes();
		$this->delete_customTaxonomies();
		//$this->delete_user_meta();
		$this->delete_users();
		$this->dropSchema();
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}
	
	protected function create_options() {
		update_option( TennisClubMembership::OPTION_NAME_VERSION , TennisClubMembership::VERSION, false );
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
	 * Delete user meta data
	 */
	protected function delete_user_meta() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		// $users = get_users();
		// foreach ($users as $user) {
		// 	delete_user_meta($user->ID, TennisClubMembership::USER_PERSON_ID);
		// }
	}
	
	/**
	 * Delete WP users representing Members/Persons
	 */
	protected function delete_users() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$users = get_users();
		foreach ($users as $user) {
			if(!empty(get_user_meta($user->id, TennisClubMembership::USER_PERSON_ID, true))) {
				delete_user_meta($user->id, TennisClubMembership::USER_PERSON_ID);
				wp_delete_user($user->id);
			}
		}
	}

	/**
	 * Delete options for this plugin
	 */
	protected function delete_options() {
		delete_option( TennisClubMembership::OPTION_NAME_VERSION );
		delete_option( TennisClubMembership::OPTION_NAME_SEEDED );
		delete_option( TennisClubMembership::OPTION_HOME_CORPORATION );
	}

	/**
	 * Delete Custom Post Types for this plugin:
	 * TennisClubRegistrationCpt, TennisMemberCpt
	 */
	protected function delete_customPostTypes() {
		$regposts = get_posts( array( 'post_type' => ClubMembershipCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		foreach( $regposts as $cpt ) {
			wp_delete_post( $cpt->ID, true );
		}
		$memposts = get_posts( array( 'post_type' => TennisMemberCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		foreach( $memposts as $cpt ) {
			wp_delete_post( $cpt->ID, true );
		}
	}

	/**
	 * Delete custom taxonomies 
	 */
	protected function delete_customTaxonomies() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);
		$this->delete_customTerms(ClubMembershipCpt::CUSTOM_POST_TYPE_TAX);
		$this->delete_customTerms(TennisMemberCpt::CUSTOM_POST_TYPE_TAX);
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

		global $wpdb;
	}

	/**
	 * TODO: Modify roles for tennis membership plugin
	 * Add the roles of 'Tennis Player', 'Tournament Director', 'Chair Umpire', etc.
	 */
	private function addRoles() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$caps = array();
		foreach(self::$tennisRoles as $slug => $name ) {
			switch($slug) {
				case self::TENNISPLAYER_ROLENAME:			
					$caps = array_merge(self::$tennisMemberCaps, get_role('subscriber')->capabilities );
					break;
				case self::PUBLICMEMBER_ROLENAME:
					$caps = array_merge(self::$publicMemberCaps, get_role('subscriber')->capabilities );
					break;
				case self::INSTRUCTOR_ROLENAME;
					$caps = array_merge(self::$instructorMemberCaps, get_role('subscriber')->capabilities );
					break;
				case self::STAFF_ROLENAME;
					$caps = array_merge(self::$staffMemberCaps, get_role('subscriber')->capabilities );
					break;
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
	 * Remove the roles 
	 */
	protected function removeRoles() {
		foreach (self::$tennisRoles as $slug => $name) {
			remove_role( $slug );
		}
	}
	
    /**
	 * Add the new capabilities to all roles having the 'manage_options' capability
	 */
    private function addCap() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

        $roles = get_editable_roles();

        foreach ($GLOBALS['wp_roles']->role_objects as $key => $role) {
            if (isset($roles[$key]) && $role->has_cap('manage_options')) {
				foreach(self::$registrationManagerCaps as $cap => $grant) {
                	$role->add_cap( $cap );
				}
            }
        }
	}

	/**
	 * Remove the membership specific custom capabilities
	 */
	private function removeCap() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$roles = get_editable_roles();
		foreach ($GLOBALS['wp_roles']->role_objects as $key => $role) {
			if (isset($roles[$key]) && $role->has_cap('manage_options')) {
				foreach(self::$registrationManagerCaps as $cap => $grant) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
	
	/**
	 * Create the Tennis Membership schema
	 */
	public function createSchema( bool $withReports=false ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		global $wpdb;
		if($withReports) $wpdb->show_errors();

		//Check if schema already installed
		$numTables = $wpdb->query("SHOW TABLES LIKE '%_membership_%'");
		$this->log->error_log("$loc: Number of tables = $numTables");
		$newSchema = $numTables > 0 ? false : true;

		//Temporarily until can test/fix dbDelta usage
		if( ! $newSchema ) return;
				
		/**
		 * Corporaton
		 */
		$coporation_table	= $this->dbTableNames["corporation"];
		$sql = "CREATE TABLE `$coporation_table` ( 
			`ID`            INT NOT NULL AUTO_INCREMENT,
			`name`          VARCHAR(255) NOT NULL,
			`yearend_date`  DATETIME NOT NULL,
			`status`        VARCHAR(25) NULL COMMENT 'Open, Closed, Open for Renewal',
			`gst_number`    VARCHAR(50) NULL,
			`gst_rate1`     DECIMAL(5,3) NULL,
            `gst_rate2`     DECIMAL(5,3) NULL,			
			`last_update`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`)
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $coporation_table" : "$coporation_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$coporation_table");
		}

		/**
		 * Person is someone who interacts with the club
		 * Information is stored in this table and in the Wordpress users table.
		 */
		$person_table = $this->dbTableNames["person"];
		$sql = "CREATE TABLE `$person_table` ( 
			`ID`            INT NOT NULL AUTO_INCREMENT,
			`corporate_ID`   INT NOT NULL,
			`sponsor_ID`    INT NULL,
			`first_name`    VARCHAR(45) NULL,
			`last_name`     VARCHAR(45) NOT NULL,
			`gender`        VARCHAR(10) NOT NULL,
			`birthdate`     DATE NULL,
			`skill_level`   DECIMAL(4,1) DEFAULT 2.0,
			`emailHome`     VARCHAR(100),
			`emailBusiness` VARCHAR(100),
			`phoneHome`     VARCHAR(45),
			`phoneMobile`   VARCHAR(45),
			`phoneBusiness` VARCHAR(45),
			`notes`         VARCHAR(2000),
			`last_update`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`),
			INDEX corpperson (`corporate_ID`),
			INDEX sponsors (`sponsor_ID`),
			INDEX fornames (`last_name`,`first_name`)
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $person_table" : "$person_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$person_table");
		}

		/**
		 * Security Questions for a Person
		 */
		
		/**
		 * Address
		 */
		$address_table	= $this->dbTableNames["address"];
		$sql = "CREATE TABLE `$address_table` ( 
			`ID`            INT NOT NULL AUTO_INCREMENT,
			`owner_ID` 	    INT NOT NULL COMMENT 'References someone in the person or corporation table',
			`addr1` 		VARCHAR(255) NULL,
			`addr2` 		VARCHAR(255) NOT NULL,
			`city` 			VARCHAR(100) NOT NULL,
			`province` 		VARCHAR(100) DEFAULT 'Ontario',
			`country` 		VARCHAR(100) DEFAULT 'Canada',
			`postal_code` 	VARCHAR(20),
			`last_update`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`),
			INDEX owneraddress (`owner_ID`)
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $address_table" : "$address_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$address_table");
		}
		
		/**
		 * Membership Type are the types of memberships available
		 */
		$membership_type_table = $this->dbTableNames["membershiptype"];
		$sql = "CREATE TABLE `$membership_type_table` ( 
			`ID`           INT NOT NULL AUTO_INCREMENT,
			`category_ID`  INT NOT NULL COMMENT 'References super type table',
			`name`         VARCHAR(25) NOT NULL COMMENT 'Adult, Couples, Family, Junior, Student, Staff, Public, Instructor',
			`description`  VARCHAR(1024) DEFAULT '',
			`last_update`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`) 
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $membership_type_table" : "$membership_type_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$membership_type_table");
		}
				
		/**
		 * Membership Super Type
		 */
		$membership_supertype_table = $this->dbTableNames["membershipcategory"];
		$sql = "CREATE TABLE `$membership_supertype_table` ( 
			`ID`           INT NOT NULL AUTO_INCREMENT,
			`corporate_ID` INT NOT NULL,
			`name`         VARCHAR(10) NOT NULL COMMENT 'Player vs NonPlayer',
			`last_update`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`) 
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $membership_supertype_table" : "$membership_supertype_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$membership_supertype_table");
		}

		/**
		 * A Person who joins the club is Registered under a specific membership type
		 */
		$member_table = $this->dbTableNames["registration"];
		$sql = "CREATE TABLE `$member_table` ( 
			`ID`                    INT NOT NULL AUTO_INCREMENT,
			`person_ID` 			INT NOT NULL COMMENT 'References the person table',
			`season_ID` 			INT NOT NULL COMMENT 'References the season table',
			`membership_type_ID`	INT NOT NULL COMMENT 'References the membership type table',
            `status`                VARCHAR(25) NOT NULL,
			`start_date` 			DATE NOT NULL,
			`expiry_date` 			DATE NOT NULL,
			`receive_emails` 		TINYINT DEFAULT 0,
			`include_in_directory` 	TINYINT DEFAULT 0,
			`share_email` 			TINYINT DEFAULT 0,
			`notes` 				VARCHAR(255),
			`last_update`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`),
			INDEX person (`person_ID`),
			INDEX season (`season_ID`),
			INDEX regtype (`membership_type_ID`)
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $member_table" : "$member_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$member_table");
		}

		$external_ref_table = $this->dbTableNames["externalmap"];
		$sql = "CREATE TABLE `$external_ref_table` (
		    `subject`         VARCHAR(50) DEFAULT 'registration',
			`internal_ID`     INT NOT NULL,
			`external_ID`     VARCHAR(100) NOT NULL,
			`last_update`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX intaccess (`subject`,`internal_ID`),
			INDEX extaccess (`subject`,`external_ID`)
		  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";  
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $external_ref_table" : "$external_ref_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$external_ref_table");
		}
		
		return $wpdb->last_error;

	} //end add schema
	
	/**
	 * Drop the Tennis Membership schema
	 */
	public function dropSchema(bool $withReports=false) {
		global $wpdb;
		if($withReports) $wpdb->show_errors(); 

		//NOTE: The order is important
		$sql = "DROP TABLE IF EXISTS ";
		$sql = $sql . $this->dbTableNames["address"];
		$sql = $sql . "," . $this->dbTableNames["externalmap"];
		$sql = $sql . "," . $this->dbTableNames["registration"];
		$sql = $sql . "," . $this->dbTableNames["person"];
		$sql = $sql . "," . $this->dbTableNames["membershiptype"];
		$sql = $sql . "," . $this->dbTableNames["membershipcategory"];
		$sql = $sql . "," . $this->dbTableNames["corporation"];

		return $wpdb->query( $sql );
	}

	public function enqueue_style() {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('tennis_css', $plugin_url . '../css/tennismembership.css');
	}

	public function enqueue_script() {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		$plugin_url = plugin_dir_url(__FILE__);
		wp_register_script( 'tennis_js', $plugin_url . 'js/tm-support.js', array('jquery'),false,true );
	}

	//Need one extra query parameter
	public function add_query_vars_filter( $vars ) {
		$loc = __CLASS__  . "::" . __FUNCTION__;
		$vars[] = "tm_vars";
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