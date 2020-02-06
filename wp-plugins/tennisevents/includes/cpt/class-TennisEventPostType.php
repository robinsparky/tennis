<?php
//TODO: use namespaces
namespace TennisCustomPostType;

/** 
 * Data and functions for the Tennis Event Custom Post Type
 * @class  TennisEventPostType
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisEvent {
	
	const CUSTOM_POST_TYPE     = 'tenniseventcpt';
	const CUSTOM_POST_TYPE_TAX = 'tenniseventcategory';
	const CUSTOM_POST_TYPE_TAG = 'tenniseventtag';

	const START_DATE_META_KEY      = '_tennisevent_start_date_key';
	const END_DATE_META_KEY        = '_tennisevent_end_date_key';
	const SIGNUP_BY_DATE_META_KEY  = '_tennisevent_signupby_date';
	const EVENT_FORMAT_META_KEY    = '_tennisevent_format';
	const EVENT_TYPE_META_KEY      = '_tennisevent_type';
	const MATCH_TYPE_META_KEY      = '_tennisevent_match_type';
	
    //Only emit on this page
	private $hooks = array('post.php', 'post-new.php');

	private $log;

	public function __construct() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log = new BaseLogger( false );

		$this->mediaMetaBoxId = 'care_course_video_meta_box';
		//$this->curriculum = array( 'recommended' =>'Recommended', 'essential' => 'Essential' );
		$this->needsApproval = array('no' => 'No', 'yes' => 'Yes');
	}

	public function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		add_action( 'init', array( $this, 'customPostType') ); 
		add_action( 'init', array( $this, 'customTaxonomy' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue') );
			
		add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $this, 'addColumns' ), 10 );
		add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $this, 'getColumnValues'), 10, 2 );
		add_filter( 'manage_edit-' .self::CUSTOM_POST_TYPE . '_sortable_columns', array( $this, 'sortableColumns') );
		add_action( 'pre_get_posts', array( $this, 'orderby' ) );
		
		//Required actions for meta boxes
		add_action( 'add_meta_boxes', array( $this, 'metaBoxes' ) );
		//Actions for save functions
		add_action( 'save_post', array( $this, 'eventTypeSave') );
		add_action( 'save_post', array( $this, 'eventFormatSave') );
		add_action( 'save_post', array( $this, 'matchTypeSave') );
		add_action( 'save_post', array( $this, 'signupBySave') );
		add_action( 'save_post', array( $this, 'startDateSave') );
		add_action( 'save_post', array( $this, 'endDateSave') );
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

		$labels = array( 'name' => 'Care Courses'
					   , 'singular_name' => 'Care Course'
					   , 'add_new' => 'Add Care Course'
					   , 'add_new_item' => 'New Care Course'
					   , 'new_item' => 'New Care Course'
					   , 'edit_item' => 'Edit Care Course'
					   , 'view_item' => 'View Care Course'
					   , 'all_items' => 'All Care Courses'
					   , 'menu_name' => 'Care Courses'
					   , 'search_items'=>'Search Care Courses'
					   , 'not_found' => 'No Care Courses found'
					   , 'not_found_in_trash'=> 'No Care Courses found in Trash'
					   , 'parent_item_colon' => 'Parent Care Course:' );
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'menu_position' => 6
					 , 'menu_icon' => 'dashicons-welcome-learn-more'
					 , 'exclude_from_search' => false
					 , 'has_archive' => true
					 , 'publicly_queryable' => true
					 , 'query_var' => true
					 , 'capability_type' => 'post'
					 , 'hierarchical' => false
					 , 'rewrite' => array( 'slug' => self::CUSTOM_POST_TYPE )
					 //, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields' ) 
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
		$newColumns['title'] = __('Title', TennisEvents::TEXT_DOMAIN );
		$newColumns['taxonomy-tenniseventcategory'] = __('Category', TennisEvents::TEXT_DOMAIN );
		$newColumns['event_type'] = __('Event Type', TennisEvents::TEXT_DOMAIN );
		$newColumns['event_format'] = __('Format', TennisEvents::TEXT_DOMAIN );
		$newColumns['match_type'] = __('Match Type', TennisEvents::TEXT_DOMAIN );
		$newColumns['signup_by_date'] = __('Signup By', TennisEvents::TEXT_DOMAIN );
		$newColumns['author'] = __('Author', TennisEvents::TEXT_DOMAIN);
		$newColumns['start_date'] = __('Start Date', TennisEvents::TEXT_DOMAIN );
		$newColumns['end_date'] = __('End Date', TennisEvents::TEXT_DOMAIN );
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
			if( @$eventType  ) {
				echo $eventType;
			}
			else {
				echo "Nothing selected";
			}
		}
		elseif( $column_name === 'event_format' ) {
			$eventFormat = get_post_meta( $postID, self::EVENT_FORMAT_META_KEY, TRUE );
			if( @$eventFormat  ) {
				echo $eventFormat;
			}
			else {
				echo "Nothing selected";
			}
		}
		elseif( $column_name === 'match_type') {
			$matchType = get_post_meta( $postID, self::MATCH_TYPE_META_KEY, TRUE );
			if( @$matchTyhpe ) {
				echo $matchType;
			}
			else {
				echo "Nothing selected";
			}
		}
		elseif( $column_name === 'signupby_date') {
			$signupBy = get_post_meta( $postID, self::SIGNUP_BY_DATE_META_KEY, TRUE );
			if( @$signupBy  ) {
				echo $signupBy;
			}
			else {
				echo "Nothing selected";
			}
		}
		elseif( $column_name === 'start_date' ) {
			$start = get_post_meta( $postID, self::START_DATE_META_KEY, TRUE );
			if( @$start  ) {
				echo $start;
			}
			else {
				echo "Nothing selected";
			}
		}
		elseif( $column_name === 'end_date' ) {
			$end = get_post_meta( $postID, self::END_DATE_META_KEY, TRUE );
			if( @$end  ) {
				echo $end;
			}
			else {
				echo "Nothing selected";
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
	public function metaBoxes() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );
		
		add_meta_box( 'tennis_event_type_meta_box' //id
					, 'Event Type' //Title
					, array( $this, 'eventTypeCallBack' ) //Callback
					, self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
					, 'normal' //context: normal, side
					, 'high' // priority: low, high, default
					// array callback args
				);
				
		add_meta_box( 'tennis_event_format_meta_box'
					, 'Format' //Title
					, array( $this, 'formatCallBack' ) //Callback
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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_event_type_field">';
		foreach( EventType::AllTypes() as $val ) {
			$disp = esc_attr($val);
			$value = esc_attr($val);
			$sel = '';
			if($actual === $key) $sel = 'selected';
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

		update_post_meta( $post_id, self::EVENT_TYPE_META_KEY, $eventType );

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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_event_format_field">';
		$formats = ['selim'=>'Single Elimination', 'delim'=>'Double Elimination'];
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

		update_post_meta( $post_id, self::EVENT_FORMAT_META_KEY, $eventFormat );

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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		
		//Now echo the html desired
		echo'<select name="tennis_match_type_field">';
		foreach( MatchType::AllTypes() as $val ) {
			$disp = esc_attr($val);
			$value = esc_attr($val);
			$sel = '';
			if($actual === $val) $sel = 'selected';
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

		update_post_meta( $post_id, self::MATCH_TYPE_META_KEY, $matchType );

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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_signup_by_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * Signup By save
	 */
	public function signupBySave( $post_ID ) {
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

		update_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY, $signupBy );

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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_start_date_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * Start Date save
	 */
	public function startDateSave( $post_ID ) {
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

		update_post_meta( $post_id, self::START_DATE_META_KEY, $startDate );

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
		if( !@$actual ) $actual = 'unknown';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf( '<input type="date" name="tennis_end_date_field" value="%s">'
						 , $actual);
		
		echo $markup;
	}

	/**
	 * End Date save
	 */
	public function endDateSave( $post_ID ) {
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

		update_post_meta( $post_id, self::END_DATE_META_KEY, $endDate );

	}

} //end class

if( class_exists('TennisCustomPostType\TennisEvent') ) {
	$event = new TennisCustomPostType\TennisEvent();
	$event->register();
}

