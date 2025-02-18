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
use datalayer\MemberRegistration;
use datalayer\MembershipType;
use datalayer\appexceptions\InvalidRegistrationException;
use api\model\RegistrationMgmt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $jsMemberData;

/** 
 * Manage Registrations by responding to ajax requests from template
 * @class  ManageRegistrations
 * @package Tennis Club Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageRegistrations
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageRegistrations';
    const NONCE     = 'manageRegistrations';

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
            case 'addRegistration':
                $mess = $this->addRegistration( $data );
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
     * Add a new registration
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function addRegistration( &$data ) {
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
                $this->log->error_log("$loc - Home corporration id is not set."); 
                throw new InvalidRegistrationException(__("Home corporation id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            $this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidRegistrationException(__( 'Home corporation is not set.', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();

            $personId = array_key_exists('personId', $data) ? $data['personId'] : 0;
            $person = Person::get($personId);
            if(is_null($person)) {
                $this->log->error_log("$loc: no such persond id '$personId'");
                throw new InvalidRegistrationException(__( 'Could not find person with this ID.', TennisClubMembership::TEXT_DOMAIN ));
            }
            $membershipTypeId = array_key_exists('membershipTypeId', $data) ? $data['membershipTypeId'] : 0;
            if(!MembershipType::isValidTypeId($membershipTypeId)) {
                $this->log->error_log("$loc: Invalid membership type ID '$membershipTypeId'");
                throw new InvalidRegistrationException(__( 'Invalid membership type ID .', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $reg = MemberRegistration::fromIds($season, $membershipTypeId, $personId);
            $reg->setReceiveEmails(array_key_exists('receiveEmails',$data) && $data['receiveEmails'] > 0 ? true : false );
            $reg->setIncludeInDir(array_key_exists('includeDir',$data) && $data['includeDir'] > 0 ? true : false );
            $reg->setShareEmail(array_key_exists('shareEmail',$data) && $data['shareEmail'] > 0 ? true : false );
            $dateStr = array_key_exists('startDate',$data) ? $data['startDate'] : '';
            $reg->setStartDate_Str($dateStr);
            $dateStr = array_key_exists('endDate',$data) ? $data['endDate'] : '';
            $reg->setEndDate_Str($dateStr);
            $notes = array_key_exists('notes',$data) ? $data['notes'] : '';
            $reg->setNotes(strip_tags($notes));

            //Setup the corresponding custom post type
            $currentTime = new DateTime('NOW');
            $postData = array(
                        'post_title' => $data['title'],
                        'post_status' => 'publish',
                        'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                        'post_content' => '',
                        'post_type' => ClubMembershipCpt::CUSTOM_POST_TYPE,
                        'post_author' => $author,
                        'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                        'post_modified' => $currentTime->format('Y-m-d G:i:s')
                        );
    
            $newPostId = wp_insert_post($postData, true);//NOTE: This triggers updateDB in TennisEventCpt
            if(is_wp_error($newPostId)) {
                $mess = $newPostId->get_error_message();
                throw new InvalidRegistrationException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }
            // update_post_meta( $newPostId, ClubMembershipCpt::EVENT_TYPE_META_KEY, $event->getEventType());
            // update_post_meta( $newPostId, ClubMembershipCpt::START_DATE_META_KEY, $startDate );
            // update_post_meta( $newPostId, ClubMembershipCpt::END_DATE_META_KEY, $endDate );
            		
            update_post_meta($newPostId, ClubMembershipCpt::MEMBERSHIP_SEASON, $season);

            $reg->addExternalRef((string)$newPostId);
            $reg->save();
            $mess = __("Created new registration '{$reg->toString()}'.",TennisClubMembership::TEXT_DOMAIN);
        }
        catch (RuntimeException | InvalidRegistrationException | InvalidArgumentException $ex ) {
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