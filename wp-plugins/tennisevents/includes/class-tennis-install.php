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
		
        add_filter('query_vars', array($this,'add_query_vars_filter'));

        add_action('wp_enqueue_scripts', array( $this,'enqueue_script'));

        add_action('wp_enqueue_scripts', array( $this,'enqueue_style'));

        //add_action('wp_head', array( $this,'add_event_product_selection'));

        // Action hook to create the  shortcode
        add_shortcode('tennis_shorts', array( $this,'do_shortcode'));

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

		$this->create_options();
		$this->createSchema();
		$this->seedData();
		add_filter('wp_nav_menu_items',array($this,'add_todaysdate_in_menu'), 10, 2);
	}
	
	
	public function on_deactivate() {
	}

	public function on_uninstall() {
		$this->delete_options();
		$this->dropSchema();
	}
	
	protected function create_options() {
		add_option( self::OPTION_NAME_VERSION , TennisEvents::VERSION, false );
	}
	
	protected function delete_options() {
		delete_option(self::OPTION_NAME_VERSION );
	}

	public function createSchema() {
		global $wpdb;
		$wpdb->show_errors(); 

		$club_table = $wpdb->prefix . "tennis_club";
		$sql = "CREATE TABLE `$club_table` ( 
				`ID` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(100) NOT NULL,
				PRIMARY KEY (`ID`) );";
		var_dump( dbDelta( $sql) );
		$wpdb->print_error();
		
		$court_table = $wpdb->prefix . "tennis_court";
		$sql = "CREATE TABLE `$court_table` (
				`ID` INT NOT NULL COMMENT 'Same as Court Number',
				`club_ID` INT NOT NULL,
				`court_type` VARCHAR(45) NOT NULL DEFAULT 'hard',
				PRIMARY KEY (`club_ID`,`ID`),
				FOREIGN KEY (`club_ID`)
				  REFERENCES `$club_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$event_table = $wpdb->prefix . "tennis_event";
		$sql = "  CREATE TABLE `$event_table` (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(100) NOT NULL,
				`club_ID` INT NOT NULL,
				PRIMARY KEY (`ID`),
				FOREIGN KEY (`club_ID`)
				  REFERENCES `$club_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$draw_table = $wpdb->prefix . "tennis_draw";
		$sql = "CREATE TABLE `$draw_table` (
				`ID` INT NOT NULL AUTO_INCREMENT,
				`event_ID` INT NOT NULL,
				`name` VARCHAR(45) NOT NULL,
				`elimination` VARCHAR(45) NOT NULL DEFAULT 'single',
				PRIMARY KEY (`ID`),
				FOREIGN KEY (`event_ID`)
				  REFERENCES `$event_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$round_table = $wpdb->prefix . "tennis_round";
		$sql = "CREATE TABLE `$round_table` (
				`ID` INT NOT NULL COMMENT 'Same as Round Number',
				`owner_ID` INT NOT NULL,
				`owner_type` VARCHAR(45) NOT NULL,
				PRIMARY KEY (`owner_ID`, `ID`),
				FOREIGN KEY (`owner_ID`)
				  REFERENCES `$draw_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$entrant_table = $wpdb->prefix . "tennis_entrant";
		$sql = "CREATE TABLE `$entrant_table` (
				`ID` INT NOT NULL,
				`draw_ID` INT NOT NULL,
				`name` VARCHAR(45) NOT NULL,
				`position` INT NOT NULL,
				`seed` INT NULL,
				PRIMARY KEY (`draw_ID`,`ID`),
				FOREIGN KEY (`draw_ID`)
				  REFERENCES `$draw_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$match_table = $wpdb->prefix . "tennis_match";
		$sql = "CREATE TABLE $match_table (
				round_ID INT NOT NULL,
				ID INT NOT NULL COMMENT 'Same as Match number',
				home_ID INT NOT NULL,
				visitor_ID INT NOT NULL,
				PRIMARY KEY (round_ID,ID),
				FOREIGN KEY (round_ID)
				  REFERENCES $round_table (ID)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION,
				FOREIGN KEY (home_ID) 
				  REFERENCES $entrant_table (ID)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION,
				FOREIGN KEY (visitor_ID) 
				  REFERENCES $entrant_table (ID)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$team_table = $wpdb->prefix . "tennis_team";
		$sql = "CREATE TABLE `$team_table` (
			  `event_ID` INT NOT NULL,
			  `ID` INT NOT NULL,
			  `name` VARCHAR(45) NOT NULL,
			  PRIMARY KEY (`event_ID`,`ID`),
			  FOREIGN KEY (`event_ID`)
				REFERENCES `$event_table` (`ID`)
				ON DELETE NO ACTION
				ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$squad_table = $wpdb->prefix . "tennis_squad";
		$sql = "CREATE TABLE `$squad_table` (
			  `team_ID` INT NOT NULL,
			  `ID` INT NOT NULL,
			  `name` VARCHAR(25) NOT NULL,
			  PRIMARY KEY (`team_ID`,`ID`),
			  FOREIGN KEY (`team_ID`)
				REFERENCES `$team_table` (`ID`)
				ON DELETE NO ACTION
				ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$game_table = $wpdb->prefix . "tennis_game";
		$sql = "CREATE TABLE `$game_table` (
				`ID` INT NOT NULL COMMENT 'Same as game number',
				`match_ID` INT NOT NULL,
				`set_number` INT NOT NULL,
				`home_score` INT NOT NULL DEFAULT 0,
				`visitor_score` INT NOT NULL DEFAULT 0,
				PRIMARY KEY (`match_ID`,`ID`),
				FOREIGN KEY (`match_ID`)
				  REFERENCES `$match_table` (`ID`)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$player_table = $wpdb->prefix . "tennis_player";
		$sql = "CREATE TABLE `$player_table` (
			  `ID` INT NOT NULL AUTO_INCREMENT,
			  `first_name` VARCHAR(45) NULL,
			  `last_name` VARCHAR(45) NOT NULL,
			  `skill_level` DECIMAL(4,1) NULL DEFAULT 2.5,
			  `emailHome`  VARCHAR(100),
			  `emailBusiness` VARCHAR(100),
			  `phoneHome` VARCHAR(45),
			  `phoneMobile` VARCHAR(45),
			  `phoneBusiness` VARCHAR(45),
			  `squad_ID` INT,
			  `entrant_ID` INT,
			  `entrant_draw_ID` INT,
			  PRIMARY KEY (`ID`),
			  FOREIGN KEY (`squad_ID`)
				REFERENCES `$squad_table` (`ID`)
				ON DELETE NO ACTION
				ON UPDATE NO ACTION,
			  FOREIGN KEY (`entrant_draw_ID`, `entrant_ID`)
				REFERENCES `$entrant_table` (`draw_ID`, `ID`)
				ON DELETE NO ACTION
				ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
		
		$booking_table = $wpdb->prefix . "tennis_court_booking";
		$sql = "CREATE TABLE $booking_table (
				club_ID INT NOT NULL,
				court_ID INT NOT NULL,
				match_ID INT NOT NULL,
				book_date DATE NULL,
				book_time TIME(6) NULL,
				PRIMARY KEY (club_ID,court_ID,match_ID),
				FOREIGN KEY (club_ID,court_ID)
				  REFERENCES $court_table (club_ID,ID)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION,
				FOREIGN KEY (match_ID)
				  REFERENCES $match_table (ID)
				  ON DELETE NO ACTION
				  ON UPDATE NO ACTION);";
		var_dump( dbDelta( $sql) ); 
		$wpdb->print_error();
	}

	public function seedData() {
		global $wpdb;

		$values = array("name" => "Tyandaga Tennis Club");
		$formats_values = array('%s');
		$table = $wpdb->prefix . "tennis_club";
		$affected = $wpdb->insert($table,$values,$formats_values);
		return $affected;
	}
	
	public function dropSchema() {
		global $wpdb;
		$tablenames = "`%tennis_player`,`%tennis_court_booking`,`%tennis_game`,`%tennis_entrant`,`%tennis_squad`,`%tennis_team`,`%tennis_match`,`%tennis_round`,`%tennis_draw`,`%tennis_court`,`%tennis_event`,`%tennis_club`;";
		$sql = "DROP TABLE IF EXISTS " . str_replace("%", $wpdb->prefix, $tablenames);
		return $wpdb->query($sql);
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

	protected function enqueue_style() {
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('tennis_css',$plugin_url . '../css/tennisevents.css');
	}

	protected function enqueue_script() {
		// guess current plugin directory URL
		$plugin_url = plugin_dir_url(__FILE__);
		wp_register_script( 'tennis_js', $plugin_url . 'js/te-support.js', array('jquery'),false,true );
	}

	//Need one extra query parameter
	protected function add_query_vars_filter( $vars ) {
		$vars[] = "te_vars";
		return $vars;
	}

	protected function add_todaysdate_in_menu( $items, $args ) {
		if( $args->theme_location == 'primary')  {
			$todaysdate = date('l jS F Y');
			$items .=  '<li>' . $todaysdate .  '</li>';
		}
		return $items;
	}
	
}