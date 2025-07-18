<?php
namespace api\ajax;

use TennisClubMembership;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use cpt\TennisMemberCpt;
use \WP_Error;
use \WP_User;
use \WP_Post;
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
use datalayer\ExternalMapping;

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
    const ACTION    = 'manageusermembers';
    const NONCE     = 'manageusermembers';

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

        add_action('deleted_user', array($this,'deletedUser'));

        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
        
        /**Workflow support */
        add_action('register_form', array($this,'registrationForm' ));
        add_filter('registration_errors',array($this,'registrationErrors'),10,3);
        add_action('user_register',array($this,'registerNewUser'),10,2);
        add_action('register_post',array($this,'registrationData'),10,3);
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
            case 'updatePerson':
                $mess = $this->updatePerson( $data );
                $returnData = $data;
                break;
            case 'updateAddress':
                $mess = $this->updateAddress( $data );
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
                            'show_admin_bar_front' => 'true',
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

            $homeEmail = $data['homeEmail'] ?? '';
            if(empty($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email cannot be blank.', TennisClubMembership::TEXT_DOMAIN ));
            }
            if(email_exists($homeEmail)) {
                throw new InvalidArgumentException(__( 'Email already exists.', TennisClubMembership::TEXT_DOMAIN ));
            }

            //Check if this is a sponsored person
            $sponsor = null;
            $sponsorId = $data['personId'] ?? 0;
            if(!empty($sponsorId)) {
                $sponsor = Person::get($sponsorId);
                if(!isset($sponsor)) {
                    throw new InvalidArgumentException(__( "Sponsor person is not found({$sponsorId}).", TennisClubMembership::TEXT_DOMAIN ));
                }
            }
            $firstName = $data['firstName']  ?? '';
            $lastName = $data['lastName'] ?? '';
            $gender = $data['gender'] ?? Genders::Other;
            $genderdd = Genders::getGendersDropDown($gender);
            $data['genderdd'] = $genderdd;
            $birthday = $data['birthDate'] ?? '';
            $homePhone = $data['homePhone'] ?? '';
            $emergPhone = $data['emergencyPhone'] ?? '';
            $notes = $data['notes'] ?? '';

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
            if($sponsorId > 0) $person->setSponsorId($sponsorId);
            $person->setBirthDate_Str($birthday);
            $age = 0;
            $now = new Datetime('now');
            $bd = $person->getBirthDateTime();
            if(!empty($bd)) {
                $df = $now->diff($bd);
                $age = $df->y + $df->m/12.0 + $df->d/365.0;
                $age = round($age,2);
            }
            $data['age'] = $age;
            $person->setHomeEmail($homeEmail);
            $person->setHomePhone($homePhone);
            $person->setBusinessPhone($emergPhone);
            $person->setGender($gender);
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
                throw new InvalidPersonException(__("$mess",TennisClubMembership::TEXT_DOMAIN));
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
            if($sponsorId > 0) $data['sponsoredId'] = $person->getID();
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
    private function updatePerson( &$data ) {
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
                throw new InvalidArgumentException(__( "Home corporation ({$homeCorpId}) is not found.", TennisClubMembership::TEXT_DOMAIN ));
            }

            $personId = (int)$data['personId'] ?? 0;
            $person = Person::get($personId);
            if(null == $person) {
                throw new InvalidArgumentException(__( "Person ({$personId}) is not found.", TennisClubMembership::TEXT_DOMAIN ));
            }

            $homeEmail = $data['homeEmail'] ?? $person->getHomeEmail();
            $oldHomeEmail = $data['oldHomeEmail'] ?? '';
            if(empty($homeEmail) && empty($oldHomeEmail)) {
                throw new InvalidArgumentException(__( 'Email cannot be blank.', TennisClubMembership::TEXT_DOMAIN ));
            }

            if(email_exists($homeEmail)) {
                $mess = __("New Email '{$homeEmail}' exists ",TennisClubMembership::TEXT_DOMAIN);
                $this->log->error_log("$loc: $mess");
            }
            if(email_exists($oldHomeEmail)) {
                $mess = __("Old Email '{$oldHomeEmail}' exists ",TennisClubMembership::TEXT_DOMAIN);
                $this->log->error_log("$loc: $mess");
            }

            $user = GW_Support::getUserByEmail($homeEmail);
            if(!$user) {
                $user = GW_Support::getUserByEmail($oldHomeEmail);
            }
            else {
                $mess = __("New Email '{$homeEmail}' exists ",TennisClubMembership::TEXT_DOMAIN);
                $this->log->error_log("$loc: $mess");
            }

            if(!$user) {
                $mess = __("No such user with new email '{$homeEmail}' or old email '{$oldHomeEmail}'",TennisClubMembership::TEXT_DOMAIN);
                throw new InvalidArgumentException($mess);
            }

            $isSponsored = $person->getSponsorId() > 0;
            $data['sponsored'] = $isSponsored;

            $firstName = $data['firstName'] ?? $person->getFirstName();
            $lastName = $data['lastName'] ?? $person->getLastName();
            $gender = $data['gender'] ?? $person->getGender();
            $birthday = $data['birthDate'] ?? $person->getBirthDate_Str();
            $phone = $data['homePhone'] ?? $person->getHomePhone();
            $notes = $data['notes'] ?? $person->getNotes();
 
            //Modify person data
            $person->setFirstName($firstName);
            $person->setLastName($lastName);
            $person->setHomeEmail($homeEmail);
            $person->setGender($gender);
            $person->setBirthDate_Str($birthday);
            $person->setHomePhone($phone);
            $person->setNotes(strip_tags($notes));

            //Update the wp user
            $userData = array(
                        'ID'         => $user->ID,
                        'user_email' => $homeEmail,
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

    private function updateAddress( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            $personId = (int)$data['personId'] ?? 0;
            $person = Person::get($personId);
            if(null == $person) {
                throw new InvalidArgumentException(__( "Person is not found ({$personId}).", TennisClubMembership::TEXT_DOMAIN ));
            }
            
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
            $address->setOwnerId($person->getID());
            $address->isValid(); //throws InvalidPersonException if not valid
            $person->save();
            $mess = __("Modified address for person {$person->getID()}.",TennisClubMembership::TEXT_DOMAIN);
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
    public function deletePerson( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            $season = array_key_exists('season', $data) ? $data['season'] : 0;
            if(0 === $season) $season = TM()->getSeason();
            
            $personId = $data['personId'] ?? 0;
            $person = Person::get($personId);
            if(null == $person) {
                throw new InvalidArgumentException(__( "Person is not found ({$personId}).", TennisClubMembership::TEXT_DOMAIN ));
            }
            $email = $person->getHomeEmail();
            $user = GW_Support::getUserByEmail($email);
            $this->deleteMemberCPT($person);//delete the custom post type
            $person->delete();
            $userId = 0;
            if($user instanceof WP_User) {
                $userId = $user->ID;
                //delete the meta data for the user
                foreach(self::$allUserMetaKeys as $key) {
                    delete_user_meta($userId, $key);
                }
                wp_delete_user($userId);
            }
            $mess = __("Deleted '{$personId}/{$userId}'.",TennisClubMembership::TEXT_DOMAIN);
   
        }
        catch (RuntimeException | InvalidPersonException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
      
    /**
     * Action fires just after a user is deleted
     * This function deletes the custom post type and the person
     * @param int $user_id The id of the WP user
     */
    public function deletedUser( $user_id) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc({$user_id})");

        $personID = (int)ExternalMapping::fetchInternalIds(Person::$tablename,$user_id);
        if(empty($personID)) {
            $this->log->error_log("$loc: No person ID found for user '{$user_id}'.");
            return;
        }   
        $person = Person::get($personID);
        if($person instanceof Person) {
            //delete the custom post type
            $this->deleteMemberCPT($person);
            //delete the person
            $person->delete();
            $this->log->error_log("$loc: Deleted person '{$personID}' for user '{$user_id}'.");
        }
        else {
            $this->log->error_log("$loc: No person found for user '{$user_id}'.");
        }

        //delete the meta data for the user
        foreach(self::$allUserMetaKeys as $key) {
            delete_user_meta($user_id, $key);
        }
    }

    /**
     * Delete the custom post type
     * @param Person $person The person whose custom post type is to be deleted
     * @return bool True if the post was deleted, false otherwise
     */
    public function deleteMemberCPT( Person $person ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc({$person->getID()})");
        $result = false;
        $externalRef = (int)$person->getExtRefSingle();
        if(!empty($externalRef)) {
            $post = get_post($externalRef);
            if($post instanceof WP_Post) {
                if($post->post_type === TennisMemberCpt::CUSTOM_POST_TYPE) {
                    $this->log->error_log("$loc: Deleting post {$externalRef} for person {$person->getID()}");
                    wp_delete_post($externalRef,true);
                    $result = true;
                }
                else {
                    $this->log->error_log("$loc: Post {$externalRef} is not a TennisMemberCpt post type.");
                }
            }
        }
        return $result;
    }


    /**
     * This section is used to register a new user via the registration form.
     */

    //Invoke the registration form
    public function registrationForm() {
        if(!is_user_logged_in()) {
            if(get_option('users_can_register')) {
                $output = $this->getRegistrationForm();
            }
            else {
                $output= __('User registration is not enabled',TennisClubMembership::TEXT_DOMAIN);
            }
            return $output;
        }
    }

    private function getRegistrationForm() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		static $ctr = 0;
        //ob_start(); 
        ?>
		<h3 class="membership_header"><?php _e('New Account Data',TennisClubMembership::TEXT_DOMAIN); ?></h3>
        <?php
			++$ctr;
			$genderSelect = Genders::getGendersDropDown();
			$this->log->error_log("$loc: ctr=$ctr");
        ?>
<p>
    <label for="membership_user_first"><?php _e('First Name',TennisClubMembership::TEXT_DOMAIN); ?></label>
    <input name="membership_user_first" id="membership_user_first" type="text" class="membership user_first" />
</p>
<p>
    <label for="membership_user_last"><?php _e('Last Name',TennisClubMembership::TEXT_DOMAIN); ?></label>
    <input name="membership_user_last" id="membership_user_last" type="text" class="membership user_last"/>
</p>
<p>
    <label>Gender: 
    <?php echo $genderSelect;?>
    </label>
</p>
    <label for="membership_user_birthdate"><?php _e('Birthdate',TennisClubMembership::TEXT_DOMAIN); ?></label>
    <input name="membership_user_birthdate" id="membership_user_birthdate" class="membership birthdate" type="date"/>
</p>
<p>
    <label for="password"><?php _e('Password',TennisClubMembership::TEXT_DOMAIN); ?></label>
    <input name="membership_user_pass" id="password" class="membership password" type="password"/>
</p>
<p>
    <label for="password_again"><?php _e('Password Again',TennisClubMembership::TEXT_DOMAIN); ?></label>
    <input name="membership_user_pass_confirm" id="password_again" class="membership password_again" type="password"/>
</p>
<p>
    <input type="hidden" name="membership_csrf" value="<?php echo wp_create_nonce('membership'); ?>"/>
</p>
        <?php
		$this->log->error_log("$loc: form was rendered!");
    }

    // register a new user
    public function registerNewUser( $user_id, $user_data ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc($user_id)");
        $this->log->error_log($user_data,"$loc: user_data");
		$this->log->error_log($_POST,"$loc: _POST");

		$nonce = $_POST['membership_csrf'] ?? '';
		if(wp_verify_nonce($nonce, 'membership')) {
			$this->log->error_log("$loc: nonce was verified!!!!");
		}
		else {
			$this->log->error_log("$loc: nonce was NOT verified!!!!");
			return;
		}

        $user_first 	= $_POST["membership_user_first"];
        $user_last	 	= $_POST["membership_user_last"];
        $user_gender 	= $_POST["user_gender"][0];
        $user_birthdate	= $_POST["membership_user_birthdate"];
        $user_pass		= $_POST["membership_user_pass"];
        
        //Setup Person
        $homeCorpId=TM()->getCorporationId();
        $person = Person::fromName($homeCorpId,$user_first,$user_last);
        $person->setBirthDate_Str($user_birthdate);
        $person->setHomeEmail($user_data['user_email']);
        $person->setGender($user_gender);
        $person->setFirstName($user_first);
        $person->setLastName($user_last);
        $person->isValid();
        
        //Setup the corresponding wp_user
        $currentTime = new DateTime('NOW');
        $userMeta = array(ManagePeople::USER_CORP_ID=>$homeCorpId
                        ,ManagePeople::USER_GENDER=>$user_gender
                        ,'first_name'=>$user_first
                        ,'last_name'=>$user_last);

        //$random_password = wp_generate_password( 12, true, false );
        $role = TM_Install::PUBLICMEMBER_ROLENAME;
        $userData = array(
                    'ID'         => $user_id,
                    'user_pass'  => $user_pass, //$random_password,
                    'user_login' => $user_data['user_login'],
                    'user_email' => $user_data['user_email'],
                    'user_nicename' => $user_data->user_nicename,
                    'user_registered' => $currentTime->format('Y-m-d H:i:s'),
                    'show_admin_bar_front' => 'true',
                    'role' => $role,
                    'meta_input' => $userMeta
        );

        $up_user_id = wp_update_user( $userData );
        if(is_wp_error($up_user_id)) {
            $mess = $up_user_id->get_error_message();
            throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
        }

        //Setup the corresponding custom post type
        $content = $person->getName();
        $postData = array(
                    'post_title' => $user_data['user_login'],
                    'post_name' => $user_data['user_login'],
                    'post_author'=> $user_id,
                    'post_status' => 'publish',
                    'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                    'post_content' => $content,
                    'post_type' => TennisMemberCpt::CUSTOM_POST_TYPE,
                    'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                    'post_modified' => $currentTime->format('Y-m-d G:i:s')
                    );

        $newPostId = wp_insert_post($postData, true);//NOTE: This triggers updatePersonDB in TennisMemberCpt
        if(is_wp_error($newPostId)) {
            $mess = $newPostId->get_error_message();
            throw new InvalidPersonException(__("{$mess}",TennisClubMembership::TEXT_DOMAIN));
        }
        $person->addExternalRef($newPostId);
        $person->save();
        update_user_meta($user_id,ManagePeople::USER_PERSON_ID,$person->getID());
        update_post_meta($newPostId, ManagePeople::USER_PERSON_ID, $person->getID());
        $this->log->error_log("Created new user '{$person->getID()}/{$user_id}: {$user_first} {$user_last}'.");

        if($user_id) {
            // send an email to the admin
            wp_new_user_notification($user_id);
            
            // log the new user in
            wp_set_auth_cookie($user_id,true);
            wp_set_current_user($user_id, $user_data['user_login']);	
            do_action('wp_login', $user_data['user_login'], $user_pass);
            
            // send the newly created user to the home page after logging them in
            //wp_redirect(home_url()); exit;
        }
        
    }

    // Add validation errors to the registration form
    // this is called when the form is submitted
    public function registrationErrors( $errors, $sanitized_user_login, $user_email ){
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc($sanitized_user_login, $user_email)");
        $this->log->error_log($_POST,"$loc: _POST");

        $user_first 	= $_POST["membership_user_first"] ?? '';
        $user_last	 	= $_POST["membership_user_last"] ?? '';
        $user_gender 	= $_POST["user_gender"][0] ?? '';
        $user_birthdate	= $_POST["membership_user_birthdate"] ?? '';
        $user_pass		= $_POST["membership_user_pass"] ?? '';
        $pass_confirm 	= $_POST["membership_user_pass_confirm"] ?? '';
        
        // this is required for username checks
        //require_once(ABSPATH . WPINC . '/registration.php');
        
        if(username_exists($sanitized_user_login)) {
            // Username already registered
            $this->log->error_log("$loc: Username already taken");
            $errors->add('username_unavailable', __('Username already taken',TennisClubMembership::TEXT_DOMAIN));
        }
        if(!validate_username($sanitized_user_login)) {
            // invalid username
            $this->log->error_log("$loc: Invalid user login");
            $errors->add('username_invalid', __('Invalid user login',TennisClubMembership::TEXT_DOMAIN));
        }
        if(!is_email($user_email)) {
            //invalid email
            $this->log->error_log("$loc: Invalid email");
            $errors->add('email_invalid', __('Invalid email',TennisClubMembership::TEXT_DOMAIN));
        }
        if(email_exists($user_email)) {
            //Email address already registered
            $this->log->error_log("$loc: Email already registered");
            $errors->add('email_used', __('Email already registered',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_pass == '') {
            // missing password
            $this->log->error_log("$loc: Please enter a password");
            $errors->add('password_empty', __('Please enter a password',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_pass != $pass_confirm) {
            // passwords do not match
            $this->log->error_log("$loc: Passwords do not match");
            $errors->add('password_mismatch', __('Passwords do not match',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_first == '') {
            // missing first name
            $this->log->error_log("$loc: Missing first name");
            $errors->add('first_name_empty', __('Please enter a first name',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_last == '') {
            // missing last name
            $this->log->error_log("$loc: Missing last name");
            $errors->add('last_name_empty', __('Please enter a last name',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_gender == '') {
            $this->log->error_log("$loc: Missing gender");
            $errors->add('last_name_empty', __('Please enter a valid gender',TennisClubMembership::TEXT_DOMAIN));
        }
        if($user_birthdate == '') {
            $this->log->error_log("$loc: Missing birthdate");
            $errors->add('birthdate_empty', __('Please enter a valid birthdate',TennisClubMembership::TEXT_DOMAIN));
        }
            
        //$errors = $this->registrationErrors()->get_error_messages();
        return $errors;
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