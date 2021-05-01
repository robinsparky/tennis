<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions to manage an Event signup using Ajax.
 * NOTE: Always deals with the Winners bracket
 * Supports:
 * 1. Re-ordering the list of entrants
 * 2. Adding new entrants
 * 3. Deleting entrants
 * 4. Updating entrant's name and/or seed
 * 5. Approving the lineup by scheduling preliminary rounds
 * 6. Reset by removing preliminary rounds
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
    const SHORTCODE = 'manage_signup';

    private $ajax_nonce = null;
    private $errobj = null;
    private $errcode = 0;

    private $clubId;
    private $eventId;
    private $bracketName;

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
        $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        $this->log->error_log("$loc: $jsurl");
        wp_register_script( 'manage_signup', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        wp_enqueue_style( 'tennis_css', $cssurl );

        //wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $this->get_ajax_data() );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        add_shortcode( self::SHORTCODE, array( $this, 'signupManagementShortcode' ) );
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

        //The following was setting user_id to 0
        $my_shorts = shortcode_atts( array(
            'clubname' => '',
            'eventid' => 0,
            'bracketname' => Bracket::WINNERS
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

        $this->eventId = (int)$my_shorts['eventid'];
        $this->log->error_log("$loc: EventId=$this->eventId");
        if( $this->eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $this->log->error_log($my_shorts, "$loc: My Shorts" );   

        //TODO: Put all references into functions in TD
        $evts = Event::find( array( "club" => $club->getID() ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = Event::getEventRecursively( $evt, $this->eventId );//gw_support
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
        } 
        
        if( !$found ) return __('No such event for this club', TennisEvents::TEXT_DOMAIN );

        //Get the bracket from attributes
        $bracketName = $my_shorts["bracketname"];
        $bracket = $target->getBracket( $bracketName );
        if( is_null( $bracket ) ) {
            //$bracket = $target->getWinnersBracket();   
            $mess =  __('No such bracket:', TennisEvents::TEXT_DOMAIN ); 
            $mess .= $bracketName;
            return $mess;    
        }
        
        $td = new TournamentDirector( $target );
        $eventName = str_replace("\'", "&apos;", $td->getName());
        $parentName = str_replace("\'", "&apos;", $td->getParentEventName());
        $clubName = $club->getName();
        $isApproved = $bracket->isApproved();
        $numPrelimMatches = count( $bracket->getMatchesByRound(1) );
        //Get the signup for this bracket
        $this->signup = $td->getSignup( $bracketName );
        $this->log->error_log( $this->signup, "$loc: Signup");
        $numSignedUp = count( $this->signup );

        $jsData = $this->get_ajax_data();
        $jsData["clubId"] = $club->getID();
        $jsData["eventId"] = $this->eventId;
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $numSignedUp;
        $jsData["numPreliminary"] = $numPrelimMatches;
        $jsData["isBracketApproved"] = $isApproved ? 1:0;
        wp_enqueue_script( 'manage_signup' );   
        wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $jsData );
        
        //Signup
        $out = '';
        $out .= '<div class="signupContainer" data-eventid="' . $this->eventId . '" ';
        $out .= 'data-clubid="' . $this->clubId . '" data-bracketname="' . $bracketName . '">' . PHP_EOL;
        $out .= "<h2 class='tennis-signup-title'>${parentName}</h2>" . PHP_EOL;
        $out .= "<h3 class='tennis-signup-title'>{$eventName}&#58;&nbsp;{$bracketName}</h3>" . PHP_EOL;
        $out .= '<ul class="eventSignup tennis-event-signup">' . PHP_EOL;
        
        $templr = <<<EOT
<li id="%s" class="entrantSignupReadOnly">
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
            $name = str_replace("\'","&apos;", $entrant->getName());
            $nameId = str_replace( [' ',"\'","'"], ['_','',''], $entrant->getName() );
            $seed = $entrant->getSeed();
            $rname = ( $seed > 0 ) ? $name . '(' . $seed . ')' : $name;
            if( $numPrelimMatches > 0 || !is_user_logged_in() || !current_user_can( 'manage_options' ) ) {
                $tbl = sprintf( $templr, $nameId, $pos, $rname );
            }
            else {
                $tbl = sprintf( $templw, $nameId, $pos, $ctr++, $name, $seed, $nameId );
            }
            $out .= $tbl;
        }
        $out .= '</ul>' . PHP_EOL;

        if( $numPrelimMatches < 1 && is_user_logged_in() && current_user_can( 'manage_options' )  ) {
            $out .= '<button class="button" type="button" id="addEntrant">Add Entrant</button><br/>' . PHP_EOL;
            $out .= '<button class="button" type="button" id="reseqSignup">Resequence Signup</button><br/>' . PHP_EOL;
            $out .= '<button class="button" type="button" id="createPrelim">Initialize Draw</button>' . PHP_EOL;
        }
        // elseif( $numPrelimMatches < 1 && $bracketName === Bracket::CONSOLATION ) {
        //     $out .= '<button class="button" type="button" id="createPrelim">Create Preliminary Round</button>' . PHP_EOL;   
        // }
        $out .= '</div>'; //container
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
        
        $this->log->error_log("$loc: action={$_POST['action']}");
        if( self::ACTION !== $_POST['action']) return;

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        if( !is_user_logged_in() ) {
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
            case "createPrelim":
                $mess = $this->createPreliminary( $data );
                break;
            case "reseqSignup":
                $mess = $this->reseqSignup( $data );
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
     * @param array $data Associative array contaning entrant's data
     * @return string message describing the result of the operation
     */
    private function moveEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $this->bracketName = $data["bracketName"];
        $fromPos = $data["currentPos"];
        $toPos   = round($data["newPos"]);

        $mess    =  __("Move Entrant from '{$fromPos}' to '{$toPos}' succeeded.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $bracket = $event->getBracket( $this->bracketName );
            $rows = $bracket->moveEntrant( $fromPos, $toPos );
            $this->signup = $bracket->getSignup( true );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }
    
    /**
     * Resequence positions in the signup
     * @param array $data Associative array contaning entrant's data
     * @return string message describing the result of the operation
     */
    private function reseqSignup( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $this->bracketName = $data["bracketName"];

        $mess    =  __("Resequence the signup succeeded.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event = Event::get( $this->eventId );
            $bracket = $event->getBracket( $this->bracketName );
            $rows = $bracket->resequenceSignup( );
            $this->signup = $bracket->getSignup( true );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Update the entrant's name, seeding
     * @param array $data Associative array containing entrant's data
     * @return string message describing result of the operation
     */
    private function updateEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        $this->eventId = $data["eventId"];
        $this->bracketName = $data["bracketName"];
        $fromPos = $data["position"];
        $seed    = $data["seed"];
        $oldName = sanitize_text_field($data["name"]); 
        $newName = sanitize_text_field($data["newName"]);

        $this->log->error_log($data, "$loc: data...");
        $this->log->error_log("$loc: newName='$newName'");

        $mess    =  __("Update Entrant from '{$oldName}' to '{$newName}'  succeeded.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $bracket = $event->getBracket( $this->bracketName );
            $entrant = $bracket->getNamedEntrant( $oldName );
            if( is_null( $entrant ) ) {
                $mess = "No such entrant: '$oldName'";
                $this->log->error_log($mess);
                throw new InvalidEntrantException(__( $mess, TennisEvents::TEXT_DOMAIN) );
            }
            if( !empty( $newName) ) {
                $entrant->setName( $newName );
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
     * @param array $data Associative array of entrant's data
     * @return string message describing result of the operation
     */
    private function deleteEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $this->bracketName = $data["bracketName"];
        $name = sanitize_text_field($data["name"]); 

        $mess  =  __("Delete Entrant '{$name}' succeeded.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $bracket = $event->getBracket( $this->bracketName );
            if( !$bracket->removeFromSignup( $name ) ) {
                $mess =  __("Delete Entrant '{$name}' failed.", TennisEvents::TEXT_DOMAIN );
            }
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
     * @param array $data Associative array containing the entrant's data
     * @return string message describing result of the operation
     */
    private function addEntrant( array $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        $this->bracketName = $data["bracketName"];
        $fromPos = $data["position"];
        $seed    = $data["seed"];
        $name    = sanitize_text_field($data["name"]); 

        $mess    =  __("Add Entrant '{$name}' succeeded.", TennisEvents::TEXT_DOMAIN );
        try {            
            $event   = Event::get( $this->eventId );
            $bracket = $event->getBracket( $this->bracketName );
            if( false === $bracket->addToSignup( $name, $seed ) ) {
                $mess =  __("Add Entrant '{$name}' failed.", TennisEvents::TEXT_DOMAIN );
            }
            $event->save();
            $this->signup = $bracket->getSignup( true );
        }
        catch( Exception $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    
    /**
     * Create preliminary rounds for this event/bracket
     * @param array $data Associative array containing the entrant's data
     * @return string message describing result of the operation
     */
    private function createPreliminary( $data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");

        $this->eventId = $data["eventId"];
        try {            
            $event   = Event::get( $this->eventId );
            $td = new TournamentDirector( $event );
            $bracketName = $data["bracketName"];
            $this->log->error_log("$loc with bracketName='$bracketName'");
            $numMatches = $td->schedulePreliminaryRounds( $bracketName );
            $mess =  __("Created $numMatches preliminary matches for '$bracketName' bracket.", TennisEvents::TEXT_DOMAIN );
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