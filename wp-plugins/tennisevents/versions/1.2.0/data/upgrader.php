<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpdb->show_errors();

// Match-Team-Squad-Player intersection
$joinTable = TennisEvents::getInstaller()->getDBTablenames()['match_team_player'];
$sql = "DROP TABLE IF EXISTS $joinTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

// Team table
$teamTable = TennisEvents::getInstaller()->getDBTablenames()['team'];
$sql = "DROP TABLE IF EXISTS $teamTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();
$sql = <<<EOS
CREATE TABLE `$teamTable` (
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
CREATE TABLE `$squadTable` (
	`ID` INT NOT NULL auto_increment,
	`event_ID` INT NOT NULL,
	`bracket_num` INT NOT NULL,
	`team_num` INT NOT NULL,
	`name` VARCHAR(25) NOT NULL,
	PRIMARY KEY (`ID`),
	INDEX (`event_ID`,`bracket_num`,`team_num`)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();


// Player table
$playerTable = TennisEvents::getInstaller()->getDBTablenames()['player'];
$sql = "DROP TABLE IF EXISTS $playerTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();
$sql = <<<EOS
CREATE TABLE `$playerTable` (
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
      `is_spare`	  TINYINT DEFAULT 0,
	  PRIMARY KEY (`ID`),
      INDEX event_bracket_idx (`event_ID`,`bracket_num`)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

     
// Player-Team-Squad intersection
$playerTeamSquadTable = TennisEvents::getInstaller()->getDBTablenames()["squad_player"];
$sql = "DROP TABLE IF EXISTS $playerTeamSquadTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();
$sql = <<<EOS
CREATE TABLE `$playerTeamSquadTable` (  
	`squad_ID` INT NOT NULL,
	`player_ID` INT NOT NULL,
    `is_captain` TINYINT DEFAULT 0,
    primary key (squad_ID, player_ID)) ENGINE=MyISAM;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

// Joins squads, players with rounds and matches
$joinTable = TennisEvents::getInstaller()->getDBTablenames()['match_team_player'];
$sql = "DROP TABLE IF EXISTS $joinTable;";
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();
$sql = <<<EOS
CREATE TABLE `wpy4_tennis_match_team_squad_player` (
  `squad_ID` int(11) NOT NULL,
  `player_ID` int(11) NOT NULL,
  `round_num` int(11) NOT NULL,
  `match_num` int(11) NOT NULL,
  PRIMARY KEY (`squad_ID`,`player_ID`,`match_num`,`round_num`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
EOS;
$rows = $wpdb->get_results( $sql, ARRAY_A );
handleDBError();

