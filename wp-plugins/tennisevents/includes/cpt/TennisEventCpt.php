<?php
namespace cpt;
use api\BaseLoggerEx;
use \TennisEvents;
use \EventType;
use \MatchType;
use \Format;
use \Event;
use \Club;
use \InvalidEventException;

/** 
 * Data and functions for the Tennis Event Custom Post Type
 * @class  TennisEventCpt
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisEventCpt {
	
	const CUSTOM_POST_TYPE     = 'tenniseventcpt';
	const CUSTOM_POST_TYPE_TAX = 'tenniseventcategory';
	const CUSTOM_POST_TYPE_TAG = 'tenniseventtag';

	const START_DATE_META_KEY      = '_tennisevent_start_date_key';
	const END_DATE_META_KEY        = '_tennisevent_end_date_key';
	const SIGNUP_BY_DATE_META_KEY  = '_tennisevent_signupby_date';
	const EVENT_FORMAT_META_KEY    = '_tennisevent_format';
	const EVENT_TYPE_META_KEY      = '_tennisevent_type';
	const MATCH_TYPE_META_KEY      = '_tennisevent_match_type';
	const PARENT_EVENT_META_KEY    = '_tennisevent_parent_event';
	
    //Only emit on this page
	private $hooks = array('post.php', 'post-new.php');

	private $log;

	/**
	 * Register actions, filters and scripts for this post type
	 */
	public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log( $loc );
		
		$tennisEvt = new TennisEventCpt();

		$tennisEvt->customPostType(); 
		$tennisEvt->customTaxonomy();
		add_action( 'admin_enqueue_scripts', array( $tennisEvt, 'enqueue') );
			
		add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $tennisEvt, 'addColumns' ), 10 );
		add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $tennisEvt, 'getColumnValues'), 10, 2 );
		add_filter( 'manage_edit-' .self::CUSTOM_POST_TYPE . '_sortable_columns', array( $tennisEvt, 'sortableColumns') );
		add_action( 'pre_get_posts', array( $tennisEvt, 'orderby' ) );
		
		//Required actions for meta boxes
		add_action( 'add_meta_boxes', array( $tennisEvt, 'metaBoxes' ) );
		//Actions for save functions re meta values
		// add_action( 'save_post', array( $tennisEvt, 'eventTypeSave'), 10 );
		// add_action( 'save_post', array( $tennisEvt, 'eventFormatSave'), 10 );
		// add_action( 'save_post', array( $tennisEvt, 'matchTypeSave'), 10 );
		// add_action( 'save_post', array( $tennisEvt, 'signupBySave'), 10 );
		// add_action( 'save_post', array( $tennisEvt, 'startDateSave'), 10 );
		// add_action( 'save_post', array( $tennisEvt, 'endDateSave'), 10 );
		// Hook for updating/inserting into Tennis tables
		add_action( 'save_post', array( $tennisEvt, 'updateTennisDB'), 12 );
		//Hook for deleting cpt
		add_action( 'delete_post', array( $tennisEvt, 'deleteTennisDB') );
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

		$labels = array( 'name' => 'Tennis Events'
					   , 'singular_name' => 'Tennis Event'
					   , 'add_new' => 'Add Tennis Event'
					   , 'add_new_item' => 'New Tennis Event'
					   , 'new_item' => 'New Tennis Event'
					   , 'edit_item' => 'Edit Tennis Event'
					   , 'view_item' => 'View Tennis Event'
					   , 'all_items' => 'All Tennis Events'
					   , 'menu_name' => 'Tennis Events'
					   , 'search_items'=>'Search Events'
					   , 'not_found' => 'No Tennis Events found'
					   , 'not_found_in_trash'=> 'No Tennis Events found in Trash'
					   , 'parent_item_colon' => 'Parent Event' );
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'description' => 'Tennis Event as a CPT'
					 , 'menu_position' => 80
					 , 'menu_icon' => 'dashicons-code-standards'
					 , 'exclude_from_search' => false
					 , 'has_archive' => true
					 , 'publicly_queryable' => true
					 , 'query_var' => true
					 , 'capability_type' => 'post'
					 , 'hierarchical' => true
					 , 'show_in_rest' => true //causes Gutenberg editor to be used
					 , 'rewrite' => array( 'slug' => 'tennisevent', 'with_front' => false )
					 //, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ) 
					 , 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ) 
					 , 'public' => true );
		register_post_type( self::CUSTOM_POST_TYPE, $args );
	}

	// Add Course columns
	public function addColumns( $columns ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $columns, $loc );

		// column vs displayed title
		$newColumns['cb'] = $columns['cb'];
		$newColumns['title'] = $columns['title'];
		$newColumns['taxonomy-tenniseventcategory'] = __('Category', TennisEvents::TEXT_DOMAIN );
		$newColumns['event_type'] = __('Event Type', TennisEvents::TEXT_DOMAIN );
		$newColumns['parent_event'] = __('Parent', TennisEvents::TEXT_DOMAIN );
		$newColumns['event_format'] = __('Format', TennisEvents::TEXT_DOMAIN );
		$newColumns['match_type'] = __('Match Type', TennisEvents::TEXT_DOMAIN );
		$newColumns['signup_by_date'] = __('Signup By', TennisEvents::TEXT_DOMAIN );
		$newColumns['start_date'] = __('Start Date', TennisEvents::TEXT_DOMAIN );
		$newColumns['end_date'] = __('End Date', TennisEvents::TEXT_DOMAIN );
		$newColumns['author'] = $columns['author'];
		$newColumns['date'] = $columns['date'];
		return $newColumns;
	}

	public function sortableColumns ( $columns ) {
		$columns['start_date'] = 'startDate';
		$columns['taxonomy-tenniseventcategory'] = 'categorySort';
		return $columns;
	}

	public function orderby ( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		if( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		  
		if ( 'startDate' === $query->get( 'orderby') ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', self::START_DATE_META_KEY );
			$query->set( 'meta_type', 'numeric' );
		}
		elseif( 'categorySort' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', self::CUSTOM_POST_TYPE_TAX );
		}
	}

	// Populate the Tennis Event columns with values
	public function getColumnValues( $column_name, $postID ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( "$loc --> $column_name, $postID" );

		if( $column_name === 'event_type') {
			$eventType = get_post_meta( $postID, self::EVENT_TYPE_META_KEY, TRUE );
			if( !empty($eventType) ) {
				echo EventType::AllTypes()[$eventType];
			}
			else {
				echo "";
			}
		}
		elseif( $column_name === 'parent_event' ) {
			$eventParentId = get_post_meta( $postID, self::PARENT_EVENT_META_KEY, TRUE );
			if( !empty($eventParentId) ) {
				$tecpt = get_post( $eventParentId );
				$name = "";
				if( !is_null( $tecpt ) ) {
					$name = $tecpt->post_title;
				}
				echo "$name($eventParentId)";
			}
			else {
				echo "";
			}
		}
		elseif( $column_name === 'event_format' ) {
			$eventFormat = get_post_meta( $postID, self::EVENT_FORMAT_META_KEY, TRUE );
			if( !empty($eventFormat) ) {
				echo Format::AllFormats()[$eventFormat];
			}
			else {
				echo "";
			}
		}
		elseif( $column_name === 'match_type') {
			$matchType = get_post_meta( $postID, self::MATCH_TYPE_META_KEY, TRUE );
			if( !empty($matchType) ) {
				echo MatchType::AllTypes()[$matchType];
			}
			else {
				echo "";
			}
		}
		elseif( $column_name === 'signup_by_date') {
			$signupBy = get_post_meta( $postID, self::SIGNUP_BY_DATE_META_KEY, TRUE );
			if( !empty($signupBy)  ) {
				echo $signupBy;
			}
			else {
				echo __('TBA', TennisEvents::TEXT_DOMAIN );
			}
		}
		elseif( $column_name === 'start_date' ) {
			$start = get_post_meta( $postID, self::START_DATE_META_KEY, TRUE );
			if( !empty($start) ) {
				echo $start;
			}
			else {
				echo __('TBA', TennisEvents::TEXT_DOMAIN );
			}
		}
		elseif( $column_name === 'end_date' ) {
			$end = get_post_meta( $postID, self::END_DATE_META_KEY, TRUE );
			if( !empty($end) ) {
				echo $end;
			}
			else {
				echo __('TBA', TennisEvents::TEXT_DOMAIN );
			}
		}
	}

	public function customTaxonomy() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );
		
			//hierarchical
		$labels = array( 'name' => 'Tennis Event Categories'
						, 'singular_name' => 'Tennis Event Category'
						, 'search_items' => 'Tennis Event Search Category'
						, 'all_items' => 'All Tennis Event Categories'
						, 'parent_item' => 'Parent Tennis Event Category'
						, 'parent_item_colon' => 'Parent Tennis Event Category:'
						, 'edit_item' => 'Edit Tennis Event Category'
						, 'update_item' => 'Update Tennis Event Category'
						, 'add_new_item' => 'Add New Tennis Event Category'
						, 'new_item_name' => 'New Tennis Event Category'
						, 'menu_name' => 'Tennis Event Categories'
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
						 , array( 'label' => 'Tennis Event Tags'
								, 'rewrite' => array( 'slug' => 'tenniseventtag' )
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

		add_meta_box( 'tennis_event_type_meta_box' //id
					, 'Event Type' //Title
					, array( $this, 'eventTypeCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side, advanced
					, 'high' // priority: low, high, default
					// array callback args
				);
				
		add_meta_box( 'tennis_parent_event_meta_box' //id
				, 'Parent Event' //Title
				, array( $this, 'parentEventCallBack' ) //Callback
				, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
				, 'side' //context: normal, side
				, 'high' // priority: low, high, default
				// array callback args
			);
	
		add_meta_box( 'tennis_event_format_meta_box'
					, 'Format' //Title
					, array( $this, 'eventFormatCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);
				
		add_meta_box( 'tennis_match_type_meta_box'
					, 'Match Type' //Title
					, array( $this, 'matchTypeCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);

		add_meta_box( 'event_signupby_meta_box'
					, 'Signup By' //Title
					, array( $this, 'signupByCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);

		add_meta_box( 'event_start_meta_box'
					, 'Start Date' //Title
					, array( $this, 'startDateCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);
				
		add_meta_box( 'event_end_meta_box'
					, 'End Date' //Title
					, array( $this, 'endDateCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);
	}
	
	/**
	 * Event Type callback
	 */
	public function eventTypeCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'eventTypeSave' //action
					  , 'tennis_event_type_nonce');

		$actual = get_post_meta( $post->ID, self::EVENT_TYPE_META_KEY, true );
		if( !@$actual ) $actual = EventType::TOURNAMENT;
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_event_type_field">';
		foreach( EventType::AllTypes() as $key=>$val ) {
			$disp = esc_attr($val);
			$value = esc_attr($key);
			$sel = '';
			if($actual === $value) $sel = 'selected';
			echo "<option value='$value' $sel>$disp</option>";
		}
		echo '</select>';
	}

	/**
	 * Event Type save
	 */
	public function eventTypeSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if( ! isset( $_POST['tennis_event_type_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_event_type_nonce'] , 'eventTypeSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_event_type_field'] ) ) {
			$this->log->error_log("$loc --> no event type field");
			return;
		}
		else {
			$eventType = $_POST['tennis_event_type_field'];
		}

		$this->log->error_log("$loc --> event type='$eventType'");

		if( !empty( $eventType ) ) {
			update_post_meta( $post_id, self::EVENT_TYPE_META_KEY, $eventType );
		}
		else {
			delete_post_meta( $post_id, self::EVENT_TYPE_META_KEY );
		}

	}
	
	/**
	 * Parent Event callback
	 */
	public function parentEventCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'parentEventSave' //action
					  , 'tennis_parent_event_nonce');

		$actual = get_post_meta( $post->ID, self::PARENT_EVENT_META_KEY, true );
		if( !isset($actual) ) $actual = "";
		$this->log->error_log("$loc --> actual='$actual'");
		
		//Now echo the html desired
		echo '<select name="tennis_parent_event_field">';
		echo '<option value="-1">Remove Parent...</option>';
		foreach( $this->parentEvents( $post ) as $candidate ) {
			$disp = esc_attr($candidate->post_title);
			$value = esc_attr($candidate->ID );
			$sel = '';
			if($actual === $value) $sel = 'selected';
			echo "<option value='$value' $sel>$disp</option>";
		}
		echo '</select>';
		echo "<input type='hidden' name='currentExtRefId' value='$actual'/>";
	}

	/**
	 * Retrieve candidate parent events for the given post
	 * @param $post A tennis event cpt
	 */
	private function parentEvents( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$result = [];
		if( $post->post_type === self::CUSTOM_POST_TYPE ) {
			$args = array('numberposts'  => -1,
						'category'   => 0,
						'orderby'    => 'title',
						'order'      => 'ASC',
						'include'    => array(),
						'exclude'    => array( $post->ID ),
						//   'meta_key'   => self::PARENT_EVENT_META_KEY,
						//   'meta_value' => '',
						'post_type'  => self::CUSTOM_POST_TYPE,
						'suppress_filters' => true );
			foreach(get_posts( $args ) as $p ) {
				$parEvtId = get_post_meta( $p->ID, self::PARENT_EVENT_META_KEY, true );
				if( !empty( $parEvtId ) ) continue;
				//Only want root events (i.e. events without parents)
				$result[] = $p;
			}
		}
		return $result;
	}
	
	/**
	 * Parent Event save
	 */
	public function parentEventSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if( ! isset( $_POST['tennis_parent_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_parent_event_nonce'] , 'parentEventSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_parent_event_field'] ) ) {
			$this->log->error_log("$loc --> no parent event field");
			return;
		}
		else {
			$parentEvent = $_POST['tennis_parent_event_field'];
		}

		$this->log->error_log("$loc --> parent event='$parentEvent'");

		if( !empty( $eventType ) ) {
			update_post_meta( $post_id, self::PARENT_EVENT_META_KEY, $eventType );
		}
		else {
			delete_post_meta( $post_id, self::PARENT_EVENT_META_KEY );
		}

	}
	
	
	/**
	 * Event Format callback
	 */
	public function eventFormatCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'eventFormatSave' //action
					  , 'tennis_event_format_nonce');

		$actual = get_post_meta( $post->ID, self::EVENT_FORMAT_META_KEY, true );
		if( !isset($actual) ) $actual = Format::SINGLE_ELIM;
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_event_format_field">';
		$formats = Format::AllFormats();
		foreach( $formats as $key=>$val ) {
			$disp = esc_attr($val);
			$value = esc_attr($key);
			$sel = '';
			if($actual === $key) $sel = 'selected';
			echo "<option value='$value' $sel>$disp</option>";
		}
		echo '</select>';
	}

	/**
	 * Event Format save
	 */
	public function eventFormatSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if( ! isset( $_POST['tennis_event_format_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_event_format_nonce'] , 'eventFormatSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_event_format_field'] ) ) {
			$this->log->error_log("$loc --> no event format field");
			return;
		}
		else {
			$eventFormat = $_POST['tennis_event_format_field'];
		}

		$this->log->error_log("$loc --> event type='$eventFormat'");

		if( !empty( $eventFormat ) ) {
			update_post_meta( $post_id, self::EVENT_FORMAT_META_KEY, $eventFormat );
		}
		else {
			delete_post_meta( $post_id, self::EVENT_FORMAT_META_KEY );
		}

	}
	
	/**
	 * Match Type callback
	 */
	public function matchTypeCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'matchTypeSave' //action
					  , 'tennis_match_type_nonce');

		$actual = get_post_meta( $post->ID, self::MATCH_TYPE_META_KEY, true );
		if( !@$actual ) $actual = MatchType::MENS_SINGLES;
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_match_type_field">';
		foreach( MatchType::AllTypes() as $key => $val ) {
			$disp = esc_attr($val);
			$value = esc_attr($key);
			$sel = '';
			if($actual === $value) $sel = 'selected';
			echo "<option value='$value' $sel>$disp</option>";
		}
		echo '</select>';
	}

	/**
	 * Match Type save
	 */
	public function matchTypeSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if( ! isset( $_POST['tennis_match_type_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_match_type_nonce'] , 'matchTypeSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_match_type_field'] ) ) {
			$this->log->error_log("$loc --> no match type field");
			return;
		}
		else {
			$matchType = $_POST['tennis_match_type_field'];
		}

		$this->log->error_log("$loc --> match type='$matchType'");

		if( !empty( $matchType ) ) {
			update_post_meta( $post_id, self::MATCH_TYPE_META_KEY, $matchType );
		}
		else {
			delete_post_meta( $post_id, self::MATCH_TYPE_META_KEY );
		}

	}
	
	/**
	 * Signup By callback
	 */
	public function signupByCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'signupBySave' //action
					  , 'tennis_signup_by_nonce');

		$actual = get_post_meta( $post->ID, self::SIGNUP_BY_DATE_META_KEY, true );
		if( !@$actual ) $actual = '';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_signup_by_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * Signup By save
	 */
	public function signupBySave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( ! isset( $_POST['tennis_signup_by_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_signup_by_nonce'] , 'signupBySave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_signup_by_field'] ) ) {
			$this->log->error_log("$loc --> no signup by field");
			return;
		}
		else {
			$signupBy = $_POST['tennis_signup_by_field'];
		}

		$this->log->error_log("$loc --> signup by='$signupBy'");

		//TODO: validate the date??
		if( !empty( $signupBy ) ) {
			update_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY, $signupBy );
		}
		else {
			delete_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY );
		}

	}
	
	/**
	 * Start Date callback
	 */
	public function startDateCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'startDateSave' //action
					  , 'tennis_start_date_nonce');

		$actual = get_post_meta( $post->ID, self::START_DATE_META_KEY, true );
		if( !@$actual ) $actual = '';
		$this->log->error_log("$loc --> actual='$actual'");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_start_date_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * Start Date save
	 */
	public function startDateSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( ! isset( $_POST['tennis_start_date_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_start_date_nonce'] , 'startDateSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_start_date_field'] ) ) {
			$this->log->error_log("$loc --> no start date field");
			return;
		}
		else {
			$startDate = $_POST['tennis_start_date_field'];
		}

		$this->log->error_log("$loc --> start date='$startDate'");

		//TODO: Validate start date??
		if( !empty( $startDate ) ) {
			update_post_meta( $post_id, self::START_DATE_META_KEY, $startDate );
		}
		else {
			delete_post_meta( $post_id, self::START_DATE_META_KEY );
		}

	}
	
	/**
	 * End Date callback
	 */
	public function endDateCallBack( $post ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'endDateSave' //action
					  , 'tennis_end_date_nonce');

		$actual = get_post_meta( $post->ID, self::END_DATE_META_KEY, true );
		if( !@$actual ) $actual = '';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_end_date_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * End Date save
	 */
	public function endDateSave( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( ! isset( $_POST['tennis_end_date_nonce'] ) ) {
			$this->log->error_log("$loc --> no nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_end_date_nonce'] , 'endDateSave'  )) {
			$this->log->error_log("$loc --> bad nonce");
			return;
		}

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		if( ! isset( $_POST['tennis_end_date_field'] ) ) {
			$this->log->error_log("$loc --> no end date field");
			return;
		}
		else {
			$endDate = $_POST['tennis_end_date_field'];
		}

		$this->log->error_log("$loc --> end date='$endDate'");

		//TODO: Validate end date??
		if( !empty( $endDate ) ) {
			update_post_meta( $post_id, self::END_DATE_META_KEY, $endDate );
		}
		else {
			delete_post_meta( $post_id, self::END_DATE_META_KEY );
		}
	}

	
	/**
	 * Update the tennis database
	 */
	public function updateTennisDB( $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$this->log->error_log($_POST, "POST:");
		
		if( ! isset( $_POST['tennis_end_date_nonce'] ) ) {
			$this->log->error_log("$loc --> no end date nonce");
			return;
		}

		if( ! wp_verify_nonce( $_POST['tennis_end_date_nonce'] , 'endDateSave'  )) {
			$this->log->error_log("$loc --> bad end date nonce");
			return;
		}
		
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		
		$evtName = "";
		if( isset( $_POST['post_title'] ) ) {
			$evtName = sanitize_text_field($_POST['post_title']);
		}
		else {
			$p = get_post( $post_id );
			$evtName = isset($p) ? $p->post_title : "";
		}

		if( empty( $evtName ) ) {
			$this->log->error_log( "$loc - title must be provided." );
			return;
		}

		$homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
		if( 0 === $homeClubId ) {
			$this->log->error_log( "$loc - Home club id is not set." );
			throw new InvalidEventException(__('Home club id is not set.'), TennisEvents::TEXT_DOMAIN );
		}

		$eventType = '';
		if( isset( $_POST['tennis_event_type_field'] ) ) {
			$eventType = sanitize_text_field($_POST['tennis_event_type_field']);
		}
		if( !empty( $eventType ) ) {
			update_post_meta( $post_id, self::EVENT_TYPE_META_KEY, $eventType );
		}
		else {
			delete_post_meta( $post_id, self::EVENT_TYPE_META_KEY );
		}
		
		$parentPostId = 0;
		$parentEvent = $parentPost = null;
		$currentParentPostId = $_POST['currentExtRefId'];
		if( isset( $_POST['tennis_parent_event_field'] ) ) {
			$parentPostId = (int)sanitize_text_field( $_POST['tennis_parent_event_field'] );
		}

		if( !empty( $parentPostId ) && (int)$parentPostId > 0 ) {
			$parentPost = get_post( $parentPostId );
			if( is_null( $parentPost ) ) {
				//TODO: Insert into external ref table????
				delete_post_meta( $post_id, self::PARENT_EVENT_META_KEY );
				throw new InvalidEventException( __('No such parent post', TennisEvents::TEXT_DOMAIN ) );
			}
			$parentEvent = $this->getEventByExtRef( $parentPostId );
			if( is_null( $parentEvent ) ) {
				delete_post_meta( $post_id, self::PARENT_EVENT_META_KEY );
				throw new InvalidEventException( __('No such parent event', TennisEvents::TEXT_DOMAIN) );
			}
			update_post_meta( $post_id, self::PARENT_EVENT_META_KEY, $parentPostId );
		}
		else {
			delete_post_meta( $post_id, self::PARENT_EVENT_META_KEY );
		}

		$eventFormat = '';
		if( isset( $_POST['tennis_event_format_field'] ) ) {
			$eventFormat = sanitize_text_field($_POST['tennis_event_format_field']);
		}
		if( !empty( $eventFormat ) ) {
			update_post_meta( $post_id, self::EVENT_FORMAT_META_KEY, $eventFormat );
		}
		else {
			delete_post_meta( $post_id, self::EVENT_FORMAT_META_KEY );
		}

		$matchType = '';
		if( isset( $_POST['tennis_match_type_field'] ) ) {
			$matchType = sanitize_text_field($_POST['tennis_match_type_field']);
		}
		if( !empty( $matchType ) ) {
			update_post_meta( $post_id, self::MATCH_TYPE_META_KEY, $matchType );
		}
		else {
			delete_post_meta( $post_id, self::MATCH_TYPE_META_KEY );
		}

		//TODO: Validate the following 3 date fields
		$signupBy = "";
		if( isset( $_POST['tennis_signup_by_field'] ) ) {
			$signupBy = $_POST['tennis_signup_by_field'];
		}
		
		if( !empty( $signupBy ) ) {
			update_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY, $signupBy );
		}
		else {
			delete_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY );
		}

		$startDate = "";
		if( isset( $_POST['tennis_start_date_field'] ) ) {
			$startDate = $_POST['tennis_start_date_field'];
		}
		if( !empty( $startDate ) ) {
			update_post_meta( $post_id, self::START_DATE_META_KEY, $startDate );
		}
		else {
			delete_post_meta( $post_id, self::START_DATE_META_KEY );
		}

		$endDate = "";
		if( isset( $_POST['tennis_end_date_field'] ) ) {
			$endDate = $_POST['tennis_end_date_field'];
		}
		if( !empty( $endDate ) ) {
			update_post_meta( $post_id, self::END_DATE_META_KEY, $endDate );
		}
		else {
			delete_post_meta( $post_id, self::END_DATE_META_KEY );
		}

		$club = Club::get( $homeClubId );
		$event = $this->getEventByExtRef( $post_id );
		if( is_null( $event ) ) {
			$event = new Event( $evtName );
		}
		$event->addClub( $club );

		//Set the parent event before setting other props
		$event->setParent( $parentEvent );

		//Set external references
		$event->addExternalRef( $post_id );

		if( ! $event->setEventType( $eventType ) ) {
			delete_post_meta( $post_id, self::EVENT_TYPE_META_KEY );
		}

		if( ! $event->setMatchType( $matchType ) ) {
			delete_post_meta( $post_id, self::MATCH_TYPE_META_KEY );
		}

		if( ! $event->setFormat( $eventFormat ) ) {
			delete_post_meta( $post_id, self::EVENT_FORMAT_META_KEY );
		}

		$event->setSignupBy( $signupBy );
		$event->setStartDate( $startDate );
		$event->setEndDate( $endDate );
		$this->log->error_log( $event, "$loc - Tennis Event:" );
		$event->save();
	}

	public function deleteTennisDB( int $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='$post_id'");

		$post = get_post( $post_id );
		if( isset( $post ) && $post->post_type === self::CUSTOM_POST_TYPE ) {
			//First delete any references to this post as a parent event
			$this->deleteParentReferences( $post_id );
			//Second delete the Event
			$evt = $this->getEventByExtRef( $post_id );
			if( ! is_null( $evt ) ) $evt->delete();
		}
	}

	/**
	 * Delete all references to the given parent post id
	 * @param $parent_id The id of the parent post (i.e. event custom post)
	 */
	private function deleteParentReferences( $parent_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: parent_id=$parent_id");

		$args = array('numberposts'  => -1,
						'category'   => 0,
						'orderby'    => 'title',
						'order'      => 'ASC',
						'include'    => array(),
						'exclude'    => array(),
						'meta_query' => array(
							array(
								'key'   =>  self::PARENT_EVENT_META_KEY,
								'value' =>  $parent_id,
								'compare' => '=='
							),
						),
						'post_type'  => self::CUSTOM_POST_TYPE,
						'suppress_filters' => true );
		$numDel = 0;
		$cpts = get_posts( $args );
		//$this->log->error_log( $cpts, "$loc: posts with parent: $parent_id");
		foreach( $cpts as $p ) {
			delete_post_meta( $p->ID, self::PARENT_EVENT_META_KEY );
			++$numDel;
		}
		$this->log->error_log("$loc: number parent references deleted='$numDel'");
	}
	
	/**
	 * Get the event using external reference
	 * @param $postId The id of the event custom post 
	 * @return Event if found null otherwise
	 */
	private function getEventByExtRef( $postId ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: postId='$postId'");

		$result = null;
		$events = Event::getEventByExtRef( $postId );
		if( is_array( $events ) ) {
			$result = $events[0];
		}
		else {
			$result = $events;
		}
		return $result;
	}

	/**
	 * Finds an Event using the name of the event
	 * @return Event if found null otherwise
	 * @param $name The name of the event
	 */
	private function getEventByName( string $name ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: name='$name'");

		$candidates = Event::search( $name );
		$event = null;
		$test = strtolower(str_replace(' ', '', $name ) );
		foreach( $candidates as $evt ) {
			if( strtolower(str_replace( ' ', '', $evt->getName())) === $test ) {
				$event = $evt;
				break;
			}
		}
		return $event;
	}
} //end class