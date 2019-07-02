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

    private $clubId;
    private $eventId;

    private $signup = array();
    private $nameKeys = array();
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

        $jsurl =  TE()->getPluginUrl() . 'js/signup.js';
        $this->log->error_log("$loc: $jsurl");
        wp_register_script( 'manage_signup', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );

        //wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $this->get_ajax_data() );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        add_shortcode( 'manage_signup', array( $this, 'signupManagementShortcode' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
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
        $this->clubId = $club->getID();

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
        $this->eventId = $target->getID();

        $td = new TournamentDirector( $target,  $target->getMatchType() );
        $eventName = $td->getName();
        $clubName = $club->getName();
        $bracket = $td->getEvent()->getWinnersBracket();
        $isApproved = $bracket->isApproved();
        $numPrelimMatches = count( $bracket->getMatchesByRound(1) );
        $this->signup = $td->getSignup();

        wp_enqueue_script( 'manage_signup' );       
        wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $this->get_ajax_data() );

        //Signup
        $out = '';
        $out .= '<div class="signupContainer" data-eventid="' . $this->eventId . '" ';
        $out .= 'data-clubid="' . $this->clubId . '">' . PHP_EOL;
        $out .= "<h3>$clubName</h3>" . PHP_EOL;        
        $out .= "<h4>$eventName</h4>" . PHP_EOL;
        $out .= '<ul class="eventSignup">' . PHP_EOL;
        
        $templr = <<<EOT
<li id="%s" class="entrantSignup">
<div class="entrantPosition">%d.</div>
<div class="entrantName">%s</div>
</li>
EOT;
        $templw = <<<EOT
<li id="%s" class="entrantSignup sortable-container ui-state-default" data-currentpos="%d">
<div class="entrantPosition">%d.</div>
<input name="entrantName" type="text" maxlength="35" size="15" class="entrantName" value="%s">
<input name="entrantSeed" type="number" maxlength="2" size="2" class="entrantSeed" step="any" value="%d">
<button class="button entrantDelete" type="button" id="%s">Delete</button>
</li>
EOT;

        $ctr = 1;
        foreach( $this->signup as $entrant ) {
            $pos = $entrant->getPosition();
            $name = $entrant->getName();
            $nameId = str_replace( ' ', '_', $name );
            $seed = $entrant->getSeed();
            $rname = ( $seed > 0 ) ? $name . '(' . $seed . ')' : $name;
            $templ = $isApproved ? $templr : $templw;
            if($isApproved) {
                $tbl = sprintf( $templr, $nameId, $pos, $rname );
            }
            else {
                $tbl = sprintf( $templw, $nameId, $pos, $ctr++, $name, $seed, $nameId );
            }
            $out .= $tbl;
        }
        $out .= '</ul>' . PHP_EOL;

        if( !$isApproved ) {
            $out .= '<button class="button" type="button" id="addEntrant">Add Entrant</button><br/>' . PHP_EOL;
            if( $numPrelimMatches === 0 ) {
                $out .= '<button class="button" type="button" id="approveSignup">Approve Signup</button>' . PHP_EOL;
            }
        }
        $out .= '</div>'; //container
        //Preliminary view
        $out .= '<div class="prelimcontainer">' . PHP_EOL;
        $out .= '</div>' . PHP_EOL;

        $out .= '<div id="tennis-event-message"></div>';

        return $out;
    }

    /**
     * Perform the CRUD or Move tasks as indicated by the Ajax request
     */
    public function performTask() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        $this->log->error_log( $_POST, "$loc: _POST:"  );

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        if( !is_user_logged_in() ){
            $this->errobj->add( $this->errcode++, __( 'User is not logged in!.',  TennisEvents::TEXT_DOMAIN ));
        }
        
        $ok = false;
        if( current_user_can( 'manage_options' ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Only administrators can modify signup.', TennisEvents::TEXT_DOMAIN ));
        }
        

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors(__("Errors were encountered", TennisEvents::TEXT_DOMAIN  ) );
        }

        $this->log->error_log($this->signup,"$loc: signup:");
        $mess = '';
        $response = array();
        $data = $_POST["data"];
        $task = $data["task"];
        switch( $task ) {
            case "move":
                $mess = $this->moveEntrant( $data );
                break;
            case "update":
                $mess = $this->updateEntrant( $data );
                break;
            case "delete":
                $mess = $this->deleteEntrant( $data );
                break;
            case "add":
                $mess = $this->addEntrant( $data );
                break;
            case "approve":
                $mess = $this->approve( $data );
                break;
            default:
                wp_die(__( 'Illegal task.', TennisEvents::TEXT_DOMAIN ));
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors( $mess );
        }

        $response["message"] = $mess;

        /*
          Setup the return data which is the signup or empty array
        */
        $signupArray = [];
        foreach( $this->signup as $entrant ) {
            $arrEntrant = $entrant->toArray();
            $arrEntrant["task"]=$task;
            $signupArray[] = $arrEntrant;
        }
        //$this->log->error_log($signupArray, "$loc: Signup Array:");
        $response["returnData"] = $signupArray;

        //Send the response
        wp_send_json_success( $response );
    
        wp_die(); // All ajax handlers die when finished
    }

    /**
     * Move the entrant to another position in the signup
     * @param $data Associative array contaning entrant's data
     * @return message describing the result of the operation
     */
    private function moveEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $fromPos = $data["currentPos"];
        $toPos   = round($data["newPos"]);

        $mess    =  __('Move Entrant succeeded.', TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $event->moveEntrant( $fromPos, $toPos );
            $this->signup = $event->getSignup( true );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Update the entrant's name, seeding
     * @param $data Associative array containing entrant's data
     * @return message describing result of the operation
     */
    private function updateEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        $this->eventId = $data["eventId"];
        $fromPos = $data["position"];
        $seed    = $data["seed"];
        $oldName = $data["name"]; 
        $newName = $data["newName"];

        $this->log->error_log($data, "$loc: data...");
        $this->log->error_log("$loc: newName='$newName'");

        $mess    =  __('Update Entrant succeeded.', TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $entrant = $event->getNamedEntrant( $oldName );
            if( is_null( $entrant ) ) {
                $mess = "No such entrant: '$oldName'";
                $this->log->error_log($mess);
                throw new InvalidEntrantException(__( $mess, TennisEvents::TEXT_DOMAIN) );
            }
            if( !empty( $newName) ) {
                $entrant->setName( $newName );
            }
            else {
                $this->log->error_log("$loc: Apparently '$newName' is empty!");
            }
            if( $seed > -1 ) {
                $entrant->setSeed( $seed );
            }
            $entrant->save();
            $this->signup = [];
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Delete the given entrant from the signup
     * @param $data Associative array of entrant's data
     * @return message describing result of the operation
     */
    private function deleteEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $name          = $data["name"]; 

        $mess  =  __('Delete Entrant succeeded.', TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $event->removeFromSignup( $name );
            $event->save();
            $this->signup = [];
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Add a new entrant to the signup for an event
     * @param $data Associative array containing the entrant's data
     * @return message describing result of the operation
     */
    private function addEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $fromPos = $data["position"];
        $seed    = $data["seed"];
        $name    = $data["name"]; 

        $mess    =  __('Add Entrant succeeded.', TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $event->addToSignup( $name, $seed );
            $event->save();
            $this->signup = $event->getSignup( true );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    private function approve( $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        try {            
            $event   = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $numMatches = $td->schedulePreliminaryRounds( Bracket::WINNERS );
            $mess =  __("Approved $numMatches preliminary matches.", TennisEvents::TEXT_DOMAIN );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
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
