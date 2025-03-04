<?php
namespace cpt;

use \DateTime;
use \DateTimeInterface;
use \WP_Error;
use commonlib\BaseLogger;
use TennisClubMembership;
use api\ajax\ManagePeople;
use datalayer\Person;
use TM_Install;

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
    public const USER_PERSON_ID = 'user_person_id';
	public const USER_CORP_ID   = 'user_corp_id';
	
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
			
		add_filter( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_columns', array( $tennisClubMember, 'addColumns' ), 10 );
		add_action( 'manage_' . self::CUSTOM_POST_TYPE . '_posts_custom_column', array( $tennisClubMember, 'getColumnValues'), 10, 2 );
		add_filter( 'manage_edit-' . self::CUSTOM_POST_TYPE . '_sortable_columns', array( $tennisClubMember, 'sortableColumns') );
		add_action( 'pre_get_posts', array( $tennisClubMember, 'orderby' ) );
		
		//Required actions for meta boxes
		//add_action( 'add_meta_boxes', array( $tennisMembership, 'metaBoxes' ) );

		//Hook for new user registration - bad idea!
		//add_action('user_register',array($tennisClubMember,'addNewPersonHook'));
		//Hook for updating/inserting into Tennis tables
		add_action( 'save_post', array( $tennisClubMember, 'updatePersonDB'), 12 );
		//Hook for deleting cpt
		add_action( 'delete_post', array( $tennisClubMember, 'deletePersonDB') );
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

		$labels = array( 'name' => 'Club People'
					   , 'singular_name' => 'Club People'
					   //, 'add_new' => 'Add Club People'
					   //, 'add_new_item' => 'New Club People'
					   //, 'new_item' => 'New Club People'
					   //, 'edit_item' => 'Edit Club People'
					   , 'view_item' => 'View Club People'
					   , 'all_items' => 'All Club People'
					   , 'menu_name' => 'Club People'
					   , 'search_items'=>'Search Club People'
					   , 'not_found' => 'No Club People found'
                       , 'not_found_in_trash'=> 'No Club People found in Trash');
                       
		$args = array( 'labels' => $labels
					 //, 'taxonomies' => array( 'category', 'post_tag' )
					 , 'description' => 'Club People as a CPT'
					 , 'menu_position' => 92
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
		$newColumns['personid'] = __( 'Person Id', TennisClubMembership::TEXT_DOMAIN );
		//$newColumns['taxonomy-tennisclubcategory'] = __('Category', TennisClubMembership::TEXT_DOMAIN );
		// $newColumns['author'] = $columns['author'];
        $newColumns['date'] = $columns['date'];
		return $newColumns;
	}

	public function sortableColumns ( $columns ) {
		$columns['personid'] = 'personId';
		return $columns;
	}

	public function orderby ( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( $loc );

		if( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		
		if ('personId' === $query->get('orderby')) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', self::USER_PERSON_ID);
		}
	}

	/**
     * Populate the columns with values
     */
	public function getColumnValues( $column_name, $postID ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( "$loc --> $column_name, $postID" );
		if( $column_name === 'personid') {
			$personId = get_post_meta( $postID, self::USER_PERSON_ID, TRUE );
			if( !empty($personId) ) {
				echo $personId;
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
		$labels = array( 'name' => 'Club People Categories'
						, 'singular_name' => 'Club People Category'
						, 'search_items' => 'Club People Search Category'
						, 'all_items' => 'All Club People Categories'
						, 'parent_item' => 'Parent Club People Category'
						, 'parent_item_colon' => 'Parent Club People Category:'
						, 'edit_item' => 'Edit Club People Category'
						, 'update_item' => 'Update Club People Category'
						, 'add_new_item' => 'Add New Club People Category'
						, 'new_item_name' => 'New Club People Category'
						, 'menu_name' => 'Club People Categories'
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
						 , array( 'label' => 'Club People Tags'
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
	 * This hook fires when a new user is registered in WP database
	 * It adds this user to the Person database if they have a compatible role
	 * @param int $user_id the id of the user just added
	 */
	public function addNewPersonHook($user_id) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: user_id='{$user_id}'");
		$user = get_user_by("ID",$user_id);
		if(false === $user) {
			$this->log->error_log("$loc: no such user");
			return;
		}

		$email = $user->user_email;
		$firstName = $user->first_name ?? $user->display_name;
		$lastName = $user->last_name ?? $user->display_name;
		$corpId = TM()->getCorporationId();
		$person = Person::find(["email"=>$email])[0] ?? null;
		if(null === $person) {
			$this->log->error_log("$loc: no existing Person with email {$email}");
			$this->log->error_log(print_r($user,true),"$loc: user data...");
			foreach(TM_Install::$tennisRoles as $slug=>$name) {
				$this->log->error_log("$loc: testing role {$slug}");
				if(in_array($slug,$user->roles)) {
					$currentTime = new DateTime('NOW');
					$person = Person::fromEmail($corpId,$email,$firstName,$lastName);
					//Setup the corresponding custom post type
					$content = $person->getName();
					$title = $user->user_login;
					$postData = array(
								'post_title' => $title,
								'post_status' => 'publish',
								'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
								'post_content' => $content,
								'post_type' => TennisMemberCpt::CUSTOM_POST_TYPE,
								'post_author' => get_current_user_id(),
								'post_date'   => $currentTime->format('Y-m-d G:i:s'),
								'post_modified' => $currentTime->format('Y-m-d G:i:s')
								);			
					$newPostId = wp_insert_post($postData, true);//NOTE: This triggers updateDB in TennisEventCpt
					if(is_wp_error($newPostId)) {
						$mess = $newPostId->get_error_message();
						throw new \Exception(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
					}
					$person->addExternalRef($newPostId);
					$person->save();
					update_user_meta($user_id,ManagePeople::USER_PERSON_ID,$person->getID());
					update_post_meta($newPostId, ManagePeople::USER_PERSON_ID, $person->getID());
					break;
				}
			}
		}
 	}
	
	/**
	 * Update the membership database
	 */
	public function updatePersonDB( $post_id ) {
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
		$post = get_post($post_id);
		//$this->log->error_log(print_r($post,true),"$loc: post...");

	}

	/**
	 * Delete Person if it's CPT is deleted
	 */
	public function deletePersonDB( int $post_id ) {
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
        $this->log->error_log($post, "$loc: post...");
        if( $post->post_type !== self::CUSTOM_POST_TYPE ) return;
		
		$person = Person::find(["external"=>$post_id])[0] ?? null;
		if(null !== $person) $person->delete();
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
	 * Detect if we are creating a new club person
	 */
	private function isNewClubMember() {
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