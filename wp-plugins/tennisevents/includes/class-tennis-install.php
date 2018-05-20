<?php


/**
 * Installation related functions and actions.
 *
 * @author   Robin Smith
 * @category Admin
 * @package  Tennis Events
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * TE_Install Class.
 */
class TE_Install {

	const OPTION_NAME_VERSION = 'tennis_version';

	private $dbTableNames; 

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
		$this->includes();

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
								   ,"player_team"			=> $wpdb->prefix . "tennis_player_team_squad"
								   ,"player_entrant"		=> $wpdb->prefix . "tennis_player_entrant"
								   ,"match_entrant"			=> $wpdb->prefix . "tennis_match_entrant"
								   ,"court_booking"			=> $wpdb->prefix . "tennis_court_booking"
								   ,"match_court_booking"	=> $wpdb->prefix . "tennis_match_court_booking"
								   ,"club_event"			=> $wpdb->prefix . "tennis_club_event"
								);
		
        add_filter('query_vars', array($this,'add_query_vars_filter'));

        add_action('wp_enqueue_scripts', array( $this,'enqueue_script'));

        add_action('wp_enqueue_scripts', array( $this,'enqueue_style'));

        //add_action('wp_head', array( $this,'add_event_product_selection'));

        // Action hook to create the  shortcode
        //add_shortcode('tennis_shorts', array( $this,'do_shortcode'));

	}


	protected function includes() {
		include_once('gw-support.php');
	}
	
	/**
	 * Activate Tennis Events.
	 */
	public function on_activate() {
		// Ensure needed classes are loaded
		//add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		error_log(__class__ . ": on_activate");
		$this->create_options();
		$this->createSchema();
		//$this->seedData();
		add_filter('wp_nav_menu_items',array($this,'add_todaysdate_in_menu'), 10, 2);
	}
	
	
	public function on_deactivate() {
		error_log(__class__ . ": on_deactivate");
	}

	public function uninstall() {
		error_log(__class__ . ": uninstall");
		$this->delete_options();
		$this->dropSchema();
	}
	
	protected function create_options() {
		add_option( self::OPTION_NAME_VERSION , TennisEvents::VERSION, false );
	}
	
	protected function delete_options() {
		delete_option(self::OPTION_NAME_VERSION );
	}

	public function createSchema(bool $withReports=false) {
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
		$team_squad_player_table 	= $this->dbTableNames["player_team"];
		$player_entrant_table 		= $this->dbTableNames["player_entrant"];
		$match_entrant_table 		= $this->dbTableNames["match_entrant"];
		$booking_table 				= $this->dbTableNames["court_booking"];
		$booking_match_table 		= $this->dbTableNames["match_court_booking"];
		$club_event_table			= $this->dbTableNames["club_event"];

		//Check if schema already installed
		if( $wpdb->get_var("SHOW TABLES LIKE '$club_table'") == $club_table ) {
			return;
		}

		/**
		 * Club or venue that owns tennis courts
		 */
		$sql = "CREATE TABLE `$club_table` ( 
				`ID` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`ID`) );";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Court ... where you play tennis!
		 */
		$sql = "CREATE TABLE `$court_table` (
				`club_ID` INT NOT NULL,
				`court_num` INT NOT NULL,
				`court_type` VARCHAR(45) NOT NULL DEFAULT 'hard',
				PRIMARY KEY (`club_ID`,`court_num`),
				FOREIGN KEY (`club_ID`)
				  REFERENCES `$club_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE NO ACTION);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Events are hierarchical entities
		 * representing leagues, tournaments, ladder, etc.
		 * For example an event called 'Year End Tournament' 
		 * having sub-events: 'Mens Singles', 'Mens Doubles', 'Womens Doubles', etc.
		 */
		$sql = "CREATE TABLE `$event_table` (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(256) NOT NULL,
				`parent_ID` INT NULL COMMENT 'Parent event',
				`event_type` VARCHAR(50) NULL COMMENT 'tournament, league, ladder, round robin',
				`match_type` DECIMAL(3,1) DEFAULT 0.0 COMMENT '1.1=mens singles, 1.2=ladies singles, 2.1=mens doubles, 2.2=ladies doubles, 2.3=mixed doubles', 
				`format` VARCHAR(25) NULL COMMENT 'single elimination, double elimination, games won, sets won',
				`signup_by` DATE NULL,
				`start_date` DATE NULL,
				`end_date` DATE NULL,
				PRIMARY KEY (`ID`),
				FOREIGN KEY (`parent_ID`)
				  REFERENCES `$event_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		/**
		 * This table enables a many-to-many relationship
		 * between clubs and events 
		 * paving the way for interclub leagues and tournaments
		 */
		$sql = "CREATE TABLE `$club_event_table` (
				`club_ID` INT NOT NULL,
				`event_ID` INT NOT NULL,
				PRIMARY KEY(`club_ID`,`event_ID`),
				FOREIGN KEY (`club_ID`)
					REFERENCES `$club_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION,
				FOREIGN KEY (`event_ID`)
					REFERENCES `$event_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		/**
		 * An entrant into a tournament event.
		 * The relationship between the event and all entrants is called a draw.
		 * This can be a single player or a doubles pair.
		 */
		$sql = "CREATE TABLE `$entrant_table` (
				`event_ID` INT NOT NULL,
				`position` INT NOT NULL,
				`name` VARCHAR(100) NOT NULL,
				`seed` INT NULL,
				PRIMARY KEY (`event_ID`,`position`),
				FOREIGN KEY (`event_ID`)
				  REFERENCES `$event_table` (`ID`)
				  ON DELETE CASCADE
				  ON UPDATE NO ACTION);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		$sql = "CREATE TABLE `$bracket_table` (
			`event_ID` INT NOT NULL,
			`bracket_num` INT NOT NULL,
			`is_approved` TINYINT NOT NULL DEFAULT 0,
			`name` VARCHAR(256) NOT NULL,
			PRIMARY KEY (`event_ID`, `bracket_num`),
			FOREIGN KEY (`event_ID`)
			   REFERENCES `$event_table` (`ID`)
			  ON DELETE CASCADE
			  ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		/**
		 * A tennis match within a round within an event
		 * Holds pointers to the next round creating a linked list
		 */
		$sql = "CREATE TABLE `$match_table` (
				`event_ID` INT NOT NULL, 
				`bracket_num` INT NOT NULL DEFAULT 0, 
				`round_num` INT NOT NULL  DEFAULT 0, 
				`match_num` INT NOT NULL  DEFAULT 0, 
				`match_type` DECIMAL(3,1) NOT NULL COMMENT '1.1=mens singles, 1.2=ladies singles, 2.1=mens doubles, 2.2=ladies doubles, 2.3=mixed doubles', 
				`match_date` DATE NULL, 
				`match_time` TIME(6) NULL, 
				`is_bye` TINYINT DEFAULT 0, 
				`next_round_num` INT DEFAULT 0, 
				`next_match_num` INT DEFAULT 0, 
				`comments` VARCHAR(255) NULL, 
				PRIMARY KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`), 
				FOREIGN KEY (`event_ID`, `bracket_num`) 
				  REFERENCES `$bracket_table` (`event_ID`, `bracket_num`) 
				  ON DELETE CASCADE 
				  ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Assigns entrants to a matches within a round within an event
		 */
		$sql = "CREATE TABLE `$match_entrant_table` (
			`match_event_ID` INT NOT NULL,
			`match_bracket_num` INT NOT NULL,
			`match_round_num` INT NOT NULL,
			`match_num` INT NOT NULL,
			`entrant_position` INT NOT NULL,
			`is_visitor` TINYINT DEFAULT 0,
			PRIMARY KEY(`match_event_ID`,`match_bracket_num`,`match_round_num`,`match_num`,`entrant_position`),
			FOREIGN KEY (`match_event_ID`,`match_bracket_num`,`match_round_num`,`match_num`)
				REFERENCES `$match_table` (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				ON DELETE CASCADE
				ON UPDATE CASCADE,
			FOREIGN KEY (`match_event_ID`,`entrant_position`)
				REFERENCES `$entrant_table` (`event_ID`,`position`)
				ON DELETE CASCADE
				ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Sets scores are kept here.
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
				`home_ties` INT NOT NULL DEFAULT 0 COMMENT 'For leagues, round robins',
				`visitor_ties` INT NOT NULL DEFAULT 0 COMMENT 'For leagues, round robins',
				`early_end` TINYINT DEFAULT 0 COMMENT '0 means set completed normally, 1 means abnormal end home defaulted, 2 means abnormal end visitor defaulted, see comments for details',
				`comments` VARCHAR(512), 
				PRIMARY KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`,`set_num`),
				FOREIGN KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				  REFERENCES `$match_table` (`event_ID`,`bracket_num`,`round_num`,`match_num`)
				  ON DELETE CASCADE
				  ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * A tennis team from a league for example
		 */
		$sql = "CREATE TABLE `$team_table` (
				`event_ID` INT NOT NULL,
				`team_num` INT NOT NULL,
				`club_ID` INT NULL,
				`name` VARCHAR(100) NOT NULL,
			PRIMARY KEY (`event_ID`,`team_num`),
			FOREIGN KEY (`club_ID`)
				REFERENCES `$club_table` (`ID`)
				ON DELETE SET NULL
				ON UPDATE CASCADE,
			FOREIGN KEY (`event_ID`)
				REFERENCES `$event_table` (`ID`)
				ON DELETE CASCADE
				ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Teams can be divided into squads
		 * This supports things like Team 1 with 'a' anb 'b' divisions for example.
		 * OR Team "Adrie and Robin", squad MD (for mens doubles)
		 */
		$sql = "CREATE TABLE `$squad_table` (
				`event_ID` INT NOT NULL,
			  	`team_num` INT NOT NULL,
			  	`division` VARCHAR(25) NOT NULL,
			  PRIMARY KEY (`event_ID`,`team_num`,`division`),
			  FOREIGN KEY (`event_ID`,`team_num`)
				REFERENCES `$team_table` (`event_ID`,`team_num`)
				ON DELETE CASCADE
				ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * All the info about a tennis player
		 */
		$sql = "CREATE TABLE `$player_table` (
			  `ID` INT NOT NULL AUTO_INCREMENT,
			  `first_name` VARCHAR(45) NULL,
			  `last_name` VARCHAR(45) NOT NULL,
			  `gender`   VARCHAR(1) NOT NULL DEFAULT 'M',
			  `skill_level` DECIMAL(4,1) NULL DEFAULT 2.5,
			  `emailHome`  VARCHAR(100),
			  `emailBusiness` VARCHAR(100),
			  `phoneHome` VARCHAR(45),
			  `phoneMobile` VARCHAR(45),
			  `phoneBusiness` VARCHAR(45),
			  PRIMARY KEY (`ID`));";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * This table maps players to a team's squads
		 */
		$sql =  "CREATE TABLE `$team_squad_player_table` ( 
				`player_ID` INT NOT NULL,
				`event_ID` INT NOT NULL,
				`team_num` INT NOT NULL,
				`division` VARCHAR(2) NOT NULL,
				FOREIGN KEY (`player_ID`)
					REFERENCES $player_table (`ID`)
					ON DELETE CASCADE
					ON UPDATE CASCADE,
				FOREIGN KEY (`event_ID`,`team_num`,`division`)
					REFERENCES $squad_table (`event_ID`,`team_num`,`division`)
					ON DELETE CASCADE
					ON UPDATE CASCADE);";	
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		/**
		 * The player_entrant table is an intersection
		 * between an a entrant in a draw and the player
		 */
		$sql ="CREATE TABLE `$player_entrant_table` (
				`player_ID` INT NOT NULL,
				`event_ID`   INT NOT NULL,
				`position`  INT NOT NULL,
				FOREIGN KEY (`player_ID`)
					REFERENCES `$player_table` (`ID`)
					ON DELETE CASCADE
					ON UPDATE CASCADE,
				FOREIGN KEY (`event_ID`,`position`)
					REFERENCES $entrant_table (`event_ID`,`position`)
					ON DELETE CASCADE
					ON UPDATE CASCADE );";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}
		
		/**
		 * Court bookings are recorded in this table.
		 */
		$sql = "CREATE TABLE $booking_table (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`club_ID` INT NOT NULL,
				`court_num` INT NOT NULL,
				`book_date` DATE NULL,
				`book_time` TIME(6) NULL,
				PRIMARY KEY (`ID`),
				FOREIGN KEY (`club_ID`,`court_num`)
				  REFERENCES $court_table (`club_ID`,`court_num`)
				  ON DELETE CASCADE
				  ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		/**
		 * This table is the intersection between
		 * a court booking and a tennis match
		 */
		$sql = "CREATE TABLE $booking_match_table (
				`booking_ID` INT NOT NULL,
				`event_ID` INT NOT NULL,
				`bracket_num` INT NOT NULL,
				`round_num` INT NOT NULL,
				`match_num` INT NOT NULL,
				FOREIGN KEY (`booking_ID`)
					REFERENCES $booking_table (`ID`)
					ON DELETE CASCADE
					ON UPDATE NO ACTION,
				FOREIGN KEY (`event_ID`,`bracket_num`,`round_num`,`match_num`)
					REFERENCES $match_table (`event_ID`,`bracket_num`,`round_num`,`match_num`)
					ON DELETE CASCADE
					ON UPDATE CASCADE);";
		dbDelta( $sql );
		if($withReports) {
			var_dump( dbDelta( $sql ) );
			$wpdb->print_error();
		}

		return $wpdb->last_error;

	} //end add schema

	public function seedData() {
		global $wpdb;

		$values = array("name" => "Tyandaga Tennis Club");
		$formats_values = array('%s');
		$table = $wpdb->prefix . "tennis_club";
		$affected = $wpdb->insert($table,$values,$formats_values);
		return $affected;
	}
	
	public function dropSchema(bool $withReports=false) {
		global $wpdb;
		if($withReports) $wpdb->show_errors(); 

		//NOTE: The order is important
		$sql = "DROP TABLE IF EXISTS ";
		$sql = $sql       . $this->dbTableNames["match_court_booking"];
		$sql = $sql . "," . $this->dbTableNames["court_booking"];
		$sql = $sql . "," . $this->dbTableNames["player_entrant"];
		$sql = $sql . "," . $this->dbTableNames["match_entrant"];
		$sql = $sql . "," . $this->dbTableNames["player_team"];
		$sql = $sql . "," . $this->dbTableNames["club_event"];
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

	/**
	 * Check version and run the updater if required.
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public function check_version() {
		if ( get_option( self::OPTION_NAME_VERSION ) !== TennisEvents::VERSION ) {
			//TODO: Do Something???
		}
	}
	
	//shortcode: rts_prod_cat orderby="name" order="asc"
	public function do_shortcode( $atts, $content = null )
    {
		$myshorts = shortcode_atts(array("hierarchy" => 0, "orderby" => "name", "order"=>"ASC", "parent"=>0), $atts,'rts_prod_cat');
		extract($myshorts);
		$out = '';

		//Want woocommerce product categories
		$categories = get_terms(array('taxonomy' => 'product_cat',
				'orderby' => $orderby,
    			'order'   => $order,
				'parent'  => $parent

		));

		if ( empty( $categories ) || is_wp_error( $categories ) ){
		    return $out;
		}
        $out = '<ul class="sbc-container gallery product-category">';

		foreach( $categories as $category ) {
			// get the thumbnail id using the queried category term_id
			$thumbnail_id = get_woocommerce_term_meta( $category->term_id, 'thumbnail_id', true );

			// get the image URL
			$cat_image_url = wp_get_attachment_url( $thumbnail_id );

			$cat_image_url = (isset($cat_image_url) && $cat_image_url != '') ? $cat_image_url : plugin_dir_path(__FILE__) . '/../img/placeholder.png';

			$image_link = sprintf('<a href="%1$s" alt="%2$s">%3$s</a>'
										,esc_url(get_category_link( $category->term_id ) )
										,esc_attr(sprintf(__('View all products in %s', 'xlc' ), $category->name))
										,sprintf('<img src="%s" />', $cat_image_url));

			$name_link = sprintf('<a href="%1$s" alt="%2$s">%3$s</a>'
										,esc_url( get_category_link( $category->term_id))
										,esc_attr(sprintf(__('View all products in %s', 'xlc' ), $category->name))
										,sprintf('<h3>%s</h3>', $category->name));

			$inner  = '<li class="product sbc-item">';
			$inner .= $image_link;
			$inner .= $name_link;
			$inner .= '</li>';
            $out .= $inner;
		}

		$out .= '</ul>';
        //$out .= '</div>';

        return $out;
	}

	public function enqueue_style() {
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('tennis_css',$plugin_url . '../css/tennisevents.css');
	}

	public function enqueue_script() {
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_register_script( 'tennis_js', $plugin_url . 'js/te-support.js', array('jquery'),false,true );
	}

	//Need one extra query parameter
	public function add_query_vars_filter( $vars ) {
		$vars[] = "te_vars";
		return $vars;
	}

	public function add_todaysdate_in_menu( $items, $args ) {
		if( $args->theme_location == 'primary')  {
			$todaysdate = date('l jS F Y');
			$items .=  '<li>' . $todaysdate .  '</li>';
		}
		return $items;
	}
	
}