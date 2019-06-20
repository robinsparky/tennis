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
 * 4. Approving the lineup
 * @class  ManageSignup
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageSignup
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

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

    private $signup = array();
    private $log;
    
    /**
     * Register the AJAX handler class with all the appropriate WordPress hooks.
     */
    public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log( $loc );
        
        $handler = new self();
        add_action( 'wp_enqueue_scripts', array( $handler, 'registerScripts' ) );
        $handler->registerHandlers();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

	    $this->errobj = new WP_Error();		
        $this->log = new BaseLogger( true );
    }

    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $jsurl =  plugins_url() . '/tennisevents/js/signup.js';
        wp_register_script( 'manage_signup', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable') );
        wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $this->get_ajax_data() );
        wp_enqueue_script( 'manage_signup' );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        add_shortcode( 'manage_signup', array( $this, 'signupManagementShortcode' ) );
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
        
        if ( !$currentuser->exists() ) {
            return 'No such user';
        }

        $user_id = (int) $currentuser->ID;
        
        if( 0 == $user_id ) {
            return "User 0 is not logged in!";
        }

        $ok = false;

        if( current_user_can( 'manage_options' ) ) $ok = true;
 
        if( !$ok ) return '';

        //The following was setting user_id to 0
        $my_shorts = shortcode_atts( array(
            'clubname' => '',
            'eventid' => 0
        ), $atts, 'manage_signup' );

        $club = null;
        if(!empty( $my_shorts['clubname'] ) ) {
            $arrClubs = Club::search( $my_shorts['clubName'] );
            if( count( $arrClubs) > 0 ) {
                $club = $arrClubs[0];
            }
        }
        else {
            $homeClubId = esc_attr( get_option(self::HOME_CLUBID_OPTION_NAME, 0) );
            $club = Club::get( $homeClubId );
        }

        if( is_null( $club ) ) return __('Please set home club id in options or specify name in shortcode', TennisEvents::TEXT_DOMAIN );

        $eventId = $my_shorts['eventid'];
        $this->log->error_log("$loc: EventId=$eventId");
        if( $eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $this->log->error_log($my_shorts, "$loc: My Shorts" );   

        $evts = Event::find( array( "club" => $club->getID() ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = Event::getEventRecursively( $evt, $eventId );//gw_support
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
        } 
        
        if( !$found ) return __('No such event for this club', TennisEvents::TEXT_DOMAIN );

        $td = new TournamentDirector( $target,  $target->getMatchType() );
        $eventName = $td->getName();
        $clubName = $club->getName();
        $bracket = $td->getEvent()->getWinnersBracket();
        $isApproved = $bracket->isApproved();
        $isApproved = false;
        $this->signup = $td->getSignup();

        //Signup
        $out = '';
        $out .= '<div class="signupContainer">' . PHP_EOL;
        $out .= "<h3>$clubName</h3>" . PHP_EOL;        
        $out .= "<h4>$eventName</h4>" . PHP_EOL;
        $out .= '<ul class="eventSignup">' . PHP_EOL;
        $templw = <<<EOT
<li id="%s" class="entrantSignup drag-container">
<div class="entrantPosition">%d.</div>
<input name="%s" type="text" maxlength="35" size="15" class="entrantName" value="%s">
<input type="number" maxlength="2" size="2" class="entrantSeed" step="any" value="%d">
<button class="button entrantDelete" type="button" id="%s">Delete</button><br/>
</li>
EOT;

        $templr = <<<EOT
<li id="%s" class="entrantSignup">
<div class="entrantPosition">%d.</div>
<div class="entrantName">%s(%d)</div>
</li>
EOT;
        $ctr = 1;
        foreach($this->signup as $entrant ) {
            $pos = $entrant->getPosition();
            $name = $entrant->getName();
            $nameId = str_replace( ' ', '_', $name );
            $seed = $entrant->getSeed();
            $templ = $isApproved ? $templr : $templw;
            if($isApproved) {
                $tbl = sprintf( $templr, $nameId, $pos, $name, $seed );
            }
            else {
                $tbl = sprintf( $templw, $nameId, $pos, $nameId, $name, $seed, $nameId );
            }
            $out .= $tbl;
        }
        $out .= '</ul>' . PHP_EOL;

        if( !$isApproved ) {
            $out .= '<button class="button" type="button" id="addEntrant">Add Entrant</button><br/>' . PHP_EOL;
            $out .= '<button class="button" type="button" id="saveChanges">Save Changes</button><br/>' . PHP_EOL;
        }
        $out .= '<button class="button" type="button" id="viewPreliminary">View Prelimary Draw</button>' . PHP_EOL;
        $out .= '</div>'; //container
        //Preliminary view
        $out .= '<div class="prelimcontainer">' . PHP_EOL;
        $out .= '</div>' . PHP_EOL;

        $out .= '<div id="tennis-event-message"></div>';

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
            $this->errobj->add( $this->errcode++, __( 'Worker is not logged in!.',  TennisEvents::TEXT_DOMAIN ));
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
        

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors("Errors were encountered");
        }
        

        $response = array();
        $response["message"] = $mess;
        $response["returnData"] = array(); //TODO: What goes here
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
        $mess = '';
        return array ( 
             'ajaxurl' => admin_url( 'admin-ajax.php' )
            ,'action' => self::ACTION
            ,'security' => wp_create_nonce( self::NONCE )
            ,'message' => $mess
            ,'signupData' => $this->signup
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
