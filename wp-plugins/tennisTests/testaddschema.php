<?php require('./wp-load.php'); ?> 
<pre > ADD/DROP Schema
<?php 
global $wpdb;
$wpdb->show_errors(); 

require_once( ABSPATH . 'wp-admin/includes/upgrade.php'); 
require_once( ABSPATH . 'wp-content/plugins/tennisevents/includes/class-tennis-install.php');

$installer = TE_Install::get_instance();
echo "<br>***************Drop Schema************************************************";
$installer->dropSchema();
// echo "<br>***************Create Schema************************************************";
// $installer->createSchema();
// echo "<br>***************Seed Data************************************************";
//$installer->seedData();
/*
$club_table = $wpdb->prefix . "tennis_club";
$sql = "CREATE TABLE `$club_table` ( 
        `ID` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        PRIMARY KEY (`ID`) );";
var_dump( dbDelta( $sql) );
$wpdb->print_error();

$court_table = $wpdb->prefix . "tennis_court";
$sql = "CREATE TABLE `$court_table` (
        `ID` INT NOT NULL COMMENT 'Same as Court Number',
        `club_ID` INT NOT NULL,
        `court_type` VARCHAR(45) NOT NULL DEFAULT 'hardcourt',
        PRIMARY KEY (`club_ID`,`ID`),
        FOREIGN KEY (`club_ID`)
          REFERENCES `$club_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$event_table = $wpdb->prefix . "tennis_event";
$sql = "  CREATE TABLE `$event_table` (
        `ID` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `club_ID` INT NOT NULL,
        PRIMARY KEY (`ID`),
        INDEX `fk_Event_Club_idx` (`club_ID` ASC),
        FOREIGN KEY (`club_ID`)
          REFERENCES `$club_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$draw_table = $wpdb->prefix . "tennis_draw";
$sql = "CREATE TABLE `$draw_table` (
        `ID` INT NOT NULL AUTO_INCREMENT,
        `event_ID` INT NOT NULL,
        `name` VARCHAR(45) NOT NULL,
        `elimination` VARCHAR(45) NOT NULL DEFAULT 'single',
        PRIMARY KEY (`ID`),
        FOREIGN KEY (`event_ID`)
          REFERENCES `$event_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$round_table = $wpdb->prefix . "tennis_round";
$sql = "CREATE TABLE `$round_table` (
        `ID` INT NOT NULL COMMENT 'Same as Round Number',
        `owner_ID` INT NOT NULL,
        `owner_type` VARCHAR(45) NOT NULL,
        PRIMARY KEY (`owner_ID`, `ID`),
        FOREIGN KEY (`owner_ID`)
          REFERENCES `$draw_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$entrant_table = $wpdb->prefix . "tennis_entrant";
$sql = "CREATE TABLE `$entrant_table` (
        `ID` INT NOT NULL,
        `draw_ID` INT NOT NULL,
        `name` VARCHAR(45) NOT NULL,
        `position` INT NOT NULL,
        `seed` INT NULL,
        PRIMARY KEY (`draw_ID`,`ID`),
        INDEX (`draw_ID` ASC, `ID` ASC),
        FOREIGN KEY (`draw_ID`)
          REFERENCES `$draw_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$match_table = $wpdb->prefix . "tennis_match";
$sql = "CREATE TABLE `$match_table` (
        `ID` INT NOT NULL COMMENT 'Same as Match number',
        `round_ID` INT NOT NULL,
        `home_ID` INT NOT NULL,
        `visitor_ID` INT NOT NULL,
        PRIMARY KEY (`round_ID`,`ID`),
        INDEX (`round_ID` ASC),
        INDEX (`home_ID` ASC),
        INDEX (`visitor_ID` ASC),
        FOREIGN KEY (`round_ID`)
          REFERENCES `$round_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION,
        FOREIGN KEY (`home_ID`) 
          REFERENCES `$entrant_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION,
        FOREIGN KEY (`visitor_ID`) 
          REFERENCES `$entrant_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$team_table = $wpdb->prefix . "tennis_team";
$sql = "CREATE TABLE `$team_table` (
      `ID` INT NOT NULL,
      `event_ID` INT NOT NULL,
      `name` VARCHAR(45) NOT NULL,
      PRIMARY KEY (`event_ID`,`ID`),
      INDEX (`event_ID` ASC),
      FOREIGN KEY (`event_ID`)
        REFERENCES `$event_table` (`ID`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$squad_table = $wpdb->prefix . "tennis_squad";
$sql = "CREATE TABLE `$squad_table` (
      `ID` INT NOT NULL,
      `team_ID` INT NOT NULL,
      `name` VARCHAR(25) NOT NULL,
      PRIMARY KEY (`team_ID`,`ID`),
      INDEX (`team_ID` ASC),
      FOREIGN KEY (`team_ID`)
        REFERENCES `$team_table` (`ID`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$game_table = $wpdb->prefix . "tennis_game";
$sql = "CREATE TABLE `$game_table` (
        `ID` INT NOT NULL COMMENT 'Same as game number',
        `match_ID` INT NOT NULL,
        `set_number` INT NOT NULL,
        `home_score` INT NOT NULL DEFAULT 0,
        `visitor_score` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`match_ID`, `entrant_ID`, `ID`),
        INDEX (`entrant_ID` ASC, `match_ID` ASC),
        FOREIGN KEY (`entrant_ID`)
          REFERENCES `$entrant_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION,
        FOREIGN KEY (`match_ID`)
          REFERENCES `$match_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$player_table = $wpdb->prefix . "tennis_player";
$sql = "CREATE TABLE `$player_table` (
      `ID` INT NOT NULL AUTO_INCREMENT,
      `first_name` VARCHAR(45) NULL,
      `last_name` VARCHAR(45) NOT NULL,
      `skill_level` DECIMAL(4,1) NULL DEFAULT 2.5,
      `emailHome`  VARCHAR(100),
      `emailBusiness` VARCHAR(100),
      `phoneHome` VARCHAR(45),
      `phoneMobile` VARCHAR(45),
      `phoneBusiness` VARCHAR(45),
      `squad_ID` INT,
      `entrant_ID` INT,
      `entrant_draw_ID` INT,
      PRIMARY KEY (`ID`),
      INDEX (`squad_ID` ASC),
      INDEX (`entrant_ID` ASC, `entrant_draw_ID` ASC),
      FOREIGN KEY (`squad_ID`)
        REFERENCES `$squad_table` (`ID`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
      FOREIGN KEY (`entrant_draw_ID`, `entrant_ID`)
        REFERENCES `$entrant_table` (`draw_ID`, `ID`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();

$booking_table = $wpdb->prefix . "tennis_court_booking";
$sql = "CREATE TABLE `$booking_table` (
        `club_ID` INT NOT NULL,
				`match_ID` INT NOT NULL,
        `court_ID` INT NOT NULL,
        `book_date` DATE NULL,
        `book_time` TIME(6) NULL,
        PRIMARY KEY (`club_ID`, `match_ID`, `court_ID`),
        INDEX (`club_ID` ASC, `match_ID` ASC, `court_ID` ASC),
        FOREIGN KEY (`club_ID`, `court_ID`)
          REFERENCES `$court_table` (`club_ID`, `ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION,
        FOREIGN KEY (`match_ID`)
          REFERENCES `$match_table` (`ID`)
          ON DELETE NO ACTION
          ON UPDATE NO ACTION);";
var_dump( dbDelta( $sql) ); 
$wpdb->print_error();
*/
?> 
</pre>
 

