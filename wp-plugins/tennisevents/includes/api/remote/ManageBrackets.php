<?php
namespace api\remote;
use commonlib\BaseLogger;
use Event;
use WP_Error;
use TennisEvents;
use TournamentDirector;
use InvalidBracketException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $jsDataForTennisBrackets;

/** 
 * Manage brackets by responding to ajax requests from template
 * with actions to manage the Events brackets such as add new bracket
 * @class  ManageBrackets
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageBrackets
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageTennisBrackets';
    const NONCE     = 'manageTennisBrackets';

    private $eventId = 0;
    private $errobj = null;
    private $errcode = 0;
    private $log;

    public static function register() {
        $handle = new self();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
        $handle->registerHandlers();
        
        global $jsDataForTennisBrackets;
        $jsDataForTennisBrackets = $handle->get_ajax_data();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();	
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        $jsurl =  TE()->getPluginUrl() . 'js/brackets.js';
        wp_register_script( 'manage_brackets', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-tabs'), TennisEvents::VERSION, true );
        
        // $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        // wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        //add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }
    
    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        // Handle the ajax request
        check_ajax_referer(  self::NONCE, 'security'  );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisEvents::TEXT_DOMAIN ));
        $this->handleErrors("You've been a bad boy.");
    }
    
    /**
     * Perform the tasks as indicated by the Ajax request
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
        
        $ok = false;
        if( current_user_can( 'manage_options' ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Only administrators can modify draw.', TennisEvents::TEXT_DOMAIN ));
        }
        
        if(count($this->errobj->errors) > 0) {
            $this->handleErrors(__("Errors were encountered", TennisEvents::TEXT_DOMAIN  ) );
        }

        $this->log->error_log("$loc: action={$_POST['action']}");
        if( self::ACTION !== $_POST['action']) return;

        $response = array();

        $data = $_POST["data"];
        $task = $data["task"];
        // $this->eventId = $data["eventId"];
        // $event = Event::get( $this->eventId );
        // $bracketName = $data["bracketName"];
        // $bracket = $event->getBracket( $bracketName );
        $returnData = $task;
        $mess = '';
        switch( $task ) {
            case 'editname':
                $mess = $this->modifyBracketName( $data );
                $returnData = $data;
                break;
            case 'addbracket':
                $mess = $this->addBracket( $data );
                $returnData = $data;
                break;
            case 'removebracket':
                $mess = $this->removeBracket( $data );
                $returnData = $data;
                break;
            default:
            $mess =  __( 'Illegal Bracket task.', TennisEvents::TEXT_DOMAIN );
            $errobj->add( $errcode++, $mess );
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors( $mess );
        }

        $response["message"] = $mess;
        $response["returnData"] = $returnData;

        //Send the response
        wp_send_json_success( $response );
    
        wp_die(); // All ajax handlers die when finished
    }
    
    /**
     * Change and existing bracket's name
     */
    private function modifyBracketName( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $newBracketName   = strip_tags( htmlspecialchars( $data["bracketName"] ));
        $oldBracketName   = strip_tags( htmlspecialchars( $data['oldBracketName'] ));
        $bracketNum    = $data["bracketNum"];
        $mess = "";
        try {
            $event = Event::get( $eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketNum );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $mess = "Changed bracket name from '{$oldBracketName}' to '{$newBracketName}'";
            $bracket->setName($newBracketName);
            $data["signuplink"] = $td->getPermaLink() . "?manage=signup&bracket={$bracket->getName()}";
            $data["drawlink"]   = $td->getPermaLink() . "?manage=draw&bracket={$bracket->getName()}";
            $td->save();
        } 
        catch (Exception $ex ) {
            $errobj->add( $errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Add a new bracket to the event
     */
    private function addBracket( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $newBracketName = strip_tags( htmlspecialchars( $data["bracketName"] ));
        $event = Event::get( $eventId );
        try {
            $event = Event::get( $eventId );
            $td = new TournamentDirector( $event );
            $bracket = $td->addBracket( $newBracketName ); //automatically saves
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("Bracket not created or found", TennisEvents::TEXT_DOMAIN) );
            }
            $data["bracketNum"] = $bracket->getBracketNumber();
            $data["bracketName"] = $bracket->getName();
            $data["imgsrc"] = TE()->getPluginUrl() . 'img/removeIcon.gif';
 
            $data["signuplink"] = $td->getPermaLink() . "?manage=signup&bracket={$bracket->getName()}";
            $data["drawlink"]   = $td->getPermaLink() . "?manage=draw&bracket={$bracket->getName()}";

            $mess = "Added bracket '{$newBracketName}' (with number='{$bracket->getBracketNumber()}')";
        } 
        catch (Exception $ex ) {
            $errobj->add( $errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    } 

    /**
     * Remove a bracket by name
     */
    private function removeBracket( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $bracketNum    = $data["bracketNum"];
        $bracketName = strip_tags( htmlspecialchars( $data["bracketName"] ));
        $event = Event::get( $eventId );
        try {
            $event = Event::get( $eventId );
            $td = new TournamentDirector( $event );
            $td->removeBracket( $bracketName );
            $td->save();
            $mess = "Removed bracket '{$bracketName}'";
        } 
        catch (Exception $ex ) {
            $errobj->add( $errcode++, $ex->getMessage() );
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
        wp_die($mess);
    }
}