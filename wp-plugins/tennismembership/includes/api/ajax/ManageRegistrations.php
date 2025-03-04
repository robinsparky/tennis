<?php
namespace api\ajax;

use TennisClubMembership;
use commonlib\BaseLogger;
use \WP_Error;
use TM_Install;
use \DateTime;
use \InvalidArgumentException;
use \RuntimeException;
use datalayer\Genders;
use cpt\ClubMembershipCpt;
use datalayer\Corporation;
use datalayer\Person;
use datalayer\MemberRegistration;
use datalayer\RegStatus;
use datalayer\MembershipType;
use datalayer\appexceptions\InvalidRegistrationException;
use api\model\RegistrationMgmt;
use datalayer\appexceptions\InvalidPersonException;
use WpOrg\Requests\Exception\InvalidArgument;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $jsRegistrationData;

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
        
        global $jsRegistrationData;
        $jsRegistrationData = $handle->get_ajax_data();
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
            case 'addregistration':
                $mess = $this->addRegistration( $data );
                $returnData = $data;
                break;
            case 'convertregistrations':
                $mess = $this->convertRegistrations( $data );
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
                throw new InvalidArgumentException(__("user not logged in",TennisClubMembership::TEXT_DOMAIN));
            }
            
            $homeCorpId = TM()->getCorporationId();
            if (0 === $homeCorpId) {
                $this->log->error_log("$loc - Home corporration id is not set."); 
                throw new InvalidRegistrationException(__("Home corporation id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            $this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidRegistrationException(__( 'Home corporation is not found.', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();

            $personId = array_key_exists('personId', $data) ? $data['personId'] : 0;
            $person = Person::get($personId);
            //$person = get_user_by('ID',$personId);
            if(is_null($person)) {
                $this->log->error_log("$loc: no such person id '$personId'");
                throw new InvalidRegistrationException(__( 'Could not find person with this ID.', TennisClubMembership::TEXT_DOMAIN ));
            }
            
            $email = $person->getHomeEmail();
            $user = get_user_by('email',$email);
            if(false === $user) {
                $this->log->error_log("$loc: no such user email '{$email}'");
                throw new InvalidRegistrationException(__( 'Could not find person with this email address.', TennisClubMembership::TEXT_DOMAIN ));
            }
            $title = $user->user_login;

            $membershipTypeId = $data['membershipTypeId'] ?? 0;
            if(!MembershipType::isValidTypeId($membershipTypeId)) {
                $this->log->error_log("$loc: Invalid membership type ID '$membershipTypeId'");
                throw new InvalidRegistrationException(__( 'Invalid membership type ID .', TennisClubMembership::TEXT_DOMAIN ));
            }
            $memType = MemberShipType::getTypeById($membershipTypeId);
            
            $reg = MemberRegistration::fromIds($season, $membershipTypeId, $personId);
            $reg->setReceiveEmails(array_key_exists('receiveEmails',$data) && $data['receiveEmails'] > 0 ? true : false );
            $reg->setIncludeInDir(array_key_exists('includeDir',$data) && $data['includeDir'] > 0 ? true : false );
            $reg->setShareEmail(array_key_exists('shareEmail',$data) && $data['shareEmail'] > 0 ? true : false );
            $startDateStr = $data['startDate'] ?? '';
            $reg->setStartDate_Str($startDateStr);
            $expiryDateStr =  $data['endDate'] ?? '';
            $reg->setEndDate_Str($expiryDateStr);
            $notes = $data['notes'] ?? '';
            $reg->setNotes(strip_tags($notes));

            //Setup the corresponding custom post type
            $content = $person->getName() . ', ' . $memType->getName() . ', ' . $season;
            $currentTime = new DateTime('NOW');
            $postData = array(
                        'post_title' => $title,
                        'post_status' => 'publish',
                        'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                        'post_content' => $content,
                        'post_type' => ClubMembershipCpt::CUSTOM_POST_TYPE,
                        'post_author' => get_current_user_id(),
                        'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                        'post_modified' => $currentTime->format('Y-m-d G:i:s')
                        );
    
            $newPostId = wp_insert_post($postData, true);//NOTE: This triggers updateDB in TennisEventCpt
            if(is_wp_error($newPostId)) {
                $mess = $newPostId->get_error_message();
                throw new InvalidRegistrationException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }
            
            $reg->addExternalRef((string)$newPostId);
            $reg->save();
            update_post_meta($newPostId, ClubMembershipCpt::REGISTRATION_ID, $reg->getID());
            update_post_meta($newPostId, ClubMembershipCpt::MEMBERSHIP_SEASON, $season);
            $mess = __("Created new registration '{$reg->toString()}'.",TennisClubMembership::TEXT_DOMAIN);
        }
        catch (RuntimeException | InvalidRegistrationException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
        
    /**
     * Convert a registration from Jegysoft
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function convertRegistrations( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $regs = [];
        if(array_key_exists('registrations',$data)) {
            $this->log->error_log($data['registrations'],"$loc: registrations...");
            $regs=$data['registrations'];
        }
        else {
            $this->log->error_log("$loc: no registrations uploaded");   
        }

        $task = $data["task"];
        $mess = "";
        $numTotal = 0;
        $numFailed = 0;
        $numSuccess = 0;
        $homeCorpId = TM()->getCorporationId();
        $users = get_users();
        $numUsers = count($users);
        $this->log->error_log("$loc: number of users is $numUsers");

        foreach($regs as $upload) {
            ++$numTotal;
            try {
                $firstName = $upload['firstname'] ?? '';
                $lastName = $upload['lastname'] ?? '';
                $email = $upload['email'] ?? '';

                //Find wp user
                $targetUser = null;
                foreach($users as $user) {
                        if($user->user_email == $email) {
                            $targetUser = $user;
                            $this->log->error_log("$loc: found auther/user $user->ID with email $user->user_email");
                            break;
                        }
                }
                if(is_null($targetUser) ) {
                    $mess = __("Could not find wp user for '{$firstName} {$lastName}' using email '{$email}'",TennisClubMembership::TEXT_DOMAIN);
                    throw new InvalidArgumentException($mess);
                }

                $person = Person::find(["email"=>$email])[0] ?? null;
                if(null === $person) {
                    $mess = __("Could not find Person '{$firstName} {$lastName}' using email '{$email}'",TennisClubMembership::TEXT_DOMAIN);
                    throw new InvalidArgumentException($mess);
                }

                $regType = $upload['regtype'] ?? '';
                $this->log->error_log("$loc: regType='{$regType}'");
                if(!MembershipType::IsValidType($regType)) {
                    $this->log->error_log("$loc: Invalid membership type '$regType'");
                    throw new InvalidRegistrationException(__( 'Invalid membership type.', TennisClubMembership::TEXT_DOMAIN ));
                }
                //Temporary
                switch($regType) {
                    case 'Adult':
                        $memTypeId = 1;
                        break;
                    case 'Couples':
                        $memTypeId = 2;
                        break;
                    case 'Family':
                        $memTypeId = 3;
                        break;
                    case 'Student':
                        $memTypeId = 4;
                        break;
                    case 'Junior':
                        $memTypeId = 5;
                        break;
                    case 'Public':
                        $memTypeId = 6;
                        break;
                    case 'Parent':
                        $memTypeId = 7;
                        break;
                    case 'Staff':
                        $memTypeId = 8;
                        break;
                    case 'Instructor':
                        $memTypeId = 9;
                        break;
                }
                // $memType = MemberShipType::getTypeByName($regType);
                // $this->log->error_log($memType,"$loc: Membership Type ...");
                $status = $upload['status'] ?? '';
                $regStatus = RegStatus::tryFrom($status) ?? RegStatus::Inactive;
                $expire = $upload['expirydate'] ?? '';
                $season = '';
                if(!empty($expire)) {
                    $expire = new DateTime($expire);
                    $season = $expire->format('Y');
                }

                if(empty($season)) {
                    $this->log->error_log("$loc: Invalid season because {$firstName} {$lastName}  had no expiry date.");
                    continue;
                }

                $reg = MemberRegistration::fromIds($season, $memTypeId, $person->getID());
                //$reg->setCorpId($homeCorpId);
                $reg->setReceiveEmails(array_key_exists('receiveEmails',$upload) && (int)$upload['receiveEmails'] > 0 ? true : false );
                $reg->setIncludeInDir(array_key_exists('includeDir',$upload) && (int)$upload['includeDir'] > 0 ? true : false );
                $reg->setShareEmail(array_key_exists('shareEmail',$upload) && (int)$upload['shareEmail'] > 0 ? true : false );
                $start = $upload['startdate'] ?? '';
                $reg->setStartDate_Str($start);
                $reg->setEndDate($expire);
                $notes = $upload['notes'] ?? '';
                $reg->setNotes(strip_tags($notes));
                $reg->setStatus($regStatus);
                $reg->isValid();

                //Setup the corresponding custom post type
                $currentTime = new DateTime('NOW');
                $title = sprintf("%s %s %s",$firstName,$lastName,$season);            
                $content = $person->getName() . ', ' . $regType . ', ' . $season;

                $postData = array(
                            'post_type' => ClubMembershipCpt::CUSTOM_POST_TYPE,
                            'post_title' => $title,
                            'post_author' => $targetUser->ID,
                            'post_status' => 'publish',
                            'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                            'post_content' => $content,
                            'post_author' => get_current_user_id(),
                            'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                            'post_modified' => $currentTime->format('Y-m-d G:i:s')
                            );

                $newPostId = wp_insert_post($postData, true);//NOTE: This triggers updateDB in cpt's
                if(is_wp_error($newPostId)) {
                    $mess = $newPostId->get_error_message();
                    throw new InvalidRegistrationException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
                }
                $postData = ["ID"=>$newPostId, "post_author"=>$targetUser->ID];
                $res = wp_update_post($postData,true,true);
                if(is_wp_error($res)) {
                    $mess = $res->get_error_message();
                    throw new InvalidRegistrationException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
                }
                
                $reg->addExternalRef((string)$newPostId);
                $reg->save();
                update_post_meta($newPostId, ClubMembershipCpt::REGISTRATION_ID, $reg->getID());
                update_post_meta($newPostId, ClubMembershipCpt::MEMBERSHIP_SEASON, $season);
                $this->log->error_log("Created new registration '{$reg->toString()}' for '{$firstName} {$lastName}'.");
                ++$numSuccess;
            }
            catch (RuntimeException | InvalidRegistrationException | InvalidArgumentException $ex ) {
                ++$numFailed;
                $this->errobj->add( $this->errcode++, $ex->getMessage() );
                $this->log->error_log("$loc: Error - " . $ex->getMessage());
            }
        } //end foreach
        $mess = __("{$numTotal} registrations; {$numSuccess} Succeeded; {$numFailed} Failed .",TennisClubMembership::TEXT_DOMAIN);
        $this->log->error_log("$loc: $mess");
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
            ,'corporateId' => TM()->getCorporationId()
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