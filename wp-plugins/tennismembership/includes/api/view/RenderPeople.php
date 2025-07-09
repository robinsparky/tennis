<?php
namespace api\view;
use DateTime;
use DateInterval;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_User;
use datalayer\Person;
use datalayer\Address;
use TennisClubMembership;
use datalayer\MemberRegistration;
use datalayer\RegistrationStatus;
use datalayer\Genders;
use api\ajax\ManagePeople;
use commonlib\GW_Support;
use cpt\TennisMemberCpt;
use datalayer\Club;
use TM_Install;
use WP_User_Query;
use WP_Admin_Bar;
use WP_Error;

global $jsMemberData;

/** 
 * Renders People meta data in user admin pages
 * @class  RenderPeople
 * @package Tennis Members
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderPeople
{ 
    const ACTION         = 'manageusermembers';
    const NONCE          = 'manageusermembers';

    const SHORTSPONSOR      = 'render_member_sponsor';
    const SHORTREGISTRATION = 'render_member_registration';
    const SHORTSPONSORED    = 'render_member_sponsored';
    const SHORTHISTORY      = 'render_reg_history';
    const SHORTMENU         = 'render_member_menu';
    const SHORTADDRESS      = 'render_member_address';
    const SHORTEMERGENCY    = 'render_member_emergency';

    private $log;

    public static function register() {
        $handle = new self();
        $handle->registerHandlers();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        if(!is_admin()) {
            $jsurl =  TM()->getPluginUrl() . 'js/tennismems.js';
            wp_register_script( 'managepeople', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisClubMembership::VERSION, true );
            
            $cssurl = TM()->getPluginUrl() . 'css/tennismembership.css';
            wp_enqueue_style( 'membership_css', $cssurl );
        }
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);
        
        //Shortcode for rendering on front facing pages and components
        add_shortcode( self::SHORTSPONSOR, array( $this, 'renderSponsor' ) );
        add_shortcode( self::SHORTREGISTRATION, array( $this, 'renderCurrentRegistration' ) );
        add_shortcode( self::SHORTSPONSORED, array( $this, 'renderSponsored' ) );
        add_shortcode( self::SHORTHISTORY, array( $this, 'renderHistory' ) );
        add_shortcode( self::SHORTMENU, array( $this, 'renderMenu' ) );
        add_shortcode( self::SHORTADDRESS, array( $this, 'renderAddress' ) );
        add_shortcode( self::SHORTEMERGENCY, array( $this, 'renderEmergency' ) );

        //Redirect members to their homepage
		add_filter('login_redirect',array($this, 'goHomePost'),10,3);
        add_action('admin_bar_menu', array($this,'adjustMenuBarUserActions'), 500 );
        add_filter('wp_nav_menu_items', array($this,'add_extra_item_to_nav_menu'), 10, 2 );

        /** User admin filters & actions for rendering on user admin pages */
        //User column data
        add_filter('manage_users_columns', array($this,'add_member_columns'));
        add_action('manage_users_custom_column',array($this,'show_member_column_content'), 10, 3);
        //add_filter('manage_users_sortable_columns', array($this,'sortable_meta_columns'));
        add_action('restrict_manage_users', array($this,'add_user_gender_filter'));
        add_filter('pre_get_users', array($this,'filter_users_by_gender' ));
        // add_action( 'restrict_manage_users', array($this,'add_user_role_filter' ));
        // add_filter( 'pre_get_users', array($this,'filter_users_by_role' ));

        //User Profile Data Form
        add_action('show_user_profile', array($this,'userDataForm'),10,1); // editing your own profile
        add_action('edit_user_profile', array($this,'userDataForm'),10,1); // editing another user
        
        //Birthday
        // add_action('personal_options_update', array($this,'userMetaBirthdaySave'),10,1);
        // add_action('edit_user_profile_update', array($this,'userMetaBirthdaySave'),10,1);
        //Gender
        // add_action('personal_options_update', array($this,'userMetaGenderSave'),10,1);
        // add_action('edit_user_profile_update', array($this,'userMetaGenderSave'),10,1);
        //Mobile phone
        // add_action('personal_options_update', array($this,'userMetaPhoneSave'),10,1);
        // add_action('edit_user_profile_update', array($this,'userMetaPhoneSave'),10,1);
        /** End user admin */
    }

    public function add_extra_item_to_nav_menu( $items, $args ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc:");
        // $this->log->error_log(print_r($items,true));
        // $this->log->error_log(print_r($args,true));
        $this->log->error_log(print_r($args->menu,true));

        if (is_user_logged_in() && $args->menu->slug === 'main-menu') {
            $user = wp_get_current_user();
            $col = Person::find(['email'=>$user->user_email]);
            $person = count($col) === 1 ? $col[0] : null;
            if(!empty($person)) {                
                $this->log->error_log("$loc: found person '{$person->getName()}'");
                $postId = (int)$person->getExtRefSingle();
                $link = get_permalink($postId);
                $items .= "<li class='wt_menu_item_user_avatar'><a href='{$link}'>" . get_avatar( $user->ID, 32 ) . '</a></li>';
            }
        }
        return $items;
    }

    public function adjustMenuBarUserActions( WP_Admin_Bar $wp_admin_bar ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $parent_slug = 'adminbar-date-time';
        $local_time  = date( 'Y-m-d, g:i a', current_time( 'timestamp', 0 ) );

        $wp_admin_bar->add_menu( array(
            'id'     => $parent_slug,
            'parent' => 'top-secondary',
            'group'  => null,
            'title'  => $local_time,
            'href'   => site_url(), //admin_url( '/options-general.php' ),
        ) );

        // $nodes = $wp_admin_bar->get_nodes();
        // $this->log->error_log("$loc: all nodes");
        // if(empty($nodes)) $this->log->error_log(".... is empty");
        // else $this->log->error_log(print_r($nodes,true));

        $user = wp_get_current_user();
        $col = Person::find(['email'=>$user->user_email]);
        $person = count($col) === 1 ? $col[0] : null;
        $link = get_site_url();
        if(!empty($person)) {                
            $this->log->error_log("$loc: found person '{$person->getName()}'");
            $postId = (int)$person->getExtRefSingle();
            $link = get_permalink($postId);
            $wp_admin_bar->add_node([
              'id'        => 'link-id',
              'title' => __('Membership Details',TennisClubMembership::TEXT_DOMAIN),
              'href' => $link, //get_site_url(null, 'site-path'),
              'parent' => 'user-actions',
              'group' => null,
            ]);
        }

     
        // $wp_admin_bar->add_node([
        //   'id'        => 'logout',
        //   'title' => 'Log Out',
        //   'href' => wp_logout_url(),
        //   'parent' => 'user-actions'
        // ]);
    }
    
    public function kickoff($userId) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc({$userId})");

    }

    public function registrationData($user_login, $user_email, WP_Error $errors) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc({$user_login},{$user_email})");
        if($errors->get_error_code()) {
            $this->log->error_log("$loc: errors: {$errors->get_error_message()}");
            return;
        }   
        else {
            $this->log->error_log("$loc: no errors");
        }
    }

    /**
     * Redirect to the member's associated post
     * Only redirects users who are also Person's
     * TODO: detect workflow re membership registration, et al
     */
	public function goHomePost($to_url, $request_url, $user) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc({$to_url},{$request_url})");

        if( $user && is_object( $user ) && is_a( $user, 'WP_User' ) ) {
        $this->log->error_log("$loc: user_email: {$user->user_email}");
            $col = Person::find(['email'=>$user->user_email]);
            $person = count($col) === 1 ? $col[0] : null;
            if(!empty($person)) {
                $this->log->error_log("$loc: found person '{$person->getName()}'");
                $postId = (int)$person->getExtRefSingle();
                $link = get_permalink($postId);
                if(false !== $link) return $link;
            }
        }
        return $to_url;
	}

    public function renderSponsor(  $atts = [], $content = null  ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();

        if(!is_user_logged_in()) return '';
        
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Sponsor',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return 'NO POST';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return 'NO PERSON';
        }

        $person = Person::get($personId);
        $reglink = GW_Support::getRegLink($person);
        $name = $person->getName();
        $firstName = $person->getFirstName();
        $lastName = $person->getLastName();
        $gender = $person->getGender();
        $birthdate = $person->getBirthDate_Str();
        $email = $person->getHomeEmail();
        $homePhone = $person->getHomePhone();
        $userOwner = GW_Support::getUserByEmail($email);
        if(empty($userOwner)) return 'NO USER';
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';
  
        $season = TM()->getSeason();    
        $currentReg = MemberRegistration::find(['seasonId'=>$season,'personId'=>$personId]);
        if(empty($currentReg)) {
            $status =  RegistrationStatus::Inactive->value;
        }
        else {
            $currentReg = $currentReg[0];
            $membershipType = $currentReg->getMembershipType()->getName();
            $startDate = $currentReg->getStartDate_Str();
            $expiryDate = $currentReg->getEndDate_Str();
            $status = $currentReg->getStatus()->value;
        }
        $role = TM_Install::$tennisRoles[$userOwner->roles[0]];  
        //$role .= "/{$status}";

        $age = 0;
        $now = new Datetime('now');
        $bd = $person->getBirthDateTime();
        if(!empty($bd)) {
            $df = $now->diff($bd);
            $age = $df->y + $df->m/12.0 + $df->d/365.0;
            $age = round($age,2);
        }

        global $jsMemberData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'managepeople' );  
        $jsMemberData = $this->get_ajax_data();  
        wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData );
 
        $genderdd = Genders::getGendersDropDown($gender);

        $readonly = <<< EOD
<article class="membership person sponsor" id="$personId" data-person-id="$personId">
<h5>$title - $role</h5>
<ul class='membership sponsor'>
	<li>First Name:&nbsp;$firstName</li>
	<li>Last Name:&nbsp;$lastName</li>
	<li>Gender:&nbsp;$gender</li>
    <li>Home Phone:&nbsp;$homePhone</li>
	<li>Email:&nbsp;<a href='mailto:$email'>$email</a></li>
	<li>Birthdate:&nbsp;$birthdate&nbsp;($age years old)</li>
</ul>
</article>
EOD;

    $readwrite = <<< EOD
<article class="membership sponsor" id="$personId" data-person-id="$personId" >
<h5>$title - $role</h5>
<ul class='membership sponsor details'>
	<li class='membership sponsor first-name'><span>$firstName</span></li>
	<li class='membership sponsor last-name'><span>$lastName</span></li>
    <li class='membership sponsor'><button id='edit-sponsor' type='submit' class='button membership edit-sponsor'>Edit</button></li>
	<li class='membership sponsor home-email'>Email:&nbsp;<a href='mailto:$email'>$email</a></li>
	<li class='membership sponsor gender'>Gender:&nbsp;<span>$gender</span></li>
	<li class='membership sponsor birth-date'>Birthdate:&nbsp;<span>$birthdate</span>&nbsp;($age years old)</li>
    <li class='membership sponsor home-phone'>Home Phone:&nbsp;<span>$homePhone</span></li>
</ul>
<form action="" method="post" class="membership sponsor">
<label>First Name:&nbsp;<input type='text' name='firstname' class='membership first-name' value='$firstName'></label>
<label>Last Name:&nbsp;<input type='text' name='lastname' class='membership last-name' value='$lastName'></label>
<label><a href="mailto:$email">Email:</a>&nbsp;<input type='email' name='homeemail' class='membership home-email' value='$email' data-orig-value='$email'></label>
<label>Gender:&nbsp;$genderdd</label>
<label>Birthdate:&nbsp;<input name='birthdate' type='date' class='membership birth-date' value='$birthdate'>&nbsp;($age)</label>
<label>Home Phone:&nbsp;<input name='homephone' type='tel' class='membership home-phone' value='$homePhone'></label>
<button id='cancel-sponsor' type='submit' class='button membership cancel-sponsor'>Cancel</button>
<button id='save-sponsor' type='submit' class='button membership save-sponsor'>Save</button>
</form>
</article>
EOD;

        $template = $readonly;
        if($userOwner->ID === get_current_user_id()) $template = $readwrite;
        if(current_user_can('manage_options')) $template = $readwrite;

	    // Start output buffering we don't output to the page
        ob_start();
        echo $template;
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }
    
    public function renderSponsored( $atts = [], $content = null ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Sponsored',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $sponsorId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($sponsorId)) {
            return '';
        }

        $sponsor = Person::get($sponsorId);
        $email = $sponsor->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(empty($userOwner)) return '';
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';

        $sponsored = $sponsor->getSponsored();
        $firstName = $sponsor->getFirstName();
        $lastName = $sponsor->getLastName();
        $sponsorName = $sponsor->getName();
        $gender = $sponsor->getGender();
        $birthdate = $sponsor->getBirthDate_Str();
        $homePhone = $sponsor->getHomePhone();

        $age = 0;
        $now = new Datetime('now');
        $bd = $sponsor->getBirthDateTime();
        if(!empty($bd)) {
            $df = $now->diff($bd);
            $age = $df->y + $df->m/12.0 + $df->d/365.0;
            $age = round($age,2);
        }

        global $jsMemberData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'managepeople' );  
        $jsMemberData = $this->get_ajax_data();  
        wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData );

        $numSponsored = count($sponsored);
        $title2 = "{$numSponsored} " . $title;

        $beginTemplate = <<< EOD
<article class="membership sponsored" id="$sponsorId" data-sponsorid="$sponsorId">
<h5>$title2</h5>
EOD;
        $readonly = <<< EOD
<ul class='membership sponsored' data-sponsoredid='%d'>
	<li>First Name:&nbsp;%s</li>
	<li>Last Name:&nbsp;%s</li>
	<li>Gender:&nbsp;%s</li>
	<li>Birthdate:&nbsp;%s&nbsp;(%f years old)</li>
	<li>Email:&nbsp;<a href='mailto:%s'>%s</a></li>
    <li>Home Phone:&nbsp;%s</li>
</ul>
EOD;

    $readwrite = <<< EOD
<section class="membership sponsored" data-sponsoredid="%d">
<ul class='membership sponsored full-name'>
<li class='membership sponsored full-name'>%s</li>
<li class='membership sponsored'><button type='submit' class='button membership show-sponsored'>Show</button></li>
<li class='membership sponsored'><button type='submit' class='button membership edit-sponsored'>Edit</button></li>
<li class='membership sponsored'><button type='submit' class='button membership delete-sponsored'>Delete</button></li>
</ul>
<ul class='membership sponsored details' data-sponsoredid='%d'>
<li class='membership sponsored home-email'>Email:&nbsp;<a href='mailto:%s'>%s</a></li>
<li class='membership sponsored gender'>Gender:&nbsp;<span>%s</span></li>
<li class='membership sponsored birth-date'>Birthdate:&nbsp;<span>%s</span> (%.2f years old)</li>
<li class='membership sponsored home-phone'>Home Phone:&nbsp;<span>%s</span></li>
</ul>
<form action="" method="post" class="membership sponsored" data-sponsoredid='%d'>
<label>First Name:&nbsp;<input type='text' name='firstname' class='membership first-name' value='%s'></label>
<label>Last Name:&nbsp;<input type='text' name='lastname' class='membership last-name' value='%s'></label>
<label><a href="mailto:%s">Email:</a>&nbsp;<input type='email' name='homeemail' class='membership home-email' value='%s' data-orig-value='%s'></label>
<label>Gender:&nbsp;%s</label>
<label>Birthdate:&nbsp;<input name='birthdate' type='date' class='membership birth-date' value='%s'>&nbsp;(%.2f years old)</label>
<label>Home Phone:&nbsp;<input name='homephone' type='tel' class='membership home-phone' value='%s'></label>
<button id='cancel-sponsored' type='submit' class='button membership cancel-sponsored'>Cancel</button>
<button id='save-sponsored' type='submit' class='button membership save-sponsored'>Save</button>
</form>
</section>
EOD;

        $endTemplate = <<< EOD
</article>
EOD;

        $template = $readonly;
        if($userOwner->ID === get_current_user_id()) $template = $readwrite;
        if(current_user_can('manage_options')) {
            $template = $readwrite;
        }
        
        $now = new Datetime('now');
	    // Start output buffering we don't output to the page
        ob_start();
        echo $beginTemplate;
        if($template === $readwrite) {
            require TM()->getPluginPath() . 'includes/templates/controls/newSponsoredDialog.php';
            echo "<button id='add-sponsored' type='submit' class='button membership add-sponsored' data-sponsorid='$sponsorId'>Add</button>";
            echo "<hr class='membership sponsored'/>";
        }

        foreach($sponsored as $sp) {
            $id = $sp->getID();
            $firstName = $sp->getFirstName();
            $lastName = $sp->getLastName();
            $gender = $sp->getGender();
            $genderdd = Genders::getGendersDropDown($gender);
            $birthdate = $sp->getBirthDate_Str();
            $bd = $sp->getBirthDateTime();
            $homePhone = $sp->getHomePhone();
            if(empty($homePhone)) $homePhone = 'N/A';
            $email = $sp->getHomeEmail();
            if(empty($email)) $email = 'N/A';
            $age = 0;
            if(!empty($bd)) {
                $df = $now->diff($bd);
                $age = round($df->y + $df->m/12.0 + $df->d/365.0,2);
            }
            if($template === $readwrite) {
                printf($template
                    // for the section
                    ,$id
                    ,$firstName . ' ' . $lastName
                    // for the list
                    ,$id
                    ,$email,$email
                    ,$gender
                    ,$birthdate,$age
                    ,$homePhone
                    // for the form
                    ,$id
                    ,$firstName
                    ,$lastName
                    ,$email,$email,$email
                    ,$genderdd
                    ,$birthdate,$age
                    ,$homePhone
                );
            } else {
                printf($template
                    ,$id
                    ,$firstName
                    ,$lastName
                    ,$email,$email
                    ,$gender
                    ,$birthdate,$age
                    ,$homePhone
                );
            }
            echo "<hr class='membership sponsored'/>";
        } //loop through sponsored

        echo $endTemplate;
    
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }
        
    public function renderMenu(  $atts = [], $content = null  ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();

        $args= shortcode_atts( array(
            'title'=>'Menu',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return '';
        }

        $person = Person::get($personId);
        $reglink = GW_Support::getRegLink($person);
        $email = $person->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';
        
        $readonly = <<< EOD
<article class="membership nav" id="$personId" data-personid="$personId">
<nav class="menu">
    <span class="menu item address"><a href='#'>Address</a></span>
    <span class="menu item emergency"><a href='#'>Emergency Contact</a></span>
    <span class="menu item agreement"><a href='#'>Registration Agreement</a></span>
    <span class="menu item history"><a href='#'>Registration History</a></span>
</nav>
</article>
EOD;
	    // Start output buffering we don't output to the page
        ob_start();
        echo $readonly;
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    public function renderCurrentRegistration( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();

        if(!is_user_logged_in()) return '';
        
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Current Membership',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return '';
        }

        $person = Person::get($personId);
        $reglink = GW_Support::getRegLink($person);
        $email = $person->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';

        $season = TM()->getSeason();
        $currentReg = MemberRegistration::find(['seasonId'=>$season,'personId'=>$personId]);
        $membershipType = '';
        $startDate = '';
        $expiryDate = '';
        $status = RegistrationStatus::Inactive->value;
        $inclDir = false;
        $shareEmail = false;
        $receiveEmails = false;
        $fee = 500;
        if(empty($currentReg)) {
            $title .= " - {$status}";
        }
        else {
            $currentReg = $currentReg[0];
            $membershipType = $currentReg->getMembershipType()->getName();
            $startDate = $currentReg->getStartDate_Str();
            $expiryDate = $currentReg->getEndDate_Str();
            $status = $currentReg->getStatus()->value;
            $inclDir = $currentReg->getIncludeInDir() ? "Yes" : "No";
            $receiveEmails = $currentReg->getReceiveEmails() ? "Yes" : "No";
            $shareEmail = $currentReg->getShareEmail() ? "Yes" : "No";
        }

        global $jsMemberData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'managepeople' );  
        $jsMemberData = $this->get_ajax_data();  
        wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData );

        $not_registered = <<<EOD
<article class='membership not-registered'  id="$personId" data-personid="$personId">
<h5>$title</h5>
<button id='register-membership' type='submit' class='button membership apply-registration'>Register</button>
</article>
EOD;
        $readonly = <<<EOD
<article class='membership current-registration' id="$personId" data-personid="$personId">
<h5>$title</h5>
<table class='membership current-registration'>
<tbody>
<tr><td>Season</td><td>$season</td></tr>
<tr><td>Membership</td><td>$membershipType</td></tr>
<tr><td>Status</td><td>$status</td></tr>
<tr><td>Start</td><td>$startDate</td></tr>
<tr><td>Expires</td><td>$expiryDate</td></tr>
<tr><td>Include in Directory</td><td>$inclDir</td></tr>
<tr><td>Receive Emails</td><td>$receiveEmails</td></tr>
<tr><td>Share Email</td><td>$shareEmail</td></tr>
<tr><td>Payment</td><td>&dollar;$fee</td></tr>
</tbody>
</table>
<button id='modify-registration' type='submit' class='button membership modify-registration' data-personid="$personId">Modify</button>
</article>
EOD;

        $template = empty($currentReg) ? $not_registered : $readonly;

	    // Start output buffering we don't output to the page
        ob_start();
        echo $template;
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }
        
    public function renderHistory(  $atts = [], $content = null  ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();
        
        if(!is_user_logged_in()) return '';
        
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Registration History',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return '';
        }

        $person = Person::get($personId);
        $reglink = GW_Support::getRegLink($person);
        $email = $person->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';
        
        $season = TM()->getSeason();
        $allRegs = MemberRegistration::find(['personId'=>$personId]);
        // $membershipType = '';
        // $startDate = '';
        // $expiryDate = '';
        // $status = '';
        if(empty($allRegs)) {
            $title .= " - No Registrations";
        }

        global $jsMemberData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'managepeople' );  
        $jsMemberData = $this->get_ajax_data();  
        wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData );

        $header = <<<EOD
<article class="membership history" id="$personId" data-person-id="$personId">
<h5>$title</h5>
<table class="membership history">
<thead>
<tr><th scope='col'>Season</th><th scope='col'>Type</th><th scope='col'>Status</th><th scope='col'>Start Date</th><th scope='col'>Expiry Date</th><th scope='col'>Directory</th><th scope='col'>Receive Emails</th><th scope='col'>Share Email</th></tr>
</thead>
<tbody>
EOD;
        $readonly = <<<EOD
<tr class="membership history" id="%d" data-registration-id="%d">
    <td scope='row' class='membership season'>%s</td>
    <td scope='row' class='membership registration-type'>%s</td>
    <td scope='row' class='membership status'>%s</td>
    <td scope='row' class='membership start-date'>%s</td>
    <td scope='row' class='membership expiry-date'>%s</td>
    <td scope='row' class='membership include-directory'>%s</td>
    <td scope='row' class='membership receive-emails'>%s</td>
    <td scope='row' class='membership share-email'>%s</td>
</tr>
EOD;
        $tail = <<<EOD
</tbody>
</table>
</article>
EOD;
	    // Start output buffering
        ob_start();
        echo $header;
        foreach($allRegs as $reg) {
            $id = $reg->getID();
            $season = $reg->getSeasonId();
            $membershipType = $reg->getMembershipType()->getName();
            $status = $reg->getStatus()->value;
            $startDate = $reg->getStartDate_Str();
            $expiryDate = $reg->getEndDate_Str();
            $inclDir = $reg->getIncludeInDir() ? "Yes" : "No";
            $receiveEmails = $reg->getReceiveEmails() ? "Yes" : "No";
            $shareEmail = $reg->getShareEmail() ? "Yes" : "No";
            printf($readonly,$id,$id
                            ,$season
                            ,$membershipType
                            ,$status
                            ,$startDate
                            ,$expiryDate
                            ,$inclDir
                            ,$receiveEmails
                            ,$shareEmail);
        }
        echo $tail;
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }
        
    public function renderAddress(  $atts = [], $content = null  ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();
        
        if(!is_user_logged_in()) return '';
        
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Address',
            'post_id'=>0,
        ), $atts, self::SHORTSPONSOR );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return '';
        }

        $person = Person::get($personId);
        $email = $person->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';

        $addr1 = '';
        $addr2 = '';
        $city  = '';
        $country = '';
        $postal = '';
        $prov = '';
        $address = Address::find($personId);
        if(!empty($address)) {
            $address = $address[0];
            $addr1 = $address->getAddr1();
            $addr2 = $address->getAddr2();
            $city = $address->getCity();
            $postal = $address->getPostalCode();
            $prov = $address->getProvince();
        }

        $template = <<<EOD
<article id="$personId" class="membership address" data-person-id="$personId">
<h5>$title></h5>
<table class="membership address">
<tbody>
<tr><td>Street</td><td>$addr1</td></tr>
<tr><td>Apt/Box</td><td>$addr2</td></tr>
<tr><td>City</td><td>$city</td></tr>
<tr><td>Province</td>$prov</td></tr>
<tr><td>Postal</td><td>$postal</td></tr>
</tbody>
</table>
</article>
EOD;

        $noaddress = <<<EOD
<article id="$personId" class="membership address" data-person-id="$personId">
<h5>$title</h5>
<button id='add-address' type='submit' class='button membership add-address'>Add</button>
</article>
EOD;
        

	    // Start output buffering we don't output to the page
        ob_start();
        if(empty($address)) {
            echo $noaddress;
        }
        else {
            echo $template;
        }
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    public function renderEmergency($atts,$content) {     
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();
        
        if(!is_user_logged_in()) return '';
        
        // if( $_POST ) {
        //     $this->log->error_log($_POST, "$loc: POST:");
        // }

        // if( $_GET ) {
        //     $this->log->error_log($_GET, "$loc: GET:");
        // }

        $args= shortcode_atts( array(
            'title'=>'Emergency Contact',
            'post_id'=>0,
        ), $atts, self::SHORTEMERGENCY );
        $this->log->error_log( $args, "$loc: My Atts" );

        $title = urldecode($args['title']);
        $postId = $args['post_id'];
        if(0 == $postId) return '';
        
        $personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
        if(empty($personId)) {
            return '';
        }

        $person = Person::get($personId);
        $email = $person->getHomeEmail();
        $userOwner = GW_Support::getUserByEmail($email);
        if(!current_user_Can('manage_options') && ($userOwner->ID !== get_current_user_id()) ) return '';

        $emergencyContact = [];
        if(!empty($emergencyContact)) {

        }

        $template = <<<EOD
<article id="$personId" class="membership emergency" data-person-id="$personId">
<h5>$title></h5>
<table class="membership emergency">
<tbody>
</tbody>
</table>
</article>
EOD;

        $nocontact = <<<EOD
<article id="$personId" class="membership emergency" data-person-id="$personId">
<h5>$title</h5>
<button id='add-emergency' type='submit' class='button membership add-emergency'>Add</button>
</article>
EOD;
        

	    // Start output buffering we don't output to the page
        ob_start();
        if(empty($emergencyContact)) {
            echo $nocontact;
        }
        else {
            echo $template;
        }
        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;

    }

    public function showPeople($title, $users ) {       
        $args = array(
            'meta_query' => array(
                'relation' => 'OR',
                    array(
                        'key'     => 'country',
                        'value'   => 'Israel',
                         'compare' => '='
                    ),
                    array(
                        'key'     => 'age',
                        'value'   => array( 20, 30 ),
                        'type'    => 'numeric',
                        'compare' => 'BETWEEN'
                    )
            )
         );
        $user_query = new WP_User_Query( $args );
        $allUsers = $user_query->get_results();
        ?>
    <table id='userqueryresults'>
        <caption></caption>
        <thead>
        <tr>
            <th scope="col">ID</th><th>First Name</th><th>Last Name</th><th>Gender</th><th>Birth Date</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($users as $user) {
        $meta = get_user_meta( $user->ID );
        // Filter out empty meta data
        $meta = array_filter( array_map( function( $a ) {return $a[0];}, $meta ) );
        $birthdate = $meta['birthdate'];
        $gender = $meta['gender'];
        ?>
        <tr id=""><td><?php echo $user->ID;?></td><td><?php echo $user->first_name;?></td><td><?php echo $user->last_name;?></td><td><?php echo $gender;?></td><td><?php echo $birthdate;?></td></tr>
    <?php } ?>
    </tbody>
        </table>
    <?php } 


    public function add_member_columns($columns) {
        $columns['ismember'] = "Is Member";
        $columns['sponsoredby'] = "Sponsored By";
        $columns['gender'] = 'Gender';
        $columns['phone'] = "Phone";
        $columns['birthday'] = 'Birthday';
        $columns['personalinfo'] = "More...";
        return $columns;
    } 

    public function show_member_column_content($value, $column_name, $user_id) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc($value,$column_name,$user_id)");

        $user = get_user_by("ID",$user_id);
        $person = Person::find(["email"=>$user->user_email])[0] ?? null;
        $clubPerson = false;
        foreach(TM_Install::$tennisRoles as $slug=>$name) {
            if(in_array($slug, $user->roles)) $clubPerson = true;
        }
        $personId=0;
        $sponsorId = 0;
        if(null !== $person) {
            update_user_meta($user->ID,ManagePeople::USER_PERSON_ID,$person->getID());
            $personId = $person->getID();
            $sponsorId = $person->getSponsorId();
        } 
        $sponsor = null;
        if($sponsorId > 0 ) {
            $sponsor = Person::get($sponsorId);
        }

        $seasonId = TM()->getSeason();
        switch($column_name) {
            case 'birthday':
                $value = (null !== $person) ? $person->getBirthDate_Str() : '';
                break;
            case 'gender':
                $value = (null !== $person) ? $person->getGender() : '';
                break;
            case 'phone':
                $value = (null !== $person) ? $person->getHomePhone() : '';
                break;      
            case 'ismember':
                $value = MemberRegistration::IsMember($seasonId,$user_id) ? 'Yes' : 'No';
                break;        
            case 'sponsoredby':
                $sponsorName = null === $sponsor ? 'Self' : $sponsor->getName();
                $value = $sponsorName;
                break;    
            case 'personalinfo':
                if(null == $person) {
                    $link = get_bloginfo('url') . '/' . TennisMemberCpt::CUSTOM_POST_TYPE_SLUG . '/' . $user->user_login;
                }
                else {
                    $link = null === $sponsor ? GW_Support::getHomeLink($person) : GW_Support::getHomeLink($sponsor);
                }
                $value = "<a href='$link'>More...</a>"; 
                break;
        }
        return $value;
    }

    //make the new column sortable
    function sortable_meta_columns( $columns ) {
        $columns['ismember'] = 'ismember';
        $columns['sponsoredby'] = 'sponsoredby';
        return $columns;
    }

    function add_user_role_filter( $which ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        echo '<select name="user_role[]" style="float:none; margin-left: 5px;">';
        echo '<option value="">Role&hellip;</option>';
        switch($which) {
            case 'top':
                $roleVar = $_GET[ 'user_role'][0] ?? '';
                break;
            case 'bottom':
                $roleVar = $_GET[ 'user_role'][1] ?? '';
                break;
            default:
                $roleVar = $_GET['user_role'][0] ?? $_GET['user_role'][1] ?? '';
                break;
        }
        foreach(wp_roles()->role_names as $name) {
            $selected = ( $name == $roleVar ) ? 'selected' : '';
            echo "<option value='{$name}' {$selected}>{$name}</option>";
        }
        echo "</select>";
        $attrs = ["id"=>"role_filter_{$which}"];
        submit_button(__( 'Filter Role', TennisClubMembership::TEXT_DOMAIN), null, $which, false, $attrs);
    }

    function filter_users_by_role( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $pagenow;
    
        if ( is_admin() && 'users.php' == $pagenow ) {
            $keys = array_keys($_GET);
            $which = in_array('top',$keys) ? 'top' : 'bottom';
            switch($which) {
                case 'top':
                    $role = $_GET[ 'user_role'][0] ?? '';
                    $_GET[ 'user_role'][1] = $role;
                    break;
                case 'bottom':
                    $role = $_GET[ 'user_role'][1] ?? '';
                    $_GET[ 'user_role'][0] = $role;
                    break;
                default:
                    $role = '';
                    break;
            }
            if(!empty($role)) {
                $query->set( 'role', $role );
            }
        }
    }

    /**
     * Drop down for filtering by Gender
     * @param mixed $which indicates whether we are addressing the filter on top or bottom of user table
     */
    function add_user_gender_filter( $which ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        // echo '<select name="user_gender[]" style="float:none; margin-left: 5px;">';
        // echo '<option value="">Gender&hellip;</option>';
        switch($which) {
            case 'top':
                $genderVar = $_GET['user_gender'][0] ?? '';
                break;
            case 'bottom':
                $genderVar = $_GET['user_gender'][1] ?? '';
                break;
            default:
                $genderVar = '';
                break;
        }
        $sel = Genders::getGendersDropDown($genderVar);
        $this->log->error_log("$loc: genderVar='{$genderVar}' which='{$which}'");
        echo $sel;
        submit_button(__( 'Filter Gender', TennisClubMembership::TEXT_DOMAIN), 'primary', 'gender_filter_'.$which, false);
    }

    /**
     * Modify the user query by adding a meta query to filter 
     * @param mixed $query ... the user query
     */
    function filter_users_by_gender( $query ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $pagenow;
    
        if ( is_admin() && 'users.php' == $pagenow ) {
            $keys = array_keys($_GET);
            $which = in_array('gender_filter_top',$keys) ? 'top' : '';
            if(empty($which)) $which = in_array('gender_filter_bottom',$keys) ? 'bottom' : '';
            $this->log->error_log("$loc: which='{$which}'");
            switch($which) {
                case 'top':
                    $gender = $_GET[ 'user_gender'][0] ?? '';
                    $_GET[ 'user_gender'][1] = $gender;
                    break;
                case 'bottom':
                    $gender = $_GET[ 'user_gender'][1] ?? '';
                    $_GET[ 'user_gender'][0] = $gender;
                    break;
                default:
                    $gender = '';
                    break;
            }
            $this->log->error_log("$loc: gender='{$gender}'");
            if(!empty($gender)) {
                $meta_query = array(
                    array(
                        'key'   => ManagePeople::USER_GENDER,
                        'value' => $gender,
		                'compare' => '='
                    )
                );
                $this->log->error_log(print_r($meta_query,true));
                $query->set( 'meta_key', ManagePeople::USER_GENDER );
                $query->set( 'meta_query', $meta_query );
            }
        }
    }

    public function userDataForm(WP_User $user) { 
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $current_user = wp_get_current_user();
        $this->log->error_log("$loc: Target User id={$user->ID}; Current user id={$current_user->ID}");

        $person = Person::find(["email"=>$user->user_email])[0] ?? null;
        $seasonId = TM()->getSeason();
        $birthdate = '';
        $phone = '';
        $gender = '';
        $sponsorName = 'Self';
        if(null !== $person) {
            $gender = $person->getGender();
            $birthdate = $person->getBirthDate_Str();
            $phone = $person->getHomePhone();
            $isMember = MemberRegistration::IsMember($seasonId,$person->getID()) ? 'Yes' : 'No';
            $sponsorName = null === $person->getSponsor() ? 'Self' : $person->getSponsor()->getName();
        }
    ?>
    <h2><?php esc_html_e("Membership Data",TennisClubMembership::TEXT_DOMAIN)?></h2>
        <table class="form-table">
            <tr>
                <th><label for="user_birthday"><?php esc_html_e("Birthday",TennisClubMembership::TEXT_DOMAIN)?></label></th>
                <td>
                    <span id='user_birthday'><?php echo $birthdate;?></span>
                </td>
            </tr>    
            <tr>
                <th><label for="user_ismember"><?php esc_html_e( "Is Member in {$seasonId}",TennisClubMembership::TEXT_DOMAIN)?></label></th>
                <td>
                    <span id='user_ismember'><?php echo $isMember; ?></span>
                </td>
            </tr>    
            <tr>
                <th><label for="user_sponsoredby"><?php esc_html_e( "Sponsor",TennisClubMembership::TEXT_DOMAIN)?></label></th>
                <td>
                    <span id='user_sponsoredby'><?php echo $sponsorName; ?></span>
                </td>
            </tr>  
            <tr>
                <th><label for="user_gender"><?php esc_html_e( "Gender",TennisClubMembership::TEXT_DOMAIN)?></label></th>
                <td> 
                    <span><?php echo $gender?></span>
                </td>
            </tr> 
            <tr>
                <th><label for="user_phone"><?php esc_html_e( "Phone",TennisClubMembership::TEXT_DOMAIN)?></label></th>
                <td>
                    <span><?php echo $phone;?></span>
                    <span class="description"><?php esc_html_e(" Home phone",TennisClubMembership::TEXT_DOMAIN)?></span>
                </td>
            </tr>
        </table>
    <?php
    }

    public function userMetaBirthdaySave($userId) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        update_user_meta($userId, ManagePeople::USER_BIRTHDAY, $_REQUEST['user_birthday']);
    }

    public function userMetaGenderSave($userId) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        update_user_meta($userId, ManagePeople::USER_GENDER, $_REQUEST['user_gender']);
    }

    public function userMetaPhoneSave($userId) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        update_user_meta($userId, ManagePeople::USER_PHONE, $_REQUEST['user_phone']);
    }
    
    // public function userMetaCorporateIdSave($userId) {
    //     if (!current_user_can('edit_user', $userId)) {
    //         return;
    //     }
    //     update_user_meta($userId, TennisMemberCpt::USER_CORP_ID, $_REQUEST['user_corporateid']);
    // }

    // public function userMetaPersonIdSave($userId) {
    //     if (!current_user_can('edit_user', $userId)) {
    //         return;
    //     }
    //     update_user_meta($userId, TennisMemberCpt::USER_PERSON_ID, $_REQUEST['user_personid']);
    // }

    /**
     * Get the AJAX data that WordPress needs to send.
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
            ,'season'   => TM()->getSeason()
            ,'corporateId' => TM()->getCorporationId()
            ,'message' => $mess
        );
    }

}