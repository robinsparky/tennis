<?php
namespace cpt;

use \DateTime;
use \DateTimeInterface;
use \WP_Error;
use commonlib\BaseLogger;

// use TennisClubMembership;
// use datalayer\MemberRegistration;

/** 
 * TennisMemberCpt is a Custom Post Type to support Person links in WP
 * @class  TennisMemberCpt
 * @package Tennis Club Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisMemberCpt {
	
	const CUSTOM_POST_TYPE     = 'tennismembercpt';
	const CUSTOM_POST_TYPE_TAX = 'tennismembercategory';
	const CUSTOM_POST_TYPE_TAG = 'tennismembertag';
    const CUSTOM_POST_TYPE_SLUG  = 'clubpeople';
	
    //Only emit on this page
	private $hooks = array('post.php', 'post-new.php');

	private $log;

	/**
	 * Register actions, filters and scripts for this post type
	 */
	public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log( $loc );
		
		$tennisClubMember = new self();

		$tennisClubMember->customPostType(); 
		//$tennisClub->customTaxonomy();
		add_action( 'admin_enqueue_scripts', array( $tennisClubMember, 'enqueue') );
			
		// add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $tennisMembership, 'addColumns' ), 10 );
		// add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $tennisMembership, 'getColumnValues'), 10, 2 );
		// add_filter( 'manage_edit-' .self::CUSTOM_POST_TYPE . '_sortable_columns', array( $tennisMembership, 'sortableColumns') );
		// add_action( 'pre_get_posts', array( $tennisMembership, 'orderby' ) );
		
		//Required actions for meta boxes
		//add_action( 'add_meta_boxes', array( $tennisMembership, 'metaBoxes' ) );

		// Hook for updating/inserting into Tennis tables
		//add_action( 'save_post', array( $tennisMembership, 'updateTennisDB'), 12 );
		//Hook for deleting cpt
		//add_action( 'delete_post', array( $tennisMembership, 'deleteTennisDB') );
	}
	
	public function __construct() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log = new BaseLogger( true );
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

		$labels = array( 'name' => 'Club Member'
					   , 'singular_name' => 'Club Member'
					   //, 'add_new' => 'Add Club Member'
					   //, 'add_new_item' => 'New Club Member'
					   //, 'new_item' => 'New Club Member'
					   //, 'edit_item' => 'Edit Club Member'
					   , 'view_item' => 'View Club Member'
					   , 'all_items' => 'All Club Members'
					   , 'menu_name' => 'Club Member'
					   , 'search_items'=>'Search Club Members'
					   , 'not_found' => 'No Club Members found'
                       , 'not_found_in_trash'=> 'No Club Members found in Trash');
                       
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'description' => 'Club Member as a CPT'
					 , 'menu_position' => 93
					 , 'menu_icon' => 'dashicons-code-standards'
					 , 'exclude_from_search' => false
					 , 'has_archive' => true
					 , 'publicly_queryable' => true
					 , 'query_var' => true
					 , 'capability_type' => 'post'
					 , 'capabilities' => array('create_posts'=>false)
					 , 'map_meta_cap' => true
					 , 'hierarchical' => true
					 , 'show_in_rest' => true //causes Gutenberg editor to be used
					 , 'rewrite' => array( 'slug' => self::CUSTOM_POST_TYPE_SLUG, 'with_front' => false )
					 //, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ) 
					 , 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ) 
					 , 'public' => true );
		$res = register_post_type( self::CUSTOM_POST_TYPE, $args );
		if(is_wp_error($res)) {
			throw new \Exception($res->get_error_message());
		}
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
		//$newColumns['title'] = __( 'Club Name', TennisClubMembership::TEXT_DOMAIN ); //$columns['title'];
		//$newColumns['taxonomy-tennisclubcategory'] = __('Category', TennisClubMembership::TEXT_DOMAIN );
		// $newColumns['author'] = $columns['author'];
        // $newColumns['date'] = $columns['date'];
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
     * Populate the TennisClubRegistrationCpt columns with values
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
		$labels = array( 'name' => 'Club Member Categories'
						, 'singular_name' => 'Club Member Category'
						, 'search_items' => 'Club Member Search Category'
						, 'all_items' => 'All Club Member Categories'
						, 'parent_item' => 'Parent Club Member Category'
						, 'parent_item_colon' => 'Parent Club Registration Category:'
						, 'edit_item' => 'Edit Club Member Category'
						, 'update_item' => 'Update Club Member Category'
						, 'add_new_item' => 'Add New Club Member Category'
						, 'new_item_name' => 'New Club Member Category'
						, 'menu_name' => 'Club Member Categories'
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
						 , array( 'label' => 'Club Registration Tags'
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
		$this->log->error_log("$loc: post_id='{$post_id}'");
		
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

	}

	public function deleteTennisDB( int $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='$post_id'");

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
		//if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ISO8601, $testDate );
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
		//if(false === $test) $test = DateTime::createFromFormat( DateTimeInterface::ISO8601, $testDate );
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