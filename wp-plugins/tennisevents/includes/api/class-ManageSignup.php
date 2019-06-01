<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions to manage an Event signup using Ajax
 * Supports:
 * 1. Re-ordering the list of entrants
 * 2. Adding new entrants
 * 3. Deleting entrants
 * @class  ManageSignup
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageSignup
{ 
    /**
     * Action hook used by the AJAX class.
     *
     * @var string
     */
    const ACTION   = 'manageSignup';
    const NONCE    = 'manageSignup';

    private $ajax_nonce = null;
    private $errobj = null;
    private $errcode = 0;

    private $log;
    
    /**
     * Register the AJAX handler class with all the appropriate WordPress hooks.
     */
    public static function register()
    {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        
        $handler = new self();
        add_shortcode( 'tennis_signup', array( $handler, 'signupManagementShortcode' ) );
        add_action('wp_enqueue_scripts', array( $handler, 'registerScript' ) );
        $handler->registerHandlers();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();		
        $this->log = new BaseLogger( false );
    }

    public function registerScript() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        wp_register_script( 'manage_signup'
                        , get_stylesheet_directory_uri() . '/js/signup.js'
                        , array('jquery') );

        wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $this->get_ajax_data() );

        wp_enqueue_script( 'manage_signup' );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_no_priv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }

    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        // Handle the ajax request
        check_ajax_referer(  self::NONCE, 'security'  );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisEvents::TextDomain ));
        $this->handleErrors("You've been a bad boy.");
    }
     
    /**
     * Render shortcode showing Webinar statuses for current user
     */
	public function signupManagementShortcode( $atts, $content = null )  {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        if( !is_user_logged_in() ) {
            return "User is not logged in!";
        }

        $currentuser = wp_get_current_user();
        
        if ( ! $currentuser->exists() ) {
            return 'No such user';
        }

        $user_id = (int) $currentuser->ID;
        
        if( 0 == $user_id ) {
            return "User 0 is not logged in!";
        }

        $ok = false;

        // if( um_is_core_page('user')  && um_get_requested_user() ) {
        //     if( !um_is_user_himself() ) return '';
        // }

        if( !um_is_myprofile() ) return '';

        foreach( $this->roles as $role ) {
            if( in_array( $role, $currentuser->roles ) ) {
                $ok = true;
                break;
            }
        }

        if( current_user_can( 'manage_options' ) ) $ok = true;
 
        if(! $ok ) return '';

        //The following was setting user_id to 0
		// $myshorts = shortcode_atts( array("user_id" => 0), $atts, 'user_status' );
        // extract( $myshorts );        

        $this->log->error_log( sprintf("%s: User id=%d and email=%s",$loc, $user_id, $currentuser->user_email ));
    

        return $out;
    }
    public function performTask() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        if( !is_user_logged_in() ){
            $this->errobj->add( $this->errcode++, __( 'Worker is not logged in!.', CARE_TEXTDOMAIN ));
        }

        $currentuser = wp_get_current_user();
        $ok = false;
        foreach( $this->roles as $role ) {
            if( in_array( $role, $currentuser->roles ) ) {
                $ok = true;
                break;
            }
        }
        
        if( current_user_can( 'manage_options' ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Only Care or site members can record watching a webinar.', CARE_TEXTDOMAIN ));
        }
        
        //Get the registered courses
        if ( !empty( $_POST['webinar'] )) {
            $webinar = $_POST['webinar'];
        }
        else {
            $this->errobj->add( $this->errcode++, __( 'No webinar info received.', CARE_TEXTDOMAIN ));
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors("Errors were encountered");
        }
        

        $response = array();
        $response["message"] = $mess;
        $response["returnData"] = $webinars;
        wp_send_json_success( $response );
    
        wp_die(); // All ajax handlers die when finished
    }

    /**
     * Get the AJAX data that WordPress needs to output.
     *
     * @return array
     */
    private function get_ajax_data()
    {
        $user = wp_get_current_user();
        if ( ! ( $user instanceof WP_User ) ) {
            throw new Exception('ET call home!');
        }
        $user_id = $user->ID;
        $existing_courses = get_user_meta( $user_id, RecordUserWebinarProgress::META_KEY, false );
        
        return array ( 
             'ajaxurl' => admin_url( 'admin-ajax.php' )
            ,'action' => self::ACTION
            ,'security' => wp_create_nonce(self::NONCE)
            ,'user_id' => $user_id
            ,'existing_courses' => $existing_courses
        );
    }

    private function handleErrors( string $mess ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        $response = array();
        $response["message"] = $mess;
        $response["exception"] = $this->errobj;
        wp_send_json_error( $response );
        wp_die();
    }
    
}