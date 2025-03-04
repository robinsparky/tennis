<?php
namespace cpt;

use api\ajax\ManageRegistrations;
use \DateTime;
use \DateTimeInterface;
use \WP_Error;
use commonlib\BaseLogger;
use datalayer\MemberRegistration;
use TennisClubMembership;
// use datalayer\MemberRegistration;

/** 
 * TennisClubRegistrationCpt is a Custom Post Type to support MemberRegistration links in WP
 * @class  TennisClubRegistrationCpt
 * @package Tennis Club Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class ClubMembershipCpt 
{
	
	const CUSTOM_POST_TYPE     = 'clubmembershipcpt';
	const CUSTOM_POST_TYPE_TAX = 'clubmemcategory';
	const CUSTOM_POST_TYPE_TAG = 'clubmemtag';
    const CLUBMEMBERSHIP_SLUG  = 'clubmemberships';
	
	const MEMBERSHIP_SEASON    = '_membership_season';
	const REGISTRATION_ID        = '_registration_id';
	
    //Only emit on this page
	private $hooks = array('post.php', 'post-new.php');

	private $log;

	/**
	 * Register actions, filters and scripts for this post type
	 */
	public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log( $loc );
		
		$tennisClubRegistration = new self();

		$tennisClubRegistration->customPostType(); 
		//$tennisClubRegistration->customTaxonomy();
		//add_action( 'admin_enqueue_scripts', array( $tennisClubRegistration, 'enqueue') );
			
		add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $tennisClubRegistration, 'addColumns' ), 10 );
		add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $tennisClubRegistration, 'getColumnValues'), 10, 2 );
		add_filter( 'manage_edit-' .self::CUSTOM_POST_TYPE . '_sortable_columns', array( $tennisClubRegistration, 'sortableColumns') );
		add_action( 'pre_get_posts', array( $tennisClubRegistration, 'orderby' ) );
		
		//Required actions for meta boxes
		//add_action( 'add_meta_boxes', array( $tennisMembership, 'metaBoxes' ) );

		// Hook for updating/inserting into custom tables
		add_action( 'save_post', array( $tennisClubRegistration, 'updateRegistrationDB'), 12 );
		//Hook for deleting cpt
		add_action( 'delete_post', array( $tennisClubRegistration, 'deleteRegistrationDB') );
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

		$labels = array( 'name' => 'Club Membership'
					   , 'singular_name' => 'Club Membership'
					   //, 'add_new' => 'Add Club Membership'
					   //, 'add_new_item' => 'New Membership'
					   //, 'new_item' => 'New Club Membership'
					   //, 'edit_item' => 'Edit Club Membership'
					   , 'view_item' => 'View Club Membership'
					   , 'all_items' => 'All Club Memberships'
					   , 'menu_name' => 'Club Membership'
					   , 'search_items'=>'Search Club Memberships'
					   , 'not_found' => 'No Club Memberships found'
                       , 'not_found_in_trash'=> 'No Club Memberships found in Trash');
                       
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'description' => 'Club Membership as a CPT'
					 , 'menu_position' => 93
					 , 'menu_icon' => 'dashicons-code-standards'
					 , 'exclude_from_search' => false
					 , 'has_archive' => true
					 , 'publicly_queryable' => true
					 , 'query_var' => true
					 , 'capability_type' => 'post'
					 , 'capabilities' => array('create_posts'=>false) //Cannot add new registrations; 'do_not_allow' removes support for the "Add New" function, including Super Admin's
					 , 'map_meta_cap' => true //Set to `false`, if users are not allowed to edit/delete existing posts
					 , 'hierarchical' => true
					 , 'show_in_rest' => true //causes Gutenberg editor to be used
					 , 'rewrite' => array( 'slug' => self::CLUBMEMBERSHIP_SLUG, 'with_front' => false )
					 //, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'excerpt', 'revisions', 'custom-fields', 'page-attributes' ) 
					 , 'supports' => array( 'title', 'editor', 'author' ) 
					 , 'public' => true
					 , 'show_in_nav_menus'=>true
					 , 'show_in_menu' => true );
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
		$newColumns['cb'] = $columns['cb'];
		$newColumns['title'] = $columns['title'];
		$newColumns['registrationid'] = __( 'Registration Id', TennisClubMembership::TEXT_DOMAIN ); 
		$newColumns['season'] = __('Season', TennisClubMembership::TEXT_DOMAIN );
		// $newColumns['author'] = $columns['author'];
        $newColumns['date'] = $columns['date'];
		return $newColumns;
	}

	public function sortableColumns ( $columns ) {
		$columns['registrationid'] = 'registrationId';
		$columns['season'] = 'season';
		return $columns;
	}

	public function orderby ( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		if( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		
		if ('registrationId' === $query->get('orderby')) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', self::REGISTRATION_ID);
		} elseif ('season' === $query->get('orderby')) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', self::MEMBERSHIP_SEASON);
		}
	}

	/**
     * Populate the TennisClubRegistrationCpt columns with values
     */
	public function getColumnValues( $column_name, $postID ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( "$loc --> $column_name, $postID" );

		if( $column_name === 'registrationid') {
			$regId = get_post_meta( $postID, self::REGISTRATION_ID, TRUE );
			if( !empty($regId) ) {
				echo $regId;
			}
			else {
				echo __( '??', TennisClubMembership::TEXT_DOMAIN );
			}
		}
		elseif( $column_name === 'season') {
			$season = get_post_meta( $postID, self::MEMBERSHIP_SEASON, TRUE );
			if( !empty($season) ) {
				echo $season;
			}
			else {
				echo __( '??', TennisClubMembership::TEXT_DOMAIN );
			}
		}
	}

	public function customTaxonomy() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );
		
		//hierarchical
		$labels = array( 'name' => 'Club Registration Categories'
						, 'singular_name' => 'Club Registration Category'
						, 'search_items' => 'Club Registration Search Category'
						, 'all_items' => 'All Club Registrations Categories'
						, 'parent_item' => 'Parent Club Registration Category'
						, 'parent_item_colon' => 'Parent Club Registration Category:'
						, 'edit_item' => 'Edit Club Registration Category'
						, 'update_item' => 'Update Club Registration Category'
						, 'add_new_item' => 'Add New Club Registration Category'
						, 'new_item_name' => 'New Club Registration Category'
						, 'menu_name' => 'Club Registration Categories'
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
	public function updateRegistrationDB( $post_id ) {
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
        //$this->log->error_log($post, "$loc: post...");
        if( $post->post_type !== self::CUSTOM_POST_TYPE ) return;

	}

	public function deleteRegistrationDB( int $post_id ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: post_id='$post_id'");

		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			$this->log->error_log("$loc --> doing autosave");
			return;
		}

		if( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->log->error_log("$loc --> cannot edit post");
			return;
		}
		
        $post = get_post( $post_id );
        //$this->log->error_log($post, "$loc: post...");
        if( $post->post_type !== self::CUSTOM_POST_TYPE ) return;
		$season = get_post_meta($post_id,ClubMembershipCpt::MEMBERSHIP_SEASON,true);
		$regId = (int)get_post_meta($post_id,ClubMembershipCpt::REGISTRATION_ID,true);
		$reg = MemberRegistration::get($regId);
		if(null !== $reg) $reg->delete();

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
	private function isNewRegistration() {
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