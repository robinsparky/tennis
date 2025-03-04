<?php
namespace api\view;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_User;
use datalayer\Person;
use TennisClubMembership;
use datalayer\MemberRegistration;
use datalayer\Genders;
use api\ajax\ManagePeople;
use commonlib\GW_Support;
use cpt\TennisMemberCpt;
use TM_Install;
use WP_User_Query;

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
    const ACTION    = 'manageusermembers';
    const NONCE     = 'manageusermembers';
    const SHORTCODE = 'manage_user_members';

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
        
        //Shortcode for rendering on front facing pages
        add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );

        /** User admin filters & actions for rendering on user admin pages */
        //User column data
        add_filter('manage_users_columns', array($this,'add_member_columns'));
        add_action('manage_users_custom_column',array($this,'show_member_column_content'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this,'sortable_meta_columns'));
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

    public function renderShortcode( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

        if( $_POST ) {
            $this->log->error_log($_POST, "$loc: POST:");
        }

        if( $_GET ) {
            $this->log->error_log($_GET, "$loc: GET:");
        }

        $short_atts = shortcode_atts( array(
            'title'=>'People',
            'status' => '',
            'role' => '',
            'portal' => ''
        ), $atts, self::SHORTCODE );

        $this->log->error_log( $short_atts, "$loc: My Atts" );
        $title = $short_atts['title'];
        $status = $short_atts['status'];
        $regType = $short_atts['regtype'];
        $portal = $short_atts['portal'];

        //Get the Club from attributes
        // $club = null;
        // if(!empty( $my_atts['clubname'] ) ) {
        //     $cname = filter_var($my_atts['clubname'], FILTER_SANITIZE_STRING);
        //     $arrClubs = Club::search( $cname );
        //     if( count( $arrClubs) > 0 ) {
        //         $club = $arrClubs[0];
        //     }
        // }
        // else {
        //     $homeClubId = esc_attr( get_option(self::HOME_CLUBID_OPTION_NAME, 0) );
        //     $club = Club::get( $homeClubId );
        // }
        // if( is_null( $club ) ) return __('Please set home club id or specify name in shortcode', TennisEvents::TEXT_DOMAIN );
        //Go

        return $this->renderPersons($short_atts);

    }

    private function renderPersons( $args ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();

        $title = "People Management";
        if(is_array($args)) {
            $title = urldecode($args['title']);
            $status = $args['status'];
            $role = $args['role'];
            $portal = $args['portal'];
        }

        global $jsMemberData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'managepeople' );  
        $jsMemberData = $this->get_ajax_data();       
        wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData );  
        
        $title = "Members";
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

	    // Start output buffering we don't output to the page
        ob_start();
        
        // Get template file to render the registrations
        // $path = wp_normalize_path( TM()->getPluginPath() . 'includes\templates\archive_clubmembershipcpt.php');
        // require $path;
        $this->showPeople($title,$allUsers);

        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    public function showPeople($title, $users ) {
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
        if(null !== $person) {
            update_user_meta($user->ID,ManagePeople::USER_PERSON_ID,$person->getID());
            $personId = $person->getID();
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
                $sponsor = null === $person ? null : $person->getSponsor();
                $sponsorName = null === $sponsor ? 'Self' : $sponsor->getName();
                $value = $sponsorName;
                break;    
            case 'personalinfo':
                if(null == $person) {
                    $link = get_bloginfo('url') . '/' . TennisMemberCpt::CUSTOM_POST_TYPE_SLUG . '/' . $user->user_login;
                }
                else {
                    $link = GW_Support::getHomeLink($person);
                }
                $value = "<a href='$link'>Info</a>"; 
                break;
        }
        return $value;
    }

    //make the new column sortable
    function sortable_meta_columns( $columns ) {
        $columns['ismember'] = 'Is Member';
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