<?php

namespace cpt;

use \DateTime;
use \DateTimeInterface;
use \WP_Error;
use commonlib\BaseLogger;
use \TennisEvents;
use datalayer\Event;
use datalayer\Club;
use datalayer\EventType;
use datalayer\MatchType;
use datalayer\GenderType;
use datalayer\ScoreType;
use datalayer\Format;
use datalayer\InvalidEventException;

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
	const GENDER_TYPE_META_KEY     = '_tennisevent_gender_type';
	const SCORE_TYPE_META_KEY      = '_tennisevent_score_type';
	const PARENT_EVENT_META_KEY    = '_tennisevent_parent_event';
	const NUMBER_OF_BRACKETS_KEY   = '_tennisevent_number_brackets';
	const AGE_MIN_META_KEY         = '_tennisevent_age_min';
	const AGE_MAX_META_KEY         = '_tennisevent_age_max';

	const TENNIS_EVENT_ERROR_TRANSIENT_KEY = 'tennis_event_settings_errors';

	//Only emit on this page
	private $hooks = array('post.php', 'post-new.php');
	private $admin_notice_messages = array();
	private $log;

	/**
	 * Register actions, filters and scripts for this post type
	 */
	public static function register() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log($loc);

		$tennisEvt = new TennisEventCpt();

		$tennisEvt->customPostType();
		$tennisEvt->customTaxonomy();
		add_action('admin_enqueue_scripts', array($tennisEvt, 'enqueue'));

		add_filter('manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array($tennisEvt, 'addColumns'), 10);
		add_action('manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array($tennisEvt, 'getColumnValues'), 10, 2);
		add_filter('manage_edit-' . self::CUSTOM_POST_TYPE . '_sortable_columns', array($tennisEvt, 'sortableColumns'));
		add_action('pre_get_posts', array($tennisEvt, 'orderby'));

		//Emit css in the header of admin page to hide the "view post" notice from WP
		add_action( 'admin_head-post-new.php', array($tennisEvt,'hide_view_post_css'));
		add_action( 'admin_head-post.php', array($tennisEvt,'hide_view_post_css'));
		
		//Taxonomy filter
		add_action( 'restrict_manage_posts', array($tennisEvt,'addTaxonomyFilter'), 10, 1 );

		//Gender type filter
		add_action('restrict_manage_posts', array($tennisEvt, 'genderTypeFilter'));
		add_filter('parse_query', array($tennisEvt, 'genderTypeParseFilter'));
		
		//Parent event filter
		add_action('restrict_manage_posts', array($tennisEvt, 'parentEventFilter'));
		add_filter('parse_query', array($tennisEvt, 'parentEventParseFilter'));
		
		//Bulk Actions
		add_filter('bulk_actions-edit-' . self::CUSTOM_POST_TYPE, array($tennisEvt, 'addResetBulkAction'));
		add_filter('handle_bulk_actions-edit-' . self::CUSTOM_POST_TYPE, array($tennisEvt, 'handleBulkReset'), 10, 3 );

		//Required actions for meta boxes
		add_action('add_meta_boxes', array($tennisEvt, 'metaBoxes'));

		// Hook for updating/inserting into Tennis tables
		add_action('save_post', array($tennisEvt, 'updateTennisDB'), 12);

		//Hook for deleting cpt
		add_action('delete_post', array($tennisEvt, 'deleteTennisDB'));

		//Error handling function used by updateTennisDB to show admin notices
		add_action('admin_notices', array($tennisEvt, 'handle_errors') );
	}

	/**
	 * Copy constructor
	 * @param TennisEventCpt $copyMe The optional custom post type to be copied.
	 */
	public function __construct( TennisEventCpt $copyMe = null ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log = new BaseLogger(true);

		if( !empty( $copyMe ) ) {
			//$this->post_title = $copyMe->post_title;
			foreach($copyMe as $key => $value ) {
				$this->$key = $value;
			}
			$this->log->error_log($this,"Copied CPT");
		}
	}
	
	/**
	 * Emit some css to hide the "view post" message when editing Tennis Events
	 */
	public function hide_view_post_css() {
		global $post_type;
		/* set post types */
		$post_types = array(
				self::CUSTOM_POST_TYPE
			  );
		if(in_array($post_type, $post_types))
		echo "<style type='text/css'>div#message.updated.notice {display: none;}</style>";
	}

	public function enqueue($hook) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc --> $hook");

		//Make sure we are rendering the "user-edit" page
		if (in_array($hook, $this->hooks)) {

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
		$this->log->error_log($loc);

		$labels = array(
			'name' => 'Tennis Events', 'singular_name' => 'Tennis Event', 'add_new' => 'Add Tennis Event', 'add_new_item' => 'New Tennis Event', 'new_item' => 'New Tennis Event', 'edit_item' => 'Edit Tennis Event', 'view_item' => 'View Tennis Event', 'all_items' => 'All Tennis Events', 'menu_name' => 'Tennis Events', 'search_items' => 'Search Events', 'not_found' => 'No Tennis Events found', 'not_found_in_trash' => 'No Tennis Events found in Trash', 'parent_item_colon' => 'Parent Event'
		);
		$args = array(
			'labels' => $labels
			//, 'taxonomies' => array( 'category', 'post_tag' )
			, 'description' => 'Tennis Event as a CPT'
			, 'menu_position' => 95
			, 'menu_icon' => 'dashicons-code-standards'
			, 'exclude_from_search' => false
			, 'has_archive' => true
			, 'publicly_queryable' => true
			, 'query_var' => true
			, 'capability_type' => 'post'
			, 'hierarchical' => true
			, 'show_in_rest' => false //causes Gutenberg editor to be used
			, 'rewrite' => array('slug' => 'tennisevent', 'with_front' => false)
			//, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ) 
			, 'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions'), 'public' => true
		);
		register_post_type(self::CUSTOM_POST_TYPE, $args);
	}

	// Add Course columns
	public function addColumns($columns) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($columns, $loc);

		// column vs displayed title
		$newColumns['cb'] = $columns['cb'];		
		$newColumns['tennis_season'] = __('Season', TennisEvents::TEXT_DOMAIN);
		$newColumns['parent_event'] = __('Parent', TennisEvents::TEXT_DOMAIN);
		$newColumns['title'] = $columns['title'];
		$newColumns['taxonomy-tenniseventcategory'] = __('Category', TennisEvents::TEXT_DOMAIN);
		$newColumns['event_type'] = __('Event Type', TennisEvents::TEXT_DOMAIN);
		$newColumns['signup_by_date'] = __('Signup By', TennisEvents::TEXT_DOMAIN);
		$newColumns['start_date'] = __('Start', TennisEvents::TEXT_DOMAIN);
		$newColumns['end_date'] = __('End', TennisEvents::TEXT_DOMAIN);
		$newColumns['gender_type'] = __('Gender Type', TennisEvents::TEXT_DOMAIN );
		$newColumns['match_type'] = __('Match Type', TennisEvents::TEXT_DOMAIN);
		$newColumns['event_format'] = __('Format', TennisEvents::TEXT_DOMAIN);
		$newColumns['score_type'] = __('Score Type', TennisEvents::TEXT_DOMAIN);
		$newColumns['num_brackets'] = __('Number of Brackets', TennisEvents::TEXT_DOMAIN);
		$newColumns['age_min'] = __('Minimum Age', TennisEvents::TEXT_DOMAIN );
		$newColumns['age_max'] = __('Maximum Age', TennisEvents::TEXT_DOMAIN );
		$newColumns['author'] = $columns['author'];
		$newColumns['date'] = $columns['date'];
		return $newColumns;
	}

	public function sortableColumns($columns) {
		$columns['start_date'] = 'startDate';
		$columns['taxonomy-tenniseventcategory'] = 'categorySort';
		$columns['tennis_season'] = 'seasonSort';
		return $columns;
	}

	public function orderby($query)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		if ('startDate' === $query->get('orderby')) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', self::START_DATE_META_KEY);
		} elseif ('categorySort' === $query->get('orderby')) {
			$query->set('orderby', self::CUSTOM_POST_TYPE_TAX);
		} elseif ('seasonSort' === $query->get('orderby')) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', self::START_DATE_META_KEY);
		}
	}
	
	public function addTaxonomyFilter( $post_type ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		if( self::CUSTOM_POST_TYPE !== $post_type ) {
			return;
		}

		$taxonomies_slugs = array(self::CUSTOM_POST_TYPE_TAX);
		// loop through the taxonomy filters array
		foreach( $taxonomies_slugs as $slug ) {
			$this->log->error_log("$loc: slug='{$slug}'");
			$taxonomy = get_taxonomy( $slug );
			$this->log->error_log($taxonomy, "$loc: taxonomy:");

			$selected = '';
			// if the current page is already filtered, get the selected term slug
			$selected = isset( $_REQUEST[ $slug ] ) ? $_REQUEST[ $slug ] : '';
			// render a dropdown for this taxonomy's terms
			wp_dropdown_categories( array(
				'show_option_all' =>  $taxonomy->labels->all_items,
				'taxonomy'        =>  $slug,
				'name'            =>  $slug,
				'orderby'         =>  'name',
				'value_field'     =>  'slug',
				'selected'        =>  $selected,
				'hierarchical'    =>  true,
			) );
		}
	}

	/**
	 * Add a filter dropdown in the Event admin page
	 */
	public function genderTypeFilter($post_type)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;

		if ($post_type === self::CUSTOM_POST_TYPE) {
			$this->log->error_log("$loc using post_type: $post_type");
			$gtypes = GenderType::AllTypes();
			if (empty($gtypes)) return;

			$selected = -1;
			if (isset($_GET['gender_type']) && !empty($_GET['gender_type'])) {
				$selected = $_GET['gender_type'];
			}
			$options[] = sprintf('<option value="-1">%1$s</option>', __('All Gender Types', TennisEvents::TEXT_DOMAIN));
			foreach ($gtypes as $key => $val) {
				if ($key === $selected) {
					$options[] = sprintf('<option value="%1$s" selected>%2$s</option>', esc_attr($key), $val);
				} else {
					$options[] = sprintf('<option value="%1$s">%2$s</option>', esc_attr($key), $val);
				}
			} 

			/** Output the dropdown menu */
			echo '<select class="" id="gender_type" name="gender_type">';
			echo join('\n', $options);
			echo '</select>';
		}
	}

	/**
	 * Modify the WP_QUERY using the value from the request query string
	 */
	public function genderTypeParseFilter($query) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc");

		global $pagenow;
		$current_page = isset($_GET['post_type']) ? $_GET['post_type'] : '';

		if (
			is_admin()
			&& self::CUSTOM_POST_TYPE == $current_page
			&& 'edit.php' == $pagenow
			&& isset($_GET['gender_type'])
			&& $_GET['gender_type'] != ''
			&& $_GET['gender_type'] != '-1'
		) {
			$query->query_vars['meta_key'] = self::GENDER_TYPE_META_KEY;
			$query->query_vars['meta_value'] = $_GET['gender_type'];
			$query->query_vars['meta_compare'] = '=';
		}
	}
		
	/**
	 * Add a filter dropdown in the Event admin page
	 * to permit filtering out events except children of selected parent
	 */
	public function parentEventFilter($post_type)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;

		if ($post_type === self::CUSTOM_POST_TYPE) {
			$this->log->error_log("$loc using post_type: $post_type");
			
			$parentEvents = Event::getAllParentEvents();
			if (empty($parentEvents)) return;

			$selected = -1;
			if (isset($_GET['parent_event_id']) && !empty($_GET['parent_event_id'])) {
				$selected = $_GET['parent_event_id'];
			}
			$options[] = sprintf('<option value="-1">%1$s</option>', __('All Events', TennisEvents::TEXT_DOMAIN));
			foreach ($parentEvents as $evt) {
				$key = $evt->getID();
				$val = str_replace("\'","'",$evt->getName());
				if ($evt->getID() === $selected) {
					$options[] = sprintf('<option value="%1$s" selected>%2$s</option>', esc_attr($key), $val);
				} else {
					$options[] = sprintf('<option value="%1$s">%2$s</option>', esc_attr($key), $val);
				}
			} 

			/** Output the dropdown menu */
			echo '<select class="" id="parent_event_id" name="parent_event_id">';
			echo join('\n', $options);
			echo '</select>';
		}
	}

	/**
	 * Modify the WP_QUERY using the value from the request query string
	 * so that only the children of the given parent are shown
	 */
	public function parentEventParseFilter($query) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc");

		global $pagenow;
		$current_page = isset($_GET['post_type']) ? $_GET['post_type'] : '';

		if (
			is_admin()
			&& self::CUSTOM_POST_TYPE == $current_page
			&& 'edit.php' == $pagenow
			&& isset($_GET['parent_event_id'])
			&& $_GET['parent_event_id'] != ''
			&& $_GET['parent_event_id'] != '-1'
		) {
			$evtId = (int)$_GET['parent_event_id'];
			$cptId = Event::getExtEventRefByEventId( $evtId );
			$query->query_vars['meta_key'] = self::PARENT_EVENT_META_KEY;
			$query->query_vars['meta_value'] = $cptId;
			$query->query_vars['meta_compare'] = '=';
		}
	}

	// Populate the Tennis Event columns with values
	public function getColumnValues($column_name, $postID) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc --> $column_name, $postID");

		if ($column_name === 'event_type') {
			$eventType = get_post_meta($postID, self::EVENT_TYPE_META_KEY, TRUE);
			if (!empty($eventType)) {
				echo EventType::AllTypes()[$eventType];
			} else {
				echo "";
			}
		} elseif ($column_name === 'match_type') {
			$matchType = get_post_meta($postID, self::MATCH_TYPE_META_KEY, TRUE);
			if (!empty($matchType)) {
				echo MatchType::AllTypes()[$matchType];
			} else {
				echo "";
			}
		} elseif ($column_name === 'parent_event') {
			$eventParentId = get_post_meta($postID, self::PARENT_EVENT_META_KEY, TRUE);
			if (!empty($eventParentId)) {
				$tecpt = get_post($eventParentId);
				$name = "";
				if (!is_null($tecpt)) {
					$name = $tecpt->post_title;
				}
				echo "$name";
			} else {
				echo "";
			}
		} elseif ($column_name === 'event_format') {
			$eventFormat = get_post_meta($postID, self::EVENT_FORMAT_META_KEY, TRUE);
			if (!empty($eventFormat) && Format::isValid($eventFormat)) {
				echo Format::AllFormats()[$eventFormat];
			} else {
				echo "";
			}
		} elseif ($column_name === 'gender_type') {
			$genderType = get_post_meta($postID, self::GENDER_TYPE_META_KEY, TRUE);
			if (!empty($genderType)) {
				echo GenderType::AllTypes()[$genderType];
			} else {
				echo "";
			}
		} elseif ($column_name === 'score_type') {
			$scoreType = get_post_meta($postID, self::SCORE_TYPE_META_KEY, TRUE);
			if (!empty($scoreType)) {
				if (ScoreType::get_instance()->isValid($scoreType)) {
					//echo $scoreType;
					echo ScoreType::get_instance()->getRuleDescriptions()[$scoreType];
				} else {
					echo "";
				}
			} else {
				echo "";
			}
		} elseif( $column_name === 'num_brackets' ) {
			$numBrackets = get_post_meta($postID, self::NUMBER_OF_BRACKETS_KEY, TRUE);
			if (!empty($numBrackets)) {
				echo $numBrackets;
			} else {
				echo '';
			}
		} elseif( $column_name === 'age_min' ) {			
			$ageMin = get_post_meta($postID, self::AGE_MIN_META_KEY, TRUE);
			if (!empty($ageMin)) {
				echo $ageMin;
			} else {
				echo '';
			}
		} elseif( $column_name === 'age_max' ) {		
			$ageMax = get_post_meta($postID, self::AGE_MAX_META_KEY, TRUE);
			if (!empty($ageMax)) {
				echo $ageMax;
			} else {
				echo '';
			}
		} elseif ($column_name === 'signup_by_date') {
			$signupBy = get_post_meta($postID, self::SIGNUP_BY_DATE_META_KEY, TRUE);
			if (!empty($signupBy)) {
				echo $signupBy;
			} else {
				echo '';
			}
		} elseif ($column_name === 'start_date') {
			$start = get_post_meta($postID, self::START_DATE_META_KEY, TRUE);
			if (!empty($start)) {
				echo $start;
			} else {
				echo '';
			}
		} elseif ($column_name === 'tennis_season') {
			$start = get_post_meta($postID, self::START_DATE_META_KEY, TRUE);
			if (!empty($start)) {
				$season = (new DateTime($start))->format('Y');
				echo $season;
			} else {
				$season = (new DateTime())->format('Y');
				echo $season;
			}
		} elseif ($column_name === 'end_date') {
			$end = get_post_meta($postID, self::END_DATE_META_KEY, TRUE);
			if (!empty($end)) {
				echo $end;
			} else {
				echo '';
			}
		}
	}

	public function customTaxonomy() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		//hierarchical
		$labels = array(
			'name' => 'Tennis Event Categories'
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

		$args = array(
			'hierarchical' => true
			, 'labels' => $labels
			, 'show_ui' => true
			, 'show_admin_column' => true
			, 'query_var' => true
			, 'rewrite' => array('slug' => self::CUSTOM_POST_TYPE_TAX)
		);

		register_taxonomy(
			self::CUSTOM_POST_TYPE_TAX,
			array(self::CUSTOM_POST_TYPE),
			$args
		);

		//NOT hierarchical
		register_taxonomy(
			self::CUSTOM_POST_TYPE_TAG,
			self::CUSTOM_POST_TYPE,
			array(
				'label' => 'Tennis Event Tags'
				, 'rewrite' => array('slug' => 'tenniseventtag')
				, 'hierarchical' => false
			)
		);
	}
	
	/**
	 * Adds the 'reset' bulk action to the bulk action drop down
	 */
	public function addResetBulkAction( $bulk_actions ) {
		$bulk_actions['tennis-event-reset'] = __('Reset the Draw', TennisEvents::TEXT_DOMAIN );
		return $bulk_actions;
	}

	/**
	 * Perform the 'reset' bulk action
	 */
	public function handleBulkReset( $redirect_url, $action, $post_ids ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($post_ids, "$loc($redirect_url, $action)");

		$numEvts = 0;
		$numBrackets = 0;
		if( $action === 'tennis-event-reset' ) {
			foreach( $post_ids as $post_id ) {
				$evt = $this->getEventByExtRef( $post_id );
				++$numEvts;
				foreach( $evt->getBrackets() as $bracket ) {
					$bracket->removeAllMatches();
					++$numBrackets;
				}
				$evt->save();
			}
		}
		$this->log->error_log("$loc: reset $numEvts Events; $numBrackets Brackets");
		return $redirect_url;
	}

	/* 
	================================================
		Meta Boxes
	================================================
	*/
	public function metaBoxes( $post_type )	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);
		if ($post_type !== self::CUSTOM_POST_TYPE) return;

		add_meta_box(
			'tennis_event_type_meta_box' //id
			,'Event Type' //Title
			,array($this, 'eventTypeCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side, advanced
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_parent_event_meta_box' //id
			,'Parent Event' //Title
			,array($this, 'parentEventCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'side' //context: normal, side
			,'low' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_event_format_meta_box',
			'Format' //Title
			,array($this, 'eventFormatCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_match_type_meta_box',
			'Match Type' //Title
			,array($this, 'matchTypeCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);
		
		add_meta_box(
			'tennis_gender_type_meta_box',
			'Gender Type' //Title
			,array($this, 'genderTypeCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_score_type_meta_box',
			'Score Type' //Title
			,array($this, 'scoreTypeCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_number_brackets_meta_box'
			,'Number of Brackets' //Title
			,array($this, 'numberBracketsCallback') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);
		
		add_meta_box(
			'tennis_age_min_meta_box',
			'Minimum Age' //Title
			,array($this, 'ageMinCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen, cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'tennis_age_max_meta_box',
			'Maximum Age' //Title
			,array($this, 'ageMaxCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen, cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'event_signupby_meta_box',
			'Signup By' //Title
			,array($this, 'signupByCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'event_start_meta_box',
			'Start Date' //Title
			,array($this, 'startDateCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);

		add_meta_box(
			'event_end_meta_box',
			'End Date' //Title
			,array($this, 'endDateCallBack') //Callback
			,self::CUSTOM_POST_TYPE //mixed: screen cpt name or ???
			,'normal' //context: normal, side
			,'high' // priority: low, high, default
			// array callback args
		);
	}

	/**
	 * Event Type callback
	 */
	public function eventTypeCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		wp_nonce_field('eventTypeSave' //action
			,'tennis_event_type_nonce'
		);

		$actual = get_post_meta( $post->ID, self::EVENT_TYPE_META_KEY, true );
		$this->log->error_log( "$loc --> actual='$actual'" );
		if ($this->isNewEvent()) {
			if ( !@$actual ) $actual = EventType::TOURNAMENT;
			$parentId = false;
		} else {
			$parentId = get_post_meta( $post->ID, self::PARENT_EVENT_META_KEY, true );
		}

		if ( empty( $parentId ) ) {
			//Now echo the html desired
			echo '<select name="tennis_event_type_field">';
			echo '<option value="">Select Event Type...</option>';
			foreach (EventType::AllTypes() as $key => $val) {
				$disp = esc_attr($val);
				$value = esc_attr($key);
				$sel = '';
				if ($actual === $value) $sel = 'selected';
				echo "<option value='$value' $sel>$disp</option>";
			}
			echo '</select>';
		} else {
			echo "<!-- No event type for child event {$post->ID}  -->";
		}
	}

	/**
	 * Parent Event callback
	 */
	public function parentEventCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field( 'parentEventSave' //action
						,'tennis_parent_event_nonce'
					);

		$actual = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);
		if (!isset($actual)) $actual = "";
		$this->log->error_log("$loc --> actual='$actual'");

		//Now echo the html desired
		echo '<select name="tennis_parent_event_field">';
		echo '<option value="-1">Remove Parent...</option>';
		foreach ($this->parentEvents($post) as $candidate) {
			$disp = esc_attr($candidate->post_title);
			$value = esc_attr($candidate->ID);
			$sel = '';
			if ($actual === $value) $sel = 'selected';
			echo "<option value='$value' $sel>$disp</option>";
		}
		echo '</select>';
		echo "<input type='hidden' name='currentExtRefId' value='$actual'/>";
	}

	/**
	 * Retrieve candidate parent events for the given post
	 * @param $post A tennis event cpt
	 */
	private function parentEvents($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		$result = [];
		if ($post->post_type === self::CUSTOM_POST_TYPE) {
			$args = array(
				'numberposts'  => -1,
				'category'   => 0,
				'orderby'    => 'title',
				'order'      => 'ASC',
				'include'    => array(),
				'exclude'    => array($post->ID),
				//   'meta_key'   => self::PARENT_EVENT_META_KEY,
				//   'meta_value' => '',
				'post_type'  => self::CUSTOM_POST_TYPE,
				'suppress_filters' => true
			);
			foreach (get_posts($args) as $p) {
				$parEvtId = get_post_meta($p->ID, self::PARENT_EVENT_META_KEY, true);
				if (!empty($parEvtId)) continue;
				//Only want root events (i.e. events without parents)
				$result[] = $p;
			}
		}
		return $result;
	}

	/**
	 * Event Format callback
	 */
	public function eventFormatCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('eventFormatSave' //action
						,'tennis_event_format_nonce'
					);

		$actual = get_post_meta($post->ID, self::EVENT_FORMAT_META_KEY, true);
		$this->log->error_log("$loc --> actual='$actual'");

		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);

		if (!empty($parentId)) {
			//Now echo the html desired
			echo '<select name="tennis_event_format_field">';
			echo '<option value="">Select Format...</option>';
			$formats = Format::AllFormats();
			foreach ($formats as $key => $val) {
				$disp = esc_attr($val);
				$value = esc_attr($key);
				$sel = '';
				if ($actual === $key) $sel = 'selected';
				echo "<option value='$value' $sel>$disp</option>";
			}
			echo '</select>';
		} else {
			echo "<!-- No format for root event {$post->ID} -->";
		}
	}

	/**
	 * Match Type callback
	 */
	public function matchTypeCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field(
			'matchTypeSave' //action
			,
			'tennis_match_type_nonce'
		);

		$actual = get_post_meta($post->ID, self::MATCH_TYPE_META_KEY, true);
		$this->log->error_log("$loc --> actual=$actual");

		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);
		

		if (!empty($parentId)) {
			//Now echo the html desired
			echo '<select name="tennis_match_type_field">';
			echo '<option value="">Select Match Type...</option>';
			foreach (MatchType::AllTypes() as $key => $val) {
				$disp = esc_attr($val);
				$value = esc_attr($key);
				$sel = '';
				if ($actual === $value) $sel = 'selected';
				echo "<option value='$value' $sel>$disp</option>";
			}
			echo '</select>';
		} else {
			echo "<!-- No match type for root event {$post->ID} -->";
		}
	}
	
	/**
	 * Match Type callback
	 */
	public function genderTypeCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field(
			'genderTypeSave' //action
			,
			'tennis_gender_type_nonce'
		);

		$actual = get_post_meta($post->ID, self::GENDER_TYPE_META_KEY, true);
		$this->log->error_log("$loc --> actual=$actual");

		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);
		

		if (!empty($parentId)) {
			//Now echo the html desired
			echo '<select name="tennis_gender_type_field">';
			echo '<option value="">Select Gender Type...</option>';
			foreach (GenderType::AllTypes() as $key => $val) {
				$disp = esc_attr($val);
				$value = esc_attr($key);
				$sel = '';
				if ($actual === $value) $sel = 'selected';
				echo "<option value='$value' $sel>$disp</option>";
			}
			echo '</select>';
		} else {
			echo "<!-- No gender type for root event {$post->ID} -->";
		}
	}

	/**
	 * Score Type callback
	 */
	public function scoreTypeCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('scoreTypeSave' //action
					,'tennis_score_type_nonce');

		$actual = get_post_meta($post->ID, self::SCORE_TYPE_META_KEY, true);

		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);

		if (!empty($parentId)) {
			error_clear_last();
			$descriptions = ScoreType::get_instance()->getRuleDescriptions();
			//Now echo the html desired
			echo '<select name="tennis_score_type_field">';
			echo '<option value="">Select Score Type...</option>';
			foreach ($descriptions as $key => $desc) {
				$disp = esc_attr($desc);
				$value = esc_attr($key);
				$sel = '';
				if ($actual === $key) $sel = 'selected';
				echo "<option value='$value' $sel>$disp</option>";
			}
			echo '</select>';
		} else {
			echo "<!-- No score type for root event {$post->ID} -->";
		}
	}

	public function numberBracketsCallback( $post ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('numberBracketsSave' //action
					,'tennis_number_brackets_nonce');
					
		$actual = get_post_meta($post->ID, self::NUMBER_OF_BRACKETS_KEY, true);
		if (!@$actual) $actual = '2';
		$this->log->error_log("$loc --> actual=$actual");
		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);

		if ( !empty($parentId) ) {
			//Now echo the html desired
			$markup = sprintf('<input type="number" name="tennis_number_brackets_field" value="%s">'
							, $actual
							);
		}
		else {
			$markup = "<!-- no number of brackets for root event -->";
		}

		echo $markup;
	}
	
	/**
	 * Minimum age callback
	 */
	public function ageMinCallBack($post)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('ageMinSave' //action
						,'tennis_age_min_by_nonce'
					);

		$actual = get_post_meta($post->ID, self::AGE_MIN_META_KEY, true);
		if (!@$actual) $actual = '1';
		$this->log->error_log("$loc --> actual=$actual");
		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);

		if (!empty($parentId)) {
			//Now echo the html desired
			$markup = sprintf('<input type="number" min="1" max="99" name="tennis_age_min_field" value="%s">'
							, $actual
							);
		}
		else {
			$markup = "<!-- no min age for root event -->";
		}

		echo $markup;
	}
		
	/**
	 * Minimum age callback
	 */
	public function ageMaxCallBack($post)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('ageMaxSave' //action
						,'tennis_age_max_by_nonce'
					);

		$actual = get_post_meta($post->ID, self::AGE_MAX_META_KEY, true);
		if (!@$actual) $actual = '99';
		$this->log->error_log("$loc --> actual=$actual");
		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);


		if (!empty($parentId)) {
			//Now echo the html desired
			$markup = sprintf('<input type="number" min="1" max="99" name="tennis_age_max_field" value="%s">'
							, $actual
							);
		}
		else {
			$markup = "<!-- no max age for root event -->";
		}

		echo $markup;
	}


	/**
	 * Signup By callback
	 */
	public function signupByCallBack($post)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field('signupBySave' //action
						,'tennis_signup_by_nonce'
					);

		$actual = get_post_meta($post->ID, self::SIGNUP_BY_DATE_META_KEY, true);
		if (!@$actual) $actual = '';
		$this->log->error_log("$loc --> actual=$actual");
		$parentId = get_post_meta($post->ID, self::PARENT_EVENT_META_KEY, true);


		if (!empty($parentId)) {
		//Now echo the html desired
		$markup = sprintf('<input type="date" name="tennis_signup_by_field" value="%s">'
						, $actual
						);
		}
		else {
			$markup = "<!-- no signup date for root event -->";
		}

		echo $markup;
	}

	/**
	 * Start Date callback
	 */
	public function startDateCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field(
			'startDateSave' //action
			,
			'tennis_start_date_nonce'
		);

		$actual = get_post_meta($post->ID, self::START_DATE_META_KEY, true);
		if (!@$actual) $actual = '';
		$this->log->error_log("$loc --> actual='$actual'");
		//Now echo the html desired
		$markup = sprintf(
			'<input type="date" name="tennis_start_date_field" value="%s">',
			$actual
		);

		echo $markup;
	}


	/**
	 * End Date callback
	 */
	public function endDateCallBack($post) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log($loc);

		wp_nonce_field(
			'endDateSave' //action
			,
			'tennis_end_date_nonce'
		);

		$actual = get_post_meta($post->ID, self::END_DATE_META_KEY, true);
		if (!@$actual) $actual = '';
		$this->log->error_log("$loc --> actual=$actual");
		//Now echo the html desired
		$markup = sprintf(
			'<input type="date" name="tennis_end_date_field" value="%s">',
			$actual
		);

		echo $markup;
	}

	/**
	 * Update the tennis database
	 */
	public function updateTennisDB($post_id) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("{$loc}($post_id)");

		if (empty($_POST)) return;

		$this->log->error_log($_POST, "$loc: _POST...");

		if (!isset($_POST['tennis_end_date_nonce'])) {
			$this->log->error_log("$loc --> no end date nonce");
			return; //could be quick edit
		}

		if (!wp_verify_nonce($_POST['tennis_end_date_nonce'], 'endDateSave')) {
			$this->log->error_log("$loc --> bad end date nonce");
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}

		$errorsFound = 0;
		$evtName = "";
		$post = get_post( $post_id );
		if ($post->post_type !== self::CUSTOM_POST_TYPE) {
			$this->log->error_log("$loc --> not tennis event custom post type!");
			return;
		}


		if (isset($_POST['post_title'])) {
			$evtName = sanitize_text_field($_POST['post_title']);
		} else {
			$evtName = $post->post_title ?? "";
		}

		if ( empty( $evtName ) ) {
			$this->log->error_log("$loc - title must be provided.");
			$this->add_error(__('Title must be provided', TennisEvents::TEXT_DOMAIN));
			return;
		}

		$homeClubId = esc_attr(get_option('gw_tennis_home_club', 0));
		if (0 === $homeClubId) {
			$this->log->error_log("$loc - Home club id is not set.");
			$this->add_error( __('Home club is not set',TennisEvents::TEXT_DOMAIN ) );
			return;
		}

		$parentPostId = 0;
		$parentEvent = $parentPost = null;
		if (isset($_POST['tennis_parent_event_field'])) {
			$parentPostId = (int)sanitize_text_field($_POST['tennis_parent_event_field']);
		}
		
		//Test to see if event has matches
		// because we cannot edit an event that already contains matches
		$tennisEvt = $this->getEventByExtRef($post_id);
		if( !empty( $tennisEvt ) && !empty($parentPostId) ) {
			foreach( $tennisEvt->getBrackets() as $bracket ) {
				if( count( $bracket->getMatches()) > 0 ) {				
					$this->log->error_log("$loc - cannot edit an event that already has matches.");
					$this->add_error(__('Cannot edit event that has matches initiated.', TennisEvents::TEXT_DOMAIN));
					return;
				}
			}
		}

		$parentEventType = '';
		if ( !empty($parentPostId) && $parentPostId > 0 ) {
			$parentPost = get_post($parentPostId);
			if (is_null($parentPost)) {
				delete_post_meta( $post_id, self::PARENT_EVENT_META_KEY );
				$this->add_error( __('No such parent custom post type event', TennisEvents::TEXT_DOMAIN ) );
				$this->log->error_log("No such parent custom post type event: '{$parentPostId}'");
			}
			$this->log->error_log($parentPostId, "$loc: Parent post id");

			$parentEvent = $this->getEventByExtRef($parentPostId);
			if (is_null($parentEvent)) {
				delete_post_meta($post_id, self::PARENT_EVENT_META_KEY);
				$this->add_error( __( 'No such parent tennis event', TennisEvents::TEXT_DOMAIN ) );
				$this->log->error_log("No such parent tennis event with external reference: '{$parentPostId}'");
			}
			else {
				$parentEventType = $parentEvent->getEventType();
				update_post_meta($post_id, self::PARENT_EVENT_META_KEY, $parentPostId);
			}
		} else {
			delete_post_meta($post_id, self::PARENT_EVENT_META_KEY);
		}
		
		$eventType = '';
		if (isset($_POST['tennis_event_type_field'])) {
			$eventType = sanitize_text_field($_POST['tennis_event_type_field']);
		}
		if (!empty($eventType)) {
			if( !empty($parentEventType) ) $eventType = $parentEventType;
			update_post_meta($post_id, self::EVENT_TYPE_META_KEY, $eventType);
		} else {
			delete_post_meta($post_id, self::EVENT_TYPE_META_KEY);
		}

		//TODO: Rationalize Format with ScoreType
		// If Format is Elimination then ScoreType cannot be Points1, Points2, etc.
		$eventFormat = '';
		if (isset($_POST['tennis_event_format_field'])) {
			$eventFormat = sanitize_text_field($_POST['tennis_event_format_field']);
		}
		if (!empty($eventFormat)) {
			//Ladders cannot be elimination events
			if( $parentEventType === EventType::LADDER ) $eventFormat=Format::ROUNDROBIN;
			update_post_meta($post_id, self::EVENT_FORMAT_META_KEY, $eventFormat);
		} else {
			if( !is_null($parentEvent) ) {
				$this->add_error(__('Event format is required', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			delete_post_meta($post_id, self::EVENT_FORMAT_META_KEY);
		}

		$matchType = '';
		if (isset($_POST['tennis_match_type_field'])) {
			$matchType = sanitize_text_field($_POST['tennis_match_type_field']);
		}
		if (!empty($matchType)) {
			update_post_meta( $post_id, self::MATCH_TYPE_META_KEY, $matchType );
		} else {
			if( !is_null($parentEvent) ) {
				$this->add_error(__('Match type is required', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			delete_post_meta( $post_id, self::MATCH_TYPE_META_KEY );
		}

		$genderType = '';
		if (isset($_POST['tennis_gender_type_field'])) {
			$genderType = sanitize_text_field($_POST['tennis_gender_type_field']);
		}
		if (!empty($genderType)) {
			update_post_meta( $post_id, self::GENDER_TYPE_META_KEY, $genderType );
		} else {
			if( !is_null($parentEvent) ) {
				$this->add_error(__('Gender type is required', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			delete_post_meta( $post_id, self::GENDER_TYPE_META_KEY );
		}

		$scoreType = '';
		if (isset($_POST['tennis_score_type_field'])) {
			$scoreType = sanitize_text_field($_POST['tennis_score_type_field']);
		}
		if (!empty( $scoreType ) ) {
			update_post_meta( $post_id, self::SCORE_TYPE_META_KEY, $scoreType );
		} else {
			if( !is_null($parentEvent) ) {
				$this->add_error(__('Score type is required', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			delete_post_meta( $post_id, self::SCORE_TYPE_META_KEY );
		}

		//Number of brackets
		$numBrackets = $_POST['tennis_number_brackets_field'] ?? 1;
		update_post_meta( $post_id, self::NUMBER_OF_BRACKETS_KEY, $numBrackets );

		//Min and Max ages
		$ageMin = 0;
		if( (isset($_POST['tennis_age_min_field']))) {
			$ageMin = $_POST['tennis_age_min_field'];
		}
		$ageMax = 0;
		if( (isset($_POST['tennis_age_max_field']))) {
			$ageMax = $_POST['tennis_age_max_field'];
		}
		if( ($ageMin > 0 && $ageMin > 0) && ($ageMin < $ageMax) ) {
			update_post_meta( $post_id, self::AGE_MIN_META_KEY, $ageMin );
			update_post_meta( $post_id, self::AGE_MAX_META_KEY, $ageMax );
		}

		//Posted SignupBy, Start and End Dates
		$signupBy = "";
		if(isset($_POST['tennis_signup_by_field'])) {
			$signupBy = $_POST['tennis_signup_by_field'];
		}
		$startDate = "";
		if(isset($_POST['tennis_start_date_field'])) {
			$startDate = $_POST['tennis_start_date_field'];
		}
		$endDate = "";
		if(isset($_POST['tennis_end_date_field'])) {
			$endDate = $_POST['tennis_end_date_field'];
		}
		$this->log->error_log("$loc: signupBy='{$signupBy}'; start='{$startDate}'; end='{$endDate}'");

		//Validate SignupBy date
		$test = $this->getDateValue($signupBy);
		if ( is_wp_error( $test ) ) {
			$this->log->error_log("$loc: Error in signup date: " . $test->get_error_message());
			$signupBy = '';
			if( !is_null( $parentEvent) ) {
				$this->add_error( __('Invalid signup date', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			$compareSign = null;
		} else {
			$signupBy = $this->getDateStr( $test );
			$compareSign = $test;
		}

		//Validate Start Date
		$test = $this->getDateValue($startDate);
		if ( is_wp_error( $test ) ) {
			$this->log->error_log("$loc: Error in start date: " . $test->get_error_message());
			$startDate = '';
			if( !is_null( $parentEvent) ) {
				$this->add_error( __('Invalid start date', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
			$compareStart = null;
		} else {
			$startDate = $this->getDateStr($test);
			$compareStart = $test;
		}

		//Validate End Date
		$test = $this->getDateValue( $endDate );
		if ( is_wp_error( $test ) ) {
			$this->log->error_log("$loc: Error in end date: " . $test->get_error_message());
			$endDate = '';
			$compareEnd = null;
			if( !is_null( $parentEvent) ) {
				$this->add_error( __('Invalid end date', TennisEvents::TEXT_DOMAIN ) );
				++$errorsFound;
			}
		} else {
			$endDate = $this->getDateStr( $test );
			$compareEnd = $test;
		}

		//Now compare the order of the dates
		if( !is_null( $compareSign ) && !is_null( $compareStart ) ) {
			$diff = $compareSign->diff( $compareStart );
			if( $diff->invert === 1 || $diff->days < 2 ) {
				$this->add_error(__( 'Signup date must be at least 3 days earlier than start date', TennisEvents::TEXT_DOMAIN ) );
				$signupBy = '';
				++$errorsFound;
			}
		}		

		if( !is_null( $compareStart ) && !is_null( $compareEnd ) ) {
			if( $compareStart >= $compareEnd ) {
				$this->add_error(__( 'Start date must be earlier than end date', TennisEvents::TEXT_DOMAIN ) );
				$startDate = '';
				++$errorsFound;
			}
		}

		//Update meta signupBy
		if (!empty( $signupBy ) ) {
			update_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY, $signupBy );
		} else {
			delete_post_meta( $post_id, self::SIGNUP_BY_DATE_META_KEY );
		}

		//Update meta Start Date
		if ( !empty( $startDate ) ) {
			update_post_meta( $post_id, self::START_DATE_META_KEY, $startDate );
		} else {
			delete_post_meta( $post_id, self::START_DATE_META_KEY );
		}

		//Update meta End Date
		if (!empty($endDate)) {
			update_post_meta($post_id, self::END_DATE_META_KEY, $endDate);
		} else {
			delete_post_meta($post_id, self::END_DATE_META_KEY);
		}


		//Event stuff
		$club = Club::get( $homeClubId );
		if( empty($club) ) {				
			$this->add_error(__( 'Home club is not set.', TennisEvents::TEXT_DOMAIN ) );
			++$errorsFound;
		}
		
		//Don't bother with setting up the Tennis Event
		// as errors are present in the input
		if( $errorsFound > 0 ) {			
			$this->add_error(__( 'Errors were found!', TennisEvents::TEXT_DOMAIN ) );
			return;
		}

		try {
			$event = $this->getEventByExtRef( $post_id );
			if (is_null( $event ) ) {
				$event = new Event( $evtName );
				$this->log->error_log("{$loc}: Created new event with name {$evtName}");
			}
			else {
				$event->setName( $evtName );
				$this->log->error_log("{$loc}: Retrieved existing event with name {$evtName}");
			}

			$event->addClub($club);

			//Set the parent event of the Event before setting other props
			$event->setParent($parentEvent);

			//Set Event external references
			$event->addExternalRef((string)$post_id);

			//Set other Event props
			if( !$event->setEventType($eventType) ) {
				delete_post_meta($post_id, self::EVENT_TYPE_META_KEY);
			}

			if( !$event->setMatchType($matchType) ) {
				delete_post_meta($post_id, self::MATCH_TYPE_META_KEY);
			}

			if( !$event->setGenderType($genderType) ) {
				delete_post_meta($post_id, self::GENDER_TYPE_META_KEY);
			}

			if( !$event->setFormat($eventFormat) ) {
				delete_post_meta($post_id, self::EVENT_FORMAT_META_KEY);
			}

			if( !$event->setScoreType($scoreType) ) {
				delete_post_meta($post_id, self::SCORE_TYPE_META_KEY);
			}

			if( !$event->setNumberOfBrackets($numBrackets) ) {
				delete_post_meta($post_id, self::NUMBER_OF_BRACKETS_KEY);
			}

			$event->setSignupBy($signupBy);
			$event->setStartDate($startDate);
			$event->setEndDate($endDate);
			$event->save();
		}
		catch(Exception $ex ) {
			$mess = __("Could not save event because: {$ex->getMessage()}", TennisEvents::TEXT_DOMAIN);
			$this->add_error( $mess );
		}
	}

	/**
	 * Delete the post id in the external reference table
	 * as well as the Tennis Event in the event table in the tennis schema
	 * @param int $post_id
	 */
	public function deleteTennisDB(int $post_id) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='$post_id'");

		$post = get_post($post_id);
		$evt = $this->getEventByExtRef($post_id);
		if (isset($post) && $post->post_type === self::CUSTOM_POST_TYPE) {
			//First delete any references to this post as a parent event
			$this->deleteParentReferences($post_id);
			//Second delete the Event which cascades to child events in Tennis db
			if (!is_null($evt)) $evt->delete();
		}
	}

	/**
	 * Delete all references to the given parent post id
	 * @param int $parent_id The id of the parent post (i.e. event custom post)
	 */
	private function deleteParentReferences($parent_id)	{
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: parent_id=$parent_id");

		$args = array(
			'numberposts'  => -1,
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
			'suppress_filters' => true
		);
		$numDel = 0;
		$cpts = get_posts($args);
		$this->log->error_log($cpts, "$loc: posts with parent: $parent_id");
		foreach ($cpts as $p) {
			//delete_post_meta( $p->ID, self::PARENT_EVENT_META_KEY );
			if (wp_delete_post($p->ID, true)) ++$numDel;
		}
		$this->log->error_log("$loc: deleted={$numDel} references to parent '{$parent_id}'");
	}

	/**
	 * Get the event using external reference
	 * @param $postId The id of the event custom post 
	 * @return Event if found null otherwise
	 */
	private function getEventByExtRef($postId) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: postId='$postId'");

		$result = null;
		$events = Event::getEventByExtRef($postId);
		if (is_array($events)) {
			$result = $events[0];
		} else {
			$result = $events;
		}
		return $result;
	}

	/**
	 * Finds an Event using the name of the event
	 * @return Event if found null otherwise
	 * @param $name The name of the event
	 */
	private function getEventByName(string $name) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: name='$name'");

		$candidates = Event::search($name);
		$event = null;
		$test = strtolower(str_replace(' ', '', $name));
		foreach ($candidates as $evt) {
			if (strtolower(str_replace(' ', '', $evt->getName())) === $test) {
				$event = $evt;
				break;
			}
		}
		return $event;
	}

	/**
	 * Get the date value from date string
	 * @param string $testDate
	 * @return mixed Datetime object if successful; Error object otherwise
	 */
	public function getDateValue(string $testDate) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: $testDate");

		$result = new WP_Error('unknown error');

		$test = DateTime::createFromFormat('Y/m/d|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y/n/j|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y-m-d|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y-n-j|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('d-m-Y|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('d/m/Y|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('m-d-Y|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('m/d/Y|', $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::ATOM, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::ISO8601, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::W3C, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::RFC822, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::RFC3339, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::RSS, $testDate);
		if (false === $test) {
			$mess = implode(';', DateTime::getLastErrors()['errors']);
			$result = new WP_Error($mess);
		} else {
			$result = $test;
		}

		return $result;
	}

	/**
	 * Test if a date in string form is valid
	 * @param string $testDate
	 * @return bool True if date is valid; false otherwise
	 */
	public function isDateValid(string $testDate) {
		$result = false;
		if (empty($testDate)) return $result;
		//DateTimeInterface::ATOM
		//DateTimeInterface::ISO8601
		//DateTimeInterface::W3C

		$test = DateTime::createFromFormat('Y/m/d', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y/n/j', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y-m-d', $testDate);
		if (false === $test) $test = DateTime::createFromFormat('Y-n-j', $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::ATOM, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::ISO8601, $testDate);
		if (false === $test) $test = DateTime::createFromFormat(DateTimeInterface::W3C, $testDate);

		if (false !== $test) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Return a string value for a DateTime
	 * @param DateTime $date
	 * @return string representation in Y-m-d format
	 */
	private function getDateStr(DateTime $date)	{
		static $datetimeformat = "Y-m-d H:i:s";
		static $storageformat = "Y-m-d";

		return $date->format($storageformat);
	}

	/**
	 * Detect if we are creating a new Tennis Event CPT
	 */
	private function isNewEvent() {
		global $pagenow;
		global $post_type;
		return $pagenow === 'post-new.php' && $post_type === self::CUSTOM_POST_TYPE;
	}

	/**
	 * Detect if we are editing Tennis Event CPT
	 */
	private function isEditEvent() {
		global $pagenow;
		global $post_type;
		$data = $_GET ?? $_POST;			
		// echo "<pre>";
		// print_r($_GET);
		// echo "</pre>";
		$action = array_key_exists('action', $data ) ? $data['action'] : '';
		if( empty( $action ) ) return false;
		return $pagenow === 'post.php' && $action === 'edit' && $post_type === self::CUSTOM_POST_TYPE;
	}
	
	/**
	 * Display errors on the edit page screen
	 * Only works with classical editor
	 * TODO: Investiate and implement Block editor api ... not really needed!
	 */
	public function handle_errors() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		$admin_page = get_current_screen();
		if( !$admin_page->base === 'post' || ! ( $this->isEditEvent() || $this->isNewEvent() ) ) return;
		
		//If there are no errors, then exit the function
		if( empty( $errors = get_transient( self::TENNIS_EVENT_ERROR_TRANSIENT_KEY ) ) ) {
			return;
		}
		$this->log->error_log( $errors, "$loc: errors ..." );

		foreach( $errors as $error ) {
			$markup = sprintf('<div id="tennis-event-error-message" class="notice notice-error is-dismissible"><p>%s</p></div>'
			 				, $error);
			$this->log->error_log("$loc: markup='{$markup}'");
			echo $markup;
		}
			
		$this->admin_notice_messages = array();
		//Clear and the transient and unhook any other notices so we don’t see duplicate messages
		delete_transient( self::TENNIS_EVENT_ERROR_TRANSIENT_KEY );
		remove_action( 'admin_notices', array( $this, 'handle_errors' ) );
	}

	public function add_error( $err='' ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc({$err})");

		array_push( $this->admin_notice_messages, $err );
		set_transient( self::TENNIS_EVENT_ERROR_TRANSIENT_KEY, $this->admin_notice_messages );
	}

} //end class