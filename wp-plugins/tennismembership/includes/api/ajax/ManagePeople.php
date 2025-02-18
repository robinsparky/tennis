<?php
namespace api\ajax;

use TennisClubMembership;
use commonlib\BaseLogger;
use \WP_Error;
use TM_Install;
use \DateTime;
use \InvalidArgumentException;
use \RuntimeException;
use cpt\ClubMembershipCpt;
//use cpt\TennisClubMembershipCpt;
use datalayer\Corporation;
use datalayer\Person;
use datalayer\Genders;
use datalayer\MemberRegistration;
use datalayer\MembershipType;
use datalayer\appexceptions\InvalidPersonException;
use api\model\RegistrationMgmt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $jsMemberData;

/** 
 * Manage People by responding to ajax requests from template
 * @class  ManagePeople
 * @package Tennis Club Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class ManagePeople
{ 
    const ACTION    = 'managePeople';
    const NONCE     = 'managePeople';

    private $registrationId = 0;
    private $errobj = null;
    private $errcode = 0;
    private $log;

    public static function register() {
        $handle = new self();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
        $handle->registerHandlers();
        
        global $jsMemberData;
        $jsMemberData = $handle->get_ajax_data();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();	
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        $jsurl =  TE()->getPluginUrl() . 'js/tennisregs.js';
        wp_register_script( 'manage_registrations', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-tabs'), TennisClubMembership::VERSION, true );

    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }
    
    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisClubMembership::TEXT_DOMAIN ));
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
        if( current_user_can( TM_Install::MANAGE_REGISTRATIONS_CAP ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Insufficient privileges to modify brackets.', TennisClubMembership::TEXT_DOMAIN ));
        }
        
        if(count($this->errobj->errors) > 0) {
            $this->handleErrors(__("Errors were encountered", TennisClubMembership::TEXT_DOMAIN  ) );
        }

        $this->log->error_log("$loc: action={$_POST['action']}");
        if( self::ACTION !== $_POST['action']) return;

        $response = array();

        $data = $_POST["data"];
        $task = $data["task"];
        $returnData = $task;
        $mess = '';
        switch( $task ) {
            case 'addPerson':
                $mess = $this->addPerson( $data );
                $returnData = $data;
                break;
            case 'deletePerson':
                $mess = $this->deletePerson( $data );
                $returnData = $data;
                break;
            default:
                $mess =  __( 'Illegal Event task.', TennisClubMembership::TEXT_DOMAIN );
                $this->errobj->add( $this->errcode++, $mess );
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors( $mess );
        }
        
        $response["message"] = $mess;
        $response["returnData"] = $returnData;

        //Send the response
        wp_send_json_success( $response );

        // All ajax handlers die when finished
        wp_die(); 
    }

    /**
     * Add a new Person
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function addPerson( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            			
            $current_user = wp_get_current_user();
            if ( $current_user->exists() ) {
                $author = $current_user->ID;
            }
            else {
                throw new InvalidArgumentException(__("User does not exist",TennisClubMembership::TEXT_DOMAIN));
            }

            $homeCorpId = TM()->getCorporationId();
            if (0 === $homeCorpId) {
                $this->log->error_log("$loc - Home corporate id is not set."); 
                throw new InvalidArgumentException(__("Home corporate id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            $this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidArgumentException(__( "Home corporation is not found({$homeCorpId}).", TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();
            $fname = array_key_exists('firstName',$data) ? $data['firstName'] : '';
            $lname = array_key_exists('lastName',$data) ? $data['lastName'] : '';

            /*
            `ID`            INT NOT NULL AUTO_INCREMENT,
			`corporate_ID   INT NOT NULL,
			`sponsor_ID`    INT NULL,
			`first_name`    VARCHAR(45) NULL,
			`last_name`     VARCHAR(45) NOT NULL,
			`gender`        VARCHAR(1) NOT NULL DEFAULT 'M',
			`birthdate`     DATE NULL,
			`skill_level`   DECIMAL(4,1) NULL DEFAULT 2.0,
			`emailHome`     VARCHAR(100),
			`emailBusiness` VARCHAR(100),
			`phoneHome`     VARCHAR(45),
			`phoneMobile`   VARCHAR(45),
			`phoneBusiness` VARCHAR(45),
			`notes`         VARCHAR(2000),
            */
            $person = Person::fromName($homeCorpId,$fname,$lname);
            $person->setGender(array_key_exists('gender',$data)  ? Genders::tryFrom($data['gender']) : Genders::Other );
            $person->setBirthDate_Str(array_key_exists('birthDay',$data) ? $data['birthDay'] : '');

            $homeEmail = array_key_exists('homeEmail',$data) ? $data['homeEmail'] : '';
            if(email_exists($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email already exists.', TennisClubMembership::TEXT_DOMAIN ));
            }
            $person->setHomeEmail($homeEmail);

            $person->setHomePhone(array_key_exists('homePhone',$data) ? $data['homePhone'] : '');
            $person->setMobilePhone(array_key_exists('mobilePhone',$data) ? $data['mobilePhone'] : '');
            $notes = array_key_exists('notes',$data) ? $data['notes'] : '';
            $person->setNotes(strip_tags($notes));

            //Setup the corresponding wp user
            $currentTime = new DateTime('NOW');
            $userMeta = array();
            $userData = array(
                        'user_pass'  => '??????????',
                        'user_login' => $homeEmail,
                        'first_name' => $fname,
                        'last_name'  => $lname, 
                        'user_email' => $homeEmail,
                        'user_registered' => $currentTime->format('Y-m-d H:i:s'),
                        'show_admin_bar_front' => false,
                        'role' => 'subscriber',
                        'meta_input' => $userMeta
            );
    
            $user_id = wp_insert_user( $userData ) ;
            if(is_wp_error($user_id)) {
                $mess = $user_id->get_error_message();
                throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }

            $person->addExternalRef((string)$user_id);
            $person->save();
            update_user_meta($user_id, TennisClubMembership::USER_PERSON_ID, $person->getID());
            $mess = __("Created new person '{$person->toString()}'.",TennisClubMembership::TEXT_DOMAIN);
        }
        catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
    
    /**
     * Delete a Person
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function deletePerson( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            			
            $current_user = wp_get_current_user();
            if ( $current_user->exists() ) {
                $author = $current_user->ID;
            }
            else {
                throw new InvalidArgumentException(__("User does not exist",TennisClubMembership::TEXT_DOMAIN));
            }

            $homeCorpId = TM()->getCorporationId();
            if (0 === $homeCorpId) {
                $this->log->error_log("$loc - Home corporate id is not set."); 
                throw new InvalidArgumentException(__("Home corporate id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            $this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidArgumentException(__( 'Home corporation is not found.', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();
            
            $personId = array_key_exists('personId',$data) ? $data['personId'] : 0;
            $person = Person::get($personId);
            $userId = $person->getExtRefSingle($personId);
    
            wp_delete_user( $userId ) ;

            $mess = __("Deleted '{$person->toString()}'.",TennisClubMembership::TEXT_DOMAIN);
            $person->delete();
        }
        catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
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
             'ajaxurl'  => admin_url( 'admin-ajax.php' )
            ,'action'   => self::ACTION
            ,'security' => wp_create_nonce( self::NONCE )
            ,'season'   => TM()->getSeason()
            ,'message'  => $mess
        );
    }

    private function handleErrors( string $mess ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $response = array();
        $response["message"] = $mess;
        $response["exception"] = $this->errobj;
        $this->log->error_log($response, "$loc: error response...");
        wp_send_json_error( $response );
        wp_die($mess);
    }
}