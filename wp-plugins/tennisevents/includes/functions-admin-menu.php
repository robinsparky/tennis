<?php

use api\TournamentDirector;
use datalayer\Club;
use datalayer\Event;

/* WP Admin Menu positions
2 - Dashboard
4 - Separator
5 - Posts
10 - Media
20 - Pages
25 - Comments
59 - Separator
60 - Appearance
65 - Plugins
70 - Users
75 - Tools
80 - Settings
99 - Separator
*/

/* ================================
    Admin Menus
   ================================   
*/
function gw_tennis_admin_page() {
    //Generate Tennis Events admin page
    add_menu_page( 'Tennis Admin Options' //title
                 , 'Tennis Settings' //menu name
                 , 'manage_options' //capabilities required of user
                 , 'gwtennissettings' //slug
                 , 'gw_tennis_create_page' // function to create page
                 , 'dashicons-admin-generic' //icon
                 , 90 //menu position
    );

    //Generate admin sub pages
    add_submenu_page( 'gwtennissettings' //parent slug
                    , 'Tennis Admin Options' //page title
                    , 'Settings' // menu title
                    , 'manage_options' //capabilities
                    , 'gwtennissettings' // menu slug
                    , 'gw_tennis_create_page' //callback
    );

    add_action( 'admin_init', 'gw_tennis_custom_settings' );
}

//Activate custom settings
add_action( 'admin_menu', 'gw_tennis_admin_page' );

function gw_tennis_create_page() {
    //generation of our admin page
    $pluginsPath = plugin_dir_path( __FILE__ );
    //Tennis settings template
    $pathToTemplate = $pluginsPath . 'templates/tennis-settings.php';
    require_once $pathToTemplate;
}

function gw_tennis_custom_settings() {
    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_HOME_TENNIS_CLUB //No option stored for home club; uses tennis db
                    , ['type'=>'number'
                      ,'sanitize_callback'=>'gw_sanitize_clubId' ]
                    );
                    
    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_TENNIS_SEASON //Option name used in get_option
                    , ['type'=>'number'
                      ,'sanitize_callback'=>'gw_sanitize_season'
                      ,'description'=>__('The relevant season for tennis events.', TennisEvents::TEXT_DOMAIN)]
                    );
                                       
    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_HISTORY_RETENTION //Option name used in get_option
                    , ['type'=>'number'
                    ,'sanitize_callback'=>'gw_sanitize_history'
                    ,'description'=>__('The number seasons to retain.', TennisEvents::TEXT_DOMAIN)]
                    );
                    

    register_setting( 'gw-tennis-settings-group' //Options group
                    , TournamentDirector::OPTION_MIN_PLAYERS_ELIM //Option name used in get_option
                    , ['type'=>'number'
                      ,'sanitize_callback'=>'gw_sanitize_minium_players'
                      ,'description' => __('The minimum number of players needed for single elimination.', TennisEvents::TEXT_DOMAIN)]
                    );

    register_setting( 'gw-tennis-settings-group' //Options group
                    , TournamentDirector::OPTION_MIN_PLAYERS_RR //Option name used in get_option
                    , ['type'=>'number'
                    ,'sanitize_callback'=>'gw_sanitize_minium_players_rr'
                    ,'description' => __('The minimum number of players needed for round robin.', TennisEvents::TEXT_DOMAIN)]
                );

    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_MINIMUM_LEADTIME //Option name used in get_option
                    , ['type'=>'number'
                    ,'sanitize_callback'=>'gw_sanitize_minium_leadtime'
                    ,'description' => __('The minimum lead time after signup ends.', TennisEvents::TEXT_DOMAIN)]
                );

    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_MINIMUM_DURATION_SUGGESTIONS //Option name used in get_option
                    , ['type'=>'number'
                    ,'sanitize_callback'=>'gw_sanitize_minimum_suggestions'
                    ,'description' => __('The minimum duration (days) in an event beyond which suggested completion dates are produced.', TennisEvents::TEXT_DOMAIN)]
                );
    
    register_setting( 'gw-tennis-settings-group' //Options group
                    , TennisEvents::OPTION_LOCK_PREVIOUS_SEASONS //Option name used in get_option
                    , ['type'=>'string'
                    ,'sanitize_callback'=>'gw_sanitize_lockdown_previous'
                    ,'description' => __('Lock down events from previous seasons (yes or no)', TennisEvents::TEXT_DOMAIN)]
                );


    add_settings_section( 'gw-tennis-options' //id
                        , __('Tennis Settings', TennisEvents::TEXT_DOMAIN) //title
                        , 'gw_tennis_options' //callback to generate html
                        , 'gwtennissettings' //page
                    );
    
    add_settings_field( 'gw_tennis_home_club' // id
                      , __('Home Club', TennisEvents::TEXT_DOMAIN) // title
                      , 'gw_tennisHomeClub' // callback
                      , 'gwtennissettings' // page
                      , 'gw-tennis-options' // section
                      //,  array of args
                );
                
    add_settings_field( 'gw_tennis_season' // id
                    , __('Season', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisSeason' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );

                                
    add_settings_field( 'gw_tennis_history' // id
                    , __('History Retention',TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisHistory' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
         
    add_settings_field( 'gw_tennis_minimum_elim' // id
                    , __('Min Players for Elimination', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisMinElim' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                ); 

    add_settings_field( 'gw_tennis_minimum_rr' // id
                    , __('Min Players for Round Robin', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisMinRR' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
                
    add_settings_field( 'gw_tennis_minimum_lead' // id
                    , __('Draw will be available', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisMinLeadTime' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
                                
    add_settings_field( 'gw_tennis_minimum_suggestion' // id
                    , __('Completion date suggestions', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisMinSuggestionTime' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
                                               
    add_settings_field( 'gw_tennis_lockdown_previous' // id
                    , __('Lockdown old events', TennisEvents::TEXT_DOMAIN) // title
                    , 'gw_tennisLockdown' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
}

function gw_tennis_options() {
    echo "<h4>Manage your Tennis Event Settings</h4>";
}

function gw_tennisHomeClub() {
    echo '<select name="gw_tennis_home_club">' . gw_get_clubs() . '</select>';
}

function gw_tennisSeason() {
    // $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    // echo '<input type="number" placeholder="Min: 1, max: 100"
    // min="1" max="100" step="1" name="gw_tennis_home_club" value="' . $homeClubId . '" /><p>Max 100 and at least 1</p>';
	$history_retention = esc_attr( get_option(TennisEvents::OPTION_HISTORY_RETENTION, TennisEvents::OPTION_HISTORY_RETENTION_DEFAULT));
    $currentYear = date('Y');
    $min = $currentYear - $history_retention + 1;
    $max = $currentYear;
    $season = esc_attr( get_option(TennisEvents::OPTION_TENNIS_SEASON, $currentYear) );
    echo "<input id='gw_tennis_event_season' name='gw_tennis_event_season' type='number' value='{$season}' min='{$min}' max='{$max}' step='1' maxlength='4' size='5' >";
}

function gw_tennisHistory() {
    $min = 1;
    $max = 7;
    $history = esc_attr( get_option(TennisEvents::OPTION_HISTORY_RETENTION,  TennisEvents::OPTION_HISTORY_RETENTION_DEFAULT) );
    echo "<input id='gw_tennis_event_history' name='gw_tennis_event_history' type='number' value='{$history}' min='{$min}' max='{$max}' step='1' maxlength='4' size='4' >";
    echo "<span>&nbsp;seasons</span>";
}

function gw_tennisMinElim() {
    $maxPlayers = TournamentDirector::MAXIMUM_ENTRANTS;
    $defaultMin = TournamentDirector::MINIMUM_ENTRANTS;
    $optName = TournamentDirector::OPTION_MIN_PLAYERS_ELIM;
    $minPlayers = TournamentDirector::getMinPlayersForElimination();
    echo "<input id='{$optName}' name='{$optName}' type='number' value='{$minPlayers}' min='{$defaultMin}' max='{$maxPlayers}' max= step='1' maxlength='4' size='4'>";
}

function gw_tennisMinRR() {
    $maxPlayers = TournamentDirector::MAXIMUM_ENTRANTS;
    $defaultMin = TournamentDirector::MINIMUM_RR_ENTRANTS;
    $optName = TournamentDirector::OPTION_MIN_PLAYERS_RR;
    $minPlayers = TournamentDirector::getMinPlayersForRoundRobin();
    echo "<input id='{$optName}' name='{$optName}' type='number' value='{$minPlayers}' min='{$defaultMin}' max='{$maxPlayers}' max= step='1' maxlength='4' size='4'>";
}

function gw_tennisMinLeadTime() {
    $leadTime = esc_attr( get_option(TennisEvents::OPTION_MINIMUM_LEADTIME, 3) );
    $optName = TennisEvents::OPTION_MINIMUM_LEADTIME;
    echo "<input id='{$optName}' name='{$optName}' type='number' value='{$leadTime}' min='1' max='7' max= step='1' maxlength='4' size='4'>";
    $txt = __("days after signup ends",TennisEvents::TEXT_DOMAIN);
    echo "<span>&nbsp;{$txt}</span>";
}

function gw_tennisMinSuggestionTime() {
    $suggestTime = esc_attr( get_option(TennisEvents::OPTION_MINIMUM_DURATION_SUGGESTIONS, 0) );
    $optName = TennisEvents::OPTION_MINIMUM_DURATION_SUGGESTIONS;
    echo "<input id='{$optName}' name='{$optName}' type='number' value='{$suggestTime}' min='0' max='99' max= step='1' maxlength='4' size='4'>";
    $txt = __("min days before completion dates are suggested for elimination tournaments (0 = no suggestions)",TennisEvents::TEXT_DOMAIN);
    echo "<span>&nbsp;{$txt}</span>";
}

function gw_tennisLockdown() {
    $lockdown = esc_attr( get_option(TennisEvents::OPTION_LOCK_PREVIOUS_SEASONS, 'no') );
    $yesChecked = '';
    $noChecked = '';
    if($lockdown == 'yes') {
        $yesChecked = 'checked';
    }
    else {
        $noChecked = 'checked';
    }
    
    $optName = TennisEvents::OPTION_LOCK_PREVIOUS_SEASONS;
    
    $txt = __("Are old events prevented from being edited?",TennisEvents::TEXT_DOMAIN);
    $templ = <<<EOT
        <fieldset>
        <legend>Choose:</legend>
        <div>
            <input type="radio" id="tennis_yes_lockdown" name="{$optName}" value="yes" {$yesChecked} />
            <label for="tennis_yes_lockdown">Yes</label>
        </div>

        <div>
            <input type="radio" id="tennis_no_lockdown" name="{$optName}" value="dewey" {$noChecked} />
            <label for="tennis_no_lockdown">No</label>
        </div>
        <div>{$txt}</div>
        </fieldset>
    EOT;
    echo $templ;

}

function gw_sanitize_clubId( $input ) {
    $output = 0;
    $message = null;
    $type = null;

    if( !is_null( $input ) ) {
        try {
            $club = Club::get( $input );
            if( !is_null( $club ) ) {
                $output = $club->getID();
                update_option('blogname', $club->getName());
                $type = 'success';
                $message = __('Default tennis club setting updated', TennisEvents::TEXT_DOMAIN );
            }
            else {
                $type = 'error';
                $message = __('Default tennis club setting failed to find club.', TennisEvents::TEXT_DOMAIN );

            }
        }
        catch( Exception $ex ) {
            $output = 0;
            $type = 'error';
            $message = __('Default tennis club setting failed to find club: ', TennisEvents::TEXT_DOMAIN ) . $ex->getMessage();
        }
    }    

    add_settings_error('gw_tennisHomeClub', esc_attr('default_club_updated'), $message, $type);

    return $output;
}

function gw_sanitize_season( $input ) {
    $message = null;
    $type = null;
    $output = date('Y');
    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            if( $input < (date('Y') - 10) ) $output = date('Y') - 10;
            if( $input > date('Y')) $output = date('Y');
            $type = 'success';
            $message = __('Tennis season setting updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis season setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis season setting must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisSeason', esc_attr('main_tennis_season_updated'), $message, $type);

    return $output;
}

function gw_sanitize_history( $input ) {
    $message = null;
    $type = null;
    $output = 5;

    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis history retention updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis history retention setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis history retention must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisHistory', esc_attr('main_tennis_history_updated'), $message, $type);

    return $output;
}

function gw_sanitize_minium_players( $input ) {
    $message = null;
    $type = null;
    $output = TournamentDirector::MINIMUM_ENTRANTS;

    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis minimum elimination players setting updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis minimum elimination players setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis minimum elimination players must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisMinElim', esc_attr('main_tennis_min_players_updated'), $message, $type);

    return $output;
}

function gw_sanitize_minium_players_rr( $input ) {
    $message = null;
    $type = null;
    $output = TournamentDirector::MINIMUM_RR_ENTRANTS;

    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis minimum round robin players setting updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis minimum round robin players setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis minimum round robin players must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisMinRR', esc_attr('main_tennis_min_rr_players_updated'), $message, $type);
    
    return $output;
}
  
function gw_sanitize_minium_leadtime( $input ) {
    $message = null;
    $type = null;
    $output = 3;

    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis minimum lead time updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis minimum lead time setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis minimum lead time must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisMinLeadTime', esc_attr('main_tennis_min_leadtime_updated'), $message, $type);

    return $output;
}
  
function gw_sanitize_minimum_suggestions( $input ) {
    $message = null;
    $type = null;
    $output = 0;

    if( !is_null( $input ) ) {
        if( is_numeric( $input ) ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis minimum suggestion time updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis minimum suggestion time setting must be numeric', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis minimum suggestion time must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisMinSuggestionTime', esc_attr('main_tennis_min_suggestiontime_updated'), $message, $type);

    return $output;
}

function gw_sanitize_lockdown_previous( $input ) {
    $message = null;
    $type = null;
    $output = 'no';

    if( !is_null( $input ) ) {
        $input = trim(strtolower($input));
        if( is_string( $input ) && in_array($input,['yes','no'])  ) {
            $output = $input;
            $type = 'success';
            $message = __('Tennis lockdown previous seasons updated', TennisEvents::TEXT_DOMAIN );
        }
        else {
            $type = 'error';
            $message = __('Tennis lockdown previous seasons setting must be a string', TennisEvents::TEXT_DOMAIN );
        }
    }
    else {
        $type = 'error';
        $message = __('Tennis lockdown previous seasons setting must not be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisLockdown', esc_attr('main_tennis_lockdown_previous_updated'), $message, $type);

    return $output;
}

function gw_get_clubs() { 
    $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    $out = '';
    $allClubs = Club::search('');
    foreach( $allClubs as $club ) {
        if($club->getID() == $homeClubId ) {
            $out .= "<option selected value='{$club->getID()}'>{$club->getName()}</option>" . PHP_EOL;
        }
        else {
            $out .= "<option value='{$club->getID()}'>{$club->getName()}</option>" . PHP_EOL;
        }
    }
    return $out;
}

function gw_get_events() {
    $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    $mainEventId = esc_attr( get_option('gw_tennis_main_event', 0) );
    $out = '';
    $allEvents = Event::find( ['club' => $homeClubId] );
    foreach( $allEvents as $event ) {
        if( $event->isLeaf()) continue;
        
        if( $mainEventId == $event->getID() ) {
            $out .= "<option selected value='{$event->getID()}'>{$event->getName()}</option>" . PHP_EOL;
        }
        else {
            $out .= "<option value='{$event->getID()}'>{$event->getName()}</option>" . PHP_EOL;
        }
    }
    return $out;
}



