<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpdb->show_errors();

// Team table
$teamTable = TennisEvents::getInstaller()->getDBTablenames()['team'];
$sql = "DROP TABLE IF EXISTS $teamTable";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

$sql = <<<EOS
CREATE TABLE $teamTable (
`event_ID` INT NOT NULL, 
`bracket_num` INT NOT NULL, 
`team_num` INT NOT NULL, 
`name` VARCHAR(100) NOT NULL, 
PRIMARY KEY (`event_ID`,`bracket_num`,`team_num`)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

// Squad table
$squadTable = TennisEvents::getInstaller()->getDBTablenames()['squad'];
$sql = "DROP TABLE IF EXISTS $squadTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

$sql = <<<EOS
CREATE TABLE $squadTable (
`event_ID` INT NOT NULL,
`bracket_num` INT NOT NULL,
`team_num` INT NOT NULL,
`division` VARCHAR(25) NOT NULL,
PRIMARY KEY (`event_ID`,`bracket_num`,`team_num`,`division`)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

// Tennis player
$playerTable = TennisEvents::getInstaller()->getDBTablenames()['player'];
$sql = "DROP TABLE IF EXISTS $playerTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

$sql = <<<EOS
CREATE TABLE $playerTable (
`ID`            INT NOT NULL AUTO_INCREMENT,
`event_ID`      INT NOT NULL,
`bracket_num`   INT NOT NULL,
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
`is_spare`      TINYINT DEFAULT 0,
PRIMARY KEY (`ID`),
INDEX event_bracket_idx (`event_ID`,`bracket_num`)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

// Player-Team-Squad intersection
$joinTable = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
$sql = "DROP TABLE IF EXISTS $joinTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

$sql = <<<EOS
CREATE TABLE $joinTable ( 
`event_ID`  INT NOT NULL,
`bracket_num` INT NOT NULL,
`team_num`  INT NOT NULL,
`division`  VARCHAR(2) NOT NULL,
`player_ID` INT NOT NULL,
`is_captain` TINYINT DEFAULT 0,
index event_team_idx (event_ID, bracket_num, team_num, division),
INDEX player_idx (player_ID)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

function handleDBError() {
    global $wpdb;

    if ( $wpdb->last_error ) {
        // An error occurred
        $wpdb->print_error();
        wp_die($wpdb->last_error);
    } else {
        // Query executed successfully
    }
}