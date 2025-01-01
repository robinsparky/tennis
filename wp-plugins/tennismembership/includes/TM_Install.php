<?php
use commonlib\BaseLogger;
//use \TennisMembership;

/**
 * Installation related functions and actions.
 *
 * @author   Robin Smith
 * @category Admin
 * @package  Tennis Membership
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
		$this->dbTableNames = array("person"					=> $wpdb->prefix . "membership_person"
									,"address"					=> $wpdb->prefix . "membership_address"
									,"registration"				=> $wpdb->prefix . "membership_registration"
									,"registrationtype"			=> $wpdb->prefix . "membership_type"
									,"registrationsupertype" 	=> $wpdb->prefix . "membership_supertype"
									,"sponsors" 				=> $wpdb->prefix . "membership_sponsor"
								);
		
        add_filter( 'query_vars', array( $this,'add_query_vars_filter' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_script' ) );

        add_action( 'wp_enqueue_scripts', array( $this,'enqueue_style' ) );

        // Action hook to create the shortcode
        //add_shortcode('tennis_shorts', array( $this,'do_shortcode'));

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
		// unregister_post_type( TennisEventCpt::CUSTOM_POST_TYPE );
		// unregister_post_type( TennisClubCpt::CUSTOM_POST_TYPE );
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
		$this->dropSchema();
		$this->log->error_log("+++++++++++++++++++++++++++++++++++$loc End+++++++++++++++++++++++++++++++");
	}
	
	protected function create_options() {
		update_option( TennisMembership::OPTION_NAME_VERSION , TennisMembership::VERSION, false );
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
	// 	$users = get_users();
	// 	foreach ($users as $user) {
	// 		delete_user_meta($user->ID, 'myplugin_user_meta');
	// 	}
	}

	
	/**
	 * Delete options for this plugin
	 */
	protected function delete_options() {
		delete_option( TennisMembership::OPTION_NAME_VERSION );
		//delete_option( TennisMembership::OPTION_NAME_SEEDED );
	}

	/**
	 * Delete Custom Post Types for this plugin:
	 * TennisEventCpt, TennisClubCpt
	 */
	protected function delete_customPostTypes() {
		// $eventposts = get_posts( array( 'post_type' => TennisEventCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		// foreach( $eventposts as $cpt ) {
		// 	wp_delete_post( $cpt->ID, true );
		// }
		// $clubposts = get_posts( array( 'post_type' => TennisClubCpt::CUSTOM_POST_TYPE, 'numberposts' => -1));
		// foreach( $clubposts as $cpt ) {
		// 	wp_delete_post( $cpt->ID, true );
		// }
	}

	/**
	 * Delete custom taxonomies 
	 */
	protected function delete_customTaxonomies() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);
	}
	
	/**
	 * Delete custom taxonomies using sql
	 * @param string $taxonomy is the slug of the custom taxonomy
	 */
	protected function delete_customTerms($taxonomy) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		global $wpdb;
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
	 * Create the Tennis Membership schema
	 * TODO: test using dbDelta
	 */
	public function createSchema( bool $withReports=false ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		global $wpdb;
		if($withReports) $wpdb->show_errors();

		//Check if schema already installed
		$person_table = $this->dbTableNames["person"];
		$newSchema = true;
		if( $wpdb->get_var("SHOW TABLES LIKE '$person_table'") == $person_table ) {
			$newSchema = false;
		}

		//Temporarily until can test/fix dbDelta usage
		if( ! $newSchema ) return;
		
		/**
		 * Person is someone who interacts with the club
		 * Information is stored in this table and in the Wordpress users table.
		 */
		$sql = "CREATE TABLE `$person_table` ( 
			`ID` INT NOT NULL AUTO_INCREMENT,
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
			`notes` VARCHAR(255),
			PRIMARY KEY (`ID`) 
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
			`ID` INT NOT NULL AUTO_INCREMENT,
			`person_ID` 	INT NOT NULL COMMENT 'References someone in the person table',
			`street1` 		VARCHAR(255) NOT NULL,
			`street2` 		VARCHAR(255) NOT NULL,
			`city` 			VARCHAR(100) NOT NULL,
			`province` 		VARCHAR(100) NOT NULL,
			`country` 		VARCHAR(100) NOT NULL,
			`postal_code` 	VARCHAR(10) NOT NULL,
			PRIMARY KEY (`ID`) 
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
		$membership_type_table = $this->dbTableNames["registrationtype"];
		$sql = "CREATE TABLE `$membership_type_table` ( 
			`ID` INT NOT NULL AUTO_INCREMENT,
			`supertype_ID` INT NOT NULL COMMENT 'References super type table',
			`name` VARCHAR(255) NOT NULL COMMENT 'Adult, Couples, Family, Junior, Student vs Staff, Public, Instructor',
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
		 * Membership Type are the super types of memberships available
		 */
		$membership_supertype_table = $this->dbTableNames["registrationsupertype"];
		$sql = "CREATE TABLE `$membership_supertype_table` ( 
			`ID` INT NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(255) NOT NULL COMMENT 'Player vs NonPlayer',
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
		 * A Person who joins the club is Registered as a certain registration type
		 * Need to consider player versus non-player, sponsor vs sponsored
		 */
		$member_table = $this->dbTableNames["registration"];
		$sql = "CREATE TABLE `$member_table` ( 
			`ID` INT NOT NULL AUTO_INCREMENT,
			`person_ID` 			INT NOT NULL COMMENT 'References someone in the person table',
			`season_ID` 			INT NOT NULL COMMENT 'References the season table',
			`registration_type_ID` 	INT NOT NULL COMMENT 'References the registration type table',
			`start_date` 			DATE NOT NULL,
			`expiry_date` 			DATE NOT NULL,
			`receive_emails` 		TINYINT DEFAULT 0,
			`include_in_directory` 	TINYINT DEFAULT 0,
			`share_email` 			TINYINT DEFAULT 0,
			`notes` 				VARCHAR(255),
			PRIMARY KEY (`ID`) 
		) ENGINE = MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $member_table" : "$member_table Created";
			$this->log->error_log( $res );
		}
		else {
			$this->log->error_log( dbDelta( $sql ), "$member_table");
		}
		
		/**
		 * Relates sponsors with the people they are sponsoring. 
		 * Usually family members
		 */
		$sponsor_table = $this->dbTableNames['sponsors'];
		$sql = "CREATE TABLE `$sponsor_table` (
			`sponsor_ID` INT NOT NULL COMMENT 'References a person',
			`sponsored_ID` INT NOT NULL COMMENT 'References a person',
			PRIMARY KEY(`sponsor_ID`, `sponsored_ID`)) ENGINE=MyISAM;";
		if( $newSchema ) {
			$res = $wpdb->query( $sql );
			$res = false === $res ? $wpdb->last_error . " when creating $sponsor_table" : "Created table '$sponsor_table'";
			$this->log->error_log( $res );
		}
		else {			
			$this->log->error_log( dbDelta( $sql ), "$sponsor_table");
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
		$sql = $sql . "," . $this->dbTableNames["registration"];
		$sql = $sql . "," . $this->dbTableNames["registrationsupertype"];
		$sql = $sql . "," . $this->dbTableNames["registrationtype"];
		$sql = $sql . "," . $this->dbTableNames["sponsors"];
		$sql = $sql . "," . $this->dbTableNames["person"];

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