<?php
namespace cpt;

use \DateTime;
use \DateTimeInterface;
use \WP_Error;
use api\BaseLoggerEx;
use \TennisEvents;
use \EventType;
use \MatchType;
use \ScoreType;
use \Format;
use \Event;
use \Club;
use \InvalidEventException;

/** 
 * Data and functions for the Tennis Club Custom Post Type
 * @class  TennisClubCpt
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisClubCpt {
	
	const CUSTOM_POST_TYPE     = 'tennisclubcpt';
	const CUSTOM_POST_TYPE_TAX = 'tennisclubcategory';
	const CUSTOM_POST_TYPE_TAG = 'tennisclubtag';

    const CLUB_NAME                = '_tennisclub_name';
	
    //Only emit on this page
	private $hooks = array('post.php', 'post-new.php');

	private $log;

	/**
	 * Register actions, filters and scripts for this post type
	 */
	public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log( $loc );
		
		$tennisClub = new TennisClubCpt();

		$tennisClub->customPostType(); 
		//$tennisClub->customTaxonomy();
		add_action( 'admin_enqueue_scripts', array( $tennisClub, 'enqueue') );
			
		add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $tennisClub, 'addColumns' ), 10 );
		add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $tennisClub, 'getColumnValues'), 10, 2 );
		add_filter( 'manage_edit-' .self::CUSTOM_POST_TYPE . '_sortable_columns', array( $tennisClub, 'sortableColumns') );
		add_action( 'pre_get_posts', array( $tennisClub, 'orderby' ) );
		
		//Required actions for meta boxes
		add_action( 'add_meta_boxes', array( $tennisClub, 'metaBoxes' ) );

		// Hook for updating/inserting into Tennis tables
		add_action( 'save_post', array( $tennisClub, 'updateTennisDB'), 12 );
		//Hook for deleting cpt
		add_action( 'delete_post', array( $tennisClub, 'deleteTennisDB') );
	}
	
	public function __construct() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log = new BaseLoggerEx( true );
	}

	public function enqueue( $hook ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( "$loc --> $hook" ); 

        //Make sure we are rendering the "user-edit" page
        if( in_array( $hook, $this->hooks ) ) {
            
            // wp_enqueue_media();//Enqueue WP media js

            // wp_register_script( 'care-media-uploader'
            //                 , get_stylesheet_directory_uri() . '/js/care-course-media-uploader.js'
            //                 , array('jquery') );
    
            // wp_localize_script( 'care-media-uploader', self::JS_OBJECT, $this->get_data() );

            // wp_enqueue_script( 'care-media-uploader' );
		}
	}

	public function customPostType() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		$labels = array( 'name' => 'Tennis Clubs'
					   , 'singular_name' => 'Tennis Club'
					   , 'add_new' => 'Add Tennis Club'
					   , 'add_new_item' => 'New Tennis Club'
					   , 'new_item' => 'New Tennis Club'
					   , 'edit_item' => 'Edit Tennis Club'
					   , 'view_item' => 'View Tennis Club'
					   , 'all_items' => 'All Tennis Clubs'
					   , 'menu_name' => 'Tennis Clubs'
					   , 'search_items'=>'Search Clubs'
					   , 'not_found' => 'No Tennis Clubs found'
                       , 'not_found_in_trash'=> 'No Tennis Clubs found in Trash');
                       
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'description' => 'Tennis Club as a CPT'
					 , 'menu_position' => 93
					 , 'menu_icon' => 'dashicons-code-standards'
					 , 'exclude_from_search' => false
					 , 'has_archive' => true
					 , 'publicly_queryable' => true
					 , 'query_var' => true
					 , 'capability_type' => 'post'
					 , 'hierarchical' => true
					 , 'show_in_rest' => true //causes Gutenberg editor to be used
					 , 'rewrite' => array( 'slug' => 'tennisclub', 'with_front' => false )
					 //, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ) 
					 , 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ) 
					 , 'public' => true );
		register_post_type( self::CUSTOM_POST_TYPE, $args );
	}

	/**
     *  Add Club columns
     */
	public function addColumns( $columns ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $columns, $loc );

		// column vs displayed title
        //$newColumns['club_name'] = __( 'Club Name', TennisEvents::TEXT_DOMAIN );
		$newColumns['cb'] = $columns['cb'];
		$newColumns['title'] = __( 'Club Name', TennisEvents::TEXT_DOMAIN ); //$columns['title'];
		//$newColumns['taxonomy-tennisclubcategory'] = __('Category', TennisEvents::TEXT_DOMAIN );
		$newColumns['author'] = $columns['author'];
        $newColumns['date'] = $columns['date'];
		return $newColumns;
	}

	public function sortableColumns ( $columns ) {
		//$columns['start_date'] = 'startDate';
		//$columns['taxonomy-tenniseventcategory'] = 'categorySort';
		return $columns;
	}

	public function orderby ( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		if( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
	}

	/**
     * Populate the Tennis Club columns with values
     */
	public function getColumnValues( $column_name, $postID ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( "$loc --> $column_name, $postID" );

		// if( $column_name === 'event_type') {
		// 	$eventType = get_post_meta( $postID, self::EVENT_TYPE_META_KEY, TRUE );
		// 	if( !empty($eventType) ) {
		// 		echo EventType::AllTypes()[$eventType];
		// 	}
		// 	else {
		// 		echo "";
		// 	}
		// }
		// elseif( $column_name === 'parent_event' ) {
		// 	$eventParentId = get_post_meta( $postID, self::PARENT_EVENT_META_KEY, TRUE );
		// 	if( !empty($eventParentId) ) {
		// 		$tecpt = get_post( $eventParentId );
		// 		$name = "";
		// 		if( !is_null( $tecpt ) ) {
		// 			$name = $tecpt->post_title;
		// 		}
		// 		echo "$name($eventParentId)";
		// 	}
		// 	else {
		// 		echo "";
		// 	}
		// }
		// elseif( $column_name === 'event_format' ) {
		// 	$eventFormat = get_post_meta( $postID, self::EVENT_FORMAT_META_KEY, TRUE );
		// 	if( !empty($eventFormat) ) {
		// 		echo Format::AllFormats()[$eventFormat];
		// 	}
		// 	else {
		// 		echo "";
		// 	}
		// }
		// elseif( $column_name === 'match_type') {
		// 	$matchType = get_post_meta( $postID, self::MATCH_TYPE_META_KEY, TRUE );
		// 	if( !empty($matchType) ) {
		// 		echo MatchType::AllTypes()[$matchType];
		// 	}
		// 	else {
		// 		echo "";
		// 	}
		// }
		// elseif( $column_name === 'score_type') {
		// 	$scoreType = get_post_meta( $postID, self::SCORE_TYPE_META_KEY, TRUE );
		// 	if( !empty($scoreType) ) {
		// 		if( ScoreType::get_instance()->isValid( $scoreType ) ) {
		// 			echo $scoreType;
		// 		}
		// 		else {
		// 			echo "";
		// 		}
		// 	}
		// 	else {
		// 		echo "";
		// 	}
		// }
		// elseif( $column_name === 'signup_by_date') {
		// 	$signupBy = get_post_meta( $postID, self::SIGNUP_BY_DATE_META_KEY, TRUE );
		// 	if( !empty($signupBy) ) {
		// 		echo $signupBy;
		// 	}
		// 	else {
		// 		echo __( 'TBA', TennisEvents::TEXT_DOMAIN );
		// 	}
		// }
		// elseif( $column_name === 'start_date' ) {
		// 	$start = get_post_meta( $postID, self::START_DATE_META_KEY, TRUE );
		// 	if( !empty($start) ) {
		// 		echo $start;
		// 	}
		// 	else {
		// 		echo __('TBA', TennisEvents::TEXT_DOMAIN );
		// 	}
		// }
		// elseif( $column_name === 'end_date' ) {
		// 	$end = get_post_meta( $postID, self::END_DATE_META_KEY, TRUE );
		// 	if( !empty($end) ) {
		// 		echo $end;
		// 	}
		// 	else {
		// 		echo __('TBA', TennisEvents::TEXT_DOMAIN );
		// 	}
		// }
	}

	public function customTaxonomy() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );
		
		//hierarchical
		$labels = array( 'name' => 'Tennis Club Categories'
						, 'singular_name' => 'Tennis Club Category'
						, 'search_items' => 'Tennis Club Search Category'
						, 'all_items' => 'All Tennis Club Categories'
						, 'parent_item' => 'Parent Tennis Club Category'
						, 'parent_item_colon' => 'Parent Tennis Club Category:'
						, 'edit_item' => 'Edit Tennis Club Category'
						, 'update_item' => 'Update Tennis Club Category'
						, 'add_new_item' => 'Add New Tennis Club Category'
						, 'new_item_name' => 'New Tennis Club Category'
						, 'menu_name' => 'Tennis Club Categories'
						);

		$args = array( 'hierarchical' => true
					 , 'labels' => $labels
					 , 'show_ui' => true
					 , 'show_admin_column' => true
					 , 'query_var' => true
					 , 'rewrite' => array( 'slug' => self::CUSTOM_POST_TYPE_TAX )
					);

		register_taxonomy( self::CUSTOM_POST_TYPE_TAX
						 , array( self::CUSTOM_POST_TYPE )
						 , $args );

		//NOT hierarchical
		register_taxonomy( self::CUSTOM_POST_TYPE_TAG
						 , self::CUSTOM_POST_TYPE
						 , array( 'label' => 'Tennis Club Tags'
								, 'rewrite' => array( 'slug' => self::CUSTOM_POST_TYPE_TAG )
								, 'hierarchical' => false
						));
	}
		
	/* 
	================================================
		Meta Boxes
	================================================
	*/
	public function metaBoxes( $post_type ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );
		if( $post_type !== self::CUSTOM_POST_TYPE ) return;

		// add_meta_box( 'tennis_event_type_meta_box' //id
		// 			, 'Event Type' //Title
		// 			, array( $this, 'eventTypeCallBack' ) //Callback
		// 			, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
		// 			, 'normal' //context: normal, side, advanced
		// 			, 'high' // priority: low, high, default
		// 			// array callback args
		// 		);

	}
	
	/**
	 * Update the tennis database
	 */
	public function updateTennisDB( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='${post_id}'");
		
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		
        $post = get_post( $post_id );
        $this->log->error_log($post, "$loc: post...");
        if( $post->post_type !== self::CUSTOM_POST_TYPE ) return;

		$clubName = $post->post_title;

		if( empty( $clubName ) ) {
			$this->log->error_log( "$loc - title/club name must be provided." );
			return;
		}
		
        $club = $this->getClubByExtRef( $post_id ) ?? new Club( $clubName );
        //Temporary
        // $club = $this->getClubByExtRef( $post_id ) ?? $this->getClubByName($clubName);
        // if( empty($club) ) {
        //     $this->log->error_log( "$loc - could not find club ${clubName}." );
		// 	return;
        // }

        //Set Club external references
        if( $club->isNew() ) $club->addExternalRef( (string)$post_id );
        
        //Set the club name to the post's title
        $club->setName( $clubName );
	
		$club->save();
	}

	public function deleteTennisDB( int $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='$post_id'");

		$homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
		$club = $this->getClubByExtRef( $post_id );
		//Don't allow default club to be deleted
		//TODO: how do we delete all clubs?
		if( isset($club) && ($homeClubId === $club->getID() ) ) {
			$this->log->error_log("$loc: Cannot delete the default home club '{$homeClubId}'");
			return;
		}

		$post = get_post( $post_id );
		if( isset( $post ) && $post->post_type === self::CUSTOM_POST_TYPE ) {
			//Delete the club
			if( ! is_null( $club ) ) $club->delete();
		}
	}
	
	/**
	 * Get the Club using external reference
	 * @param int $postId The id of the event custom post 
	 * @return Club if found null otherwise
	 */
	private function getClubByExtRef( $postId ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: postId='$postId'");

		$result = null;
		$clubs = Club::getClubByExtRef( $postId );
		if( is_array( $clubs ) ) {
			$result = $clubs[0];
		}
		else {
			$result = $clubs;
		}
		return $result;
	}

	/**
	 * Finds a Club using the name of the club
	 * @param $name The name of the club
	 * @return Club if found null otherwise
	 */
	private function getClubByName( string $name ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: name='$name'");

		$candidates = Club::search( $name );
		$club = null;
		$test = strtolower(str_replace(' ', '', $name ) );
		foreach( $candidates as $clb ) {
			if( strtolower(str_replace( ' ', '', $clb->getName())) === $test ) {
				$club = $clb;
				break;
			}
		}
		return $club;
	}
	
	/**
	 * Get the date value from date string
	 * @param string $testDate
	 * @return mixed Datetime object if successful; Error object otherwise
	 */
	public function getDateValue( string $testDate ) {
		$loc =__CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: $testDate");

		$result = new WP_Error('unknown error');

		$test = DateTime::createFromFormat( '!Y/m/d', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'Y/n/j|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'Y-m-d|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'Y-n-j|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'd-m-Y|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'd/m/Y|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'm-d-Y|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( 'm/d/Y|', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ATOM, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ISO8601, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::W3C, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::RFC822, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::RFC3339, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::RSS, $testDate );
		if( false === $test ) {
			$mess = implode(';',DateTime::getLastErrors()['errors']);
			$result = new WP_Error( $mess );
		}
		else {
			$result = $test;
		}

        return $result;
	}
		
	/**
	 * Test if a date in string form is valid
	 * @param string $testDate
	 * @return bool True if date is valid; false otherwise
	 */
	public function isDateValid( string $testDate ) {
		$result = false;
		if( empty( $testDate ) ) return $result;
		//DateTimeInterface::ATOM
		//DateTimeInterface::ISO8601
		//DateTimeInterface::W3C

		$test = DateTime::createFromFormat( '!Y/m/d', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ATOM, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ISO8601, $testDate );
		if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::W3C, $testDate );
		
		if(false !== $test) {
			$result = true;
		}

        return $result;
	}

	private function getDateStr( DateTime $date ) {
		static $datetimeformat = "Y-m-d H:i:s";
		static $dateformat = "!Y-m-d";
		static $storageformat = "Y-m-d";

		return $date->format( $storageformat );
	}
	
	/**
	 * Detect if we are creating a new Tennis Club
	 */
	private function isNewClub() {
		global $pagenow;
		return $pagenow === 'post-new.php';
	}

	private function addNotice() {
		add_action( 'admin_notices', array($this,'sample_admin_notice__success') );
	}
	
	public function sample_admin_notice__success() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Done!', 'sample-text-domain' ); ?></p>
		</div>
		<?php
	}
} //end class