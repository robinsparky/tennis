<?php
namespace api\ajax;

use TennisClubMembership;
use commonlib\BaseLogger;
use cpt\TennisMemberCpt;
use \WP_Error;
use \WP_User;
use TM_Install;
use \DateTime;
use \InvalidArgumentException;
use \RuntimeException;
use datalayer\Corporation;
use datalayer\Genders;
use datalayer\MemberRegistration;
use datalayer\Person;
use datalayer\Address;
use datalayer\appexceptions\InvalidPersonException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** 
 * Manage People by responding to ajax requests from template
 * @class  ManagePeople
 * @package Tennis Club Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class ManagePeople
{ 
    const ACTION    = 'managepeople';
    const NONCE     = 'managepeople';

    //User Meta Keys
    public const USER_CORP_ID      = 'user_corp_id';
    public const USER_PERSON_ID    = 'user_person_id';
    public const USER_SPONSOR_ID   = 'user_sponsor_id';
    public const USER_GENDER       = 'user_gender';
    public const USER_BIRTHDAY     = 'user_birthday';
    public const USER_PHONE        = 'user_phone';
    public const USER_LEGACY_DATA  = 'user_legacy';
    public const USER_EMERGENCY_PHONE = 'user_emergency_phone';

    public static $allUserMetaKeys = [self::USER_CORP_ID
                                    ,self::USER_PERSON_ID
                                    ,self::USER_BIRTHDAY
                                    ,self::USER_GENDER
                                    ,self::USER_PHONE
                                    ,self::USER_EMERGENCY_PHONE
                                    ,self::USER_LEGACY_DATA];

    // public const USER_ADDRESS      = 'user_address';
	// public const USER_SPONSOR_ID   = 'user_sponsor_id';
    // public const USER_INCLUDE_DIR  = 'user_include_dir';
    // public const USER_SHARE_EMAIL  = 'user_share_email';
    // public const USER_SKILL        = 'user_skill';
    // public const USER_NOTES        = 'user_notes';

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
        
        $jsurl =  TE()->getPluginUrl() . 'js/tennismems.js';
        wp_register_script( 'managepeople', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-tabs'), TennisClubMembership::VERSION, true );

    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_action('delete_user', array($this,'deleteUserData'));
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
        if(false==check_ajax_referer( self::NONCE, 'security' )) {  
            $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisClubMembership::TEXT_DOMAIN ));
            $this->handleErrors("You've been a bad boy.");
        }

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        $ok = false;
        if( current_user_can( TM_Install::MANAGE_REGISTRATIONS_CAP ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Insufficient privileges to manage registrations.', TennisClubMembership::TEXT_DOMAIN ));
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
            case 'convertusers':
                $mess = $this->convertUsers( $data );
                $returnData = $data;
                break;
            case 'addPerson':
                $mess = $this->addPerson( $data );
                $returnData = $data;
                break;
            case 'modifyPerson':
                $mess = $this->modifyPerson( $data );
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
     * Convert a user from Jegysoft
     * @param array $data A reference to a dictionary containing user and registration data
     * @return string A message describing success or failure
     */
    private function convertUsers( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $users = [];
        if(array_key_exists("users",$data)) {
            $this->log->error_log($data['users'],"$loc: data...");
            $users = $data["users"];
        }
        else {
            $this->log->error_log("$loc: no users..............................................................");
        }

        $task = $data["task"];
        $mess = "";
        $numFailed = 0;
        $numSuccess = 0;
        $numTotal = 0;
        $homeCorpId = TM()->getCorporationId();

        foreach($users as $upload) {
            ++$numTotal;
            try {
                $email = $upload['email'] ?? '';
                if(empty($email)) {
                    throw new InvalidArgumentException(__( 'Email cannot be blank.', TennisClubMembership::TEXT_DOMAIN ));
                }
                if(email_exists($email)) {
                    $mess = __("Email '{$email}' already exists ",TennisClubMembership::TEXT_DOMAIN);
                    throw new InvalidPersonException($mess);
                }

                $gender = $upload['gender'];
                $this->log->error_log("$loc: gender = '{$gender}'==============================================");
                if(!isset($gender)) continue;

                $birthdate = $upload['birthdate'] ?? '';
                $firstName = $upload['firstname'] ?? '';
                $lastName = $upload['lastname'] ?? '';
                $memNum = $upload['membernumber'] ?? '';
                $portal = $upload['portal'] ?? '';
                $fob = $upload['fob'] ?? '';
                $mlyr = $upload['memberlastyear'] ?? '';
                $status = $upload['status'] ?? '';
                $legacy = array('membernumber'=>$memNum,'fob'=>$fob,'memberlastyear'=>$mlyr,'status'=>$status,'portal'=>$portal);

                //Setup Person
                $person = Person::fromName($homeCorpId,$firstName,$lastName);
                $person->setBirthDate_Str($birthdate);
                $person->setHomeEmail($email);
                $person->setGender($gender);
                $person->setBirthDate_Str($birthdate);
                $person->isValid();
                
                $expire = $upload['expirydate'] ?? '';
                $role = TM_Install::PUBLICMEMBER_ROLENAME;
                if(!empty($expire)) {
                    $expire = new DateTime($expire);
                    if(2025 === (int)$expire->format('Y')) $role = TM_Install::TENNISPLAYER_ROLENAME;
                    $season = $expire->format('Y');
                }

                //Setup the corresponding wp_user
                $userName = $firstName;
                if(false !=  username_exists($userName)) {
                    $userName = $firstName . '_' . substr($lastName,0,1);
                    if(false !=  username_exists($userName)) {
                        $userName = $firstName . '_' . substr($lastName,0,2); 
                        if(false !=  username_exists($userName)) {
                            $userName = $email;
                        }
                    }
                }
                $currentTime = new DateTime('NOW');
                $userMeta = array(ManagePeople::USER_CORP_ID=>$homeCorpId
                                ,ManagePeople::USER_GENDER=>$gender
                                ,ManagePeople::USER_LEGACY_DATA=>$legacy);
                $random_password = wp_generate_password( 12, true, false );
                $userData = array(
                            'user_pass'  => $firstName, //$random_password,
                            'user_login' => $userName,
                            'first_name' => $firstName,
                            'last_name'  => $lastName, 
                            'user_email' => $email,
                            'user_registered' => $currentTime->format('Y-m-d H:i:s'),
                            'show_admin_bar_front' => true,
                            'role' => $role,
                            'meta_input' => $userMeta
                );
        
                $user_id = wp_insert_user( $userData );
                if(is_wp_error($user_id)) {
                    $mess = $user_id->get_error_message();
                    throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
                }

                //Setup the corresponding custom post type
                $content = $person->getName();
                $title = get_user_by('ID',$user_id)->user_login;
                $postData = array(
                            'post_title' => $title,
                            'post_author'=> $user_id,
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
                    throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
                }
                $person->addExternalRef($newPostId);
                $person->save();
                update_user_meta($user_id,ManagePeople::USER_PERSON_ID,$person->getID());
                update_post_meta($newPostId, ManagePeople::USER_PERSON_ID, $person->getID());
                $this->log->error_log("Created new user '{$person->getID()}/{$user_id}: {$firstName} {$lastName}'.");
                ++$numSuccess;
            }
            catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
                ++$numFailed;
                $this->errobj->add( $this->errcode++, $ex->getMessage() );
                $this->log->error_log("$loc: Error - " . $ex->getMessage());
            }
        } //end foreach
        $mess = __("{$numTotal} users; {$numSuccess} Succeeded; {$numFailed} Failed .",TennisClubMembership::TEXT_DOMAIN);
        return $mess;
    }

    /**
     * Add a new Person/User
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function addPerson( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
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

            $firstName = $data['firstname']  ?? '';
            $lastName = $data['lastname'] ?? '';
            $gender = $data['gender'] ?? Genders::Other;
            $birthday = $data['birthday'] ?? '';
            $homeEmail = $data['email'] ?? '';
            if(empty($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email cannot be blank.', TennisClubMembership::TEXT_DOMAIN ));
            }
            if(email_exists($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email already exists.', TennisClubMembership::TEXT_DOMAIN ));
            }

            $phone = $data['phone'] ?? '';
            $emergPhone = $data['emergencyphone'] ?? '';
            $notes = $data['notes'] ?? '';
            //Get Address data
            $street1 = $data['street1'] ?? '';
            $street2 = $data['street2'] ?? '';
            $city    = $data['city'] ?? '';
            $prov    = $data['province'] ?? 'Ontario';
            $postal  = $data['postal'] ?? '';

            $address = new Address();
            $address->setAddr1($street1);
            $address->setAddr2($street2);
            $address->setCity($city);
            $address->setProvince($prov);
            $address->setPostalCode($postal);

            $role = $data['role'] ?? TM_Install::PUBLICMEMBER_ROLENAME;
            $goodRole = false;
            foreach(TM_Install::$tennisRoles as $slug => $rl) {
                if($role === $rl) {
                    $goodRole = true;
                    break;
                }
            }
            if(!$goodRole) $role = TM_Install::PUBLICMEMBER_ROLENAME;

            //Setup Person
            $person = Person::fromName($homeCorpId,$firstName,$lastName);
            $person->setBirthDate_Str($birthday);
            $person->setHomeEmail($homeEmail);
            $person->setHomePhone($phone);
            $person->setGender($gender);
            //$person->setEmergencyPhone($emergPhone);
            $person->setNotes(strip_tags($notes));
            $person->isValid();//throws InvalidPersonException if not valid

            //Setup the corresponding wp user
            $userName = $firstName;
            if(false !=  username_exists($userName)) {
                $userName = $firstName . '_' . substr($lastName,0,1);
                if(false !=  username_exists($userName)) {
                    $userName = $firstName . '_' . substr($lastName,0,2); 
                    if(false !=  username_exists($userName)) {
                        $userName = $firstName . '_' . substr($lastName,0,3); 
                        if(false != username_exists($userName)) {
                            $userName = $homeEmail;
                        }
                    }
                }
            }
            $currentTime = new DateTime('NOW');
            $userMeta = array(ManagePeople::USER_CORP_ID=>$homeCorpId
                            ,ManagePeople::USER_GENDER=>$gender);
            $random_password = wp_generate_password( 12, true, false );
            $userData = array(
                        'user_pass'  => $firstName, //$random_password,
                        'user_login' => $userName,
                        'first_name' => $firstName,
                        'last_name'  => $lastName, 
                        'user_email' => $homeEmail,
                        'user_registered' => $currentTime->format('Y-m-d H:i:s'),
                        'show_admin_bar_front' => false,
                        'role' => $role,
                        'meta_input' => $userMeta
            );
    
            $user_id = wp_insert_user( $userData );
            if(is_wp_error($user_id)) {
                $mess = $user_id->get_error_message();
                throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }
            
            //Setup the corresponding custom post type
            $content = $person->getName();
            $title = get_user_by('ID',$user_id)->user_login;
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
                throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }
            $person->addExternalRef($newPostId);
            $person->save();
            update_user_meta($user_id,ManagePeople::USER_PERSON_ID,$person->getID());
            update_post_meta($newPostId, ManagePeople::USER_PERSON_ID, $person->getID());
            $address->setOwnerId($person->getID());
            $address->save();
            $mess = __("Created new user {$person->getID()}/{$user_id}: '{$firstName} {$lastName}'.",TennisClubMembership::TEXT_DOMAIN);
        }
        catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
        
    /**
     * Modify a Person/User
     * @param array $data A reference to a dictionary containing registration data
     * @return string A message describing success or failure
     */
    private function modifyPerson( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            $homeCorpId = TM()->getCorporationId();
            if (0 === $homeCorpId) {
                $this->log->error_log("$loc - Home corporate id is not set."); 
                throw new InvalidArgumentException(__("Home corporate id is not set.",TennisClubMembership::TEXT_DOMAIN));
            }
            $this->log->error_log("$loc: Corporation Id={$homeCorpId}");
            $corp = Corporation::get($homeCorpId);
            if( !isset($corp) ) {				
                throw new InvalidArgumentException(__( "Home corporation is not found ({$homeCorpId}).", TennisClubMembership::TEXT_DOMAIN ));
            }

            $personId = (int)$data['personId'] ?? 0;
            $person = Person::get($personId);
            if(null == $person) {
                throw new InvalidArgumentException(__( "Person is not found ({$personId}).", TennisClubMembership::TEXT_DOMAIN ));
            }

            $firstName = $data['firstname'] ?? $person->getFirstName();
            $lastName = $data['lastname'] ?? $person->getLastName();
            $homeEmail = $data['email'] ?? $person->getHomeEmail();
            if(empty($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email cannot be blank.', TennisClubMembership::TEXT_DOMAIN ));
            }
            $user = get_user_by('user_email',$homeEmail);
            if(!$user instanceof WP_User) {
                $mess = __("No such user with email '{$homeEmail}'",TennisClubMembership::TEXT_DOMAIN);
                throw new InvalidArgumentException($mess);
            }
 
            //Modify person data
            $gender = $data['gender'] ?? $person->getGender();
            $birthday = $data['birthday'] ?? $person->getBirthDate_Str();
            $phone = $data['phone'] ?? $person->getHomePhone();
            $notes = $data['notes'] ?? $person->getNotes();
            $person->setHomeEmail($homeEmail);
            $person->setGender($gender);
            $person->setBirthDate_Str($birthday);
            $person->setHomePhone($phone);
            $person->setNotes(strip_tags($notes));

            //Modify Address data
            $addressId = (int)$data['addressId'] ?? $person->getAddress()->getID();
            $street1 = $data['street1'] ?? '';
            $street2 = $data['street2'] ?? '';
            $city    = $data['city'] ?? '';
            $prov    = $data['province'] ?? 'Ontario';
            $postal  = $data['postal'] ?? '';
            $address = Address::get($addressId) ?? new Address();
            if(!empty($street1)) $address->setAddr1($street1);
            if(!empty($street2)) $address->setAddr2($street2);
            if(!empty($city)) $address->setCity($city);
            if(!empty($prov)) $address->setProvince($prov);
            if(!empty($postal)) $address->setPostalCode($postal);

            //Update the wp user
            $userData = array(
                        'ID'         => $user->ID,
                        'first_name' => $firstName,
                        'last_name'  => $lastName 
            );
    
            $user_id = wp_update_user( $userData );
            if(is_wp_error($user_id)) {
                $mess = $user_id->get_error_message();
                throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
            }

            //Update the custom post type
            $postId = (int)$person->getExtRefSingle();
            $content = $person->getName();
            $postData = ['ID'=>$postId,"post_content"=>$content];
            $pid = wp_update_post($postData,true);
            if(is_wp_error($pid)){
                $mess = $pid->get_error_message();
                throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));                
            }
            $person->save();
            $address->setOwnerId($person->getID());
            $address->save();
            update_user_meta($user_id, ManagePeople::USER_PERSON_ID,$person->getID());
            update_user_meta($user_id, ManagePeople::USER_GENDER,$person->getGender());
            update_post_meta($postId, ManagePeople::USER_PERSON_ID,$person->getID());
            $mess = __("Modified user {$person->getID()}/{$user_id}: '{$firstName} {$lastName}'.",TennisClubMembership::TEXT_DOMAIN);
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
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();
            
            $personId = $data['personId'] ?? 0;
            $person = Person::get($personId);
            $userId = 0;
            if(null != $person) {
                $email = $person->getHomeEmail();
                $user = get_user_by('email',$email);
                $userId = $user->ID;
                if($user instanceof WP_User) {
                    wp_delete_user($user->ID); //see deleteUserData
                }
                $person->delete();
                $mess = __("Deleted '{$personId}/{$userId}'.",TennisClubMembership::TEXT_DOMAIN);
            }
            else {
                $mess = __("Failed to Delete '{$personId}/{$userId}'.",TennisClubMembership::TEXT_DOMAIN);
            }
        }
        catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
      
    /**
     * Action fires just before a user is deleted
     * @param int $user_id The id of the WP user
     */
    public function deleteUserData( $user_id) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc({$user_id})");

        foreach(self::$allUserMetaKeys as $key) {
            delete_user_meta($user_id, $key);
        }
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