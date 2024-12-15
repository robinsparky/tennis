<?php
global $wpdb;
$wpdb->show_errors = TRUE;
$table = $wpdb->prefix . 'tennis_match';		
$sqlDrop = "DROP TABLE IF EXISTS {$table}_copy";
//$safe = $wpdb->prepare( $sql );
$rows = $wpdb->get_results( $sqlDrop, ARRAY_A );

$sqlCopy = "CREATE TABLE {$table}_copy AS SELECT * FROM {$table}";
$rows = $wpdb->get_results( $sqlCopy, ARRAY_A );
$this->log->error_log( sprintf("Data upgrader-> %d copy rows returned.", $wpdb->num_rows ) );
$this->log->error_log(sprintf("Data upgrader-> copy rows last error: %s", $wpdb->last_error ));

$sqlAlter = "alter table {$table} add column `expected_end` datetime after match_date";
$rows = $wpdb->get_results( $sqlAlter, ARRAY_A );
$this->log->error_log(sprintf("Data upgrader-> alter add col last error: %s", $wpdb->last_error ));

$sqlAlter = "alter table {$table} drop column `match_type`";
$rows = $wpdb->get_results( $sqlAlter, ARRAY_A );
$this->log->error_log(sprintf("Data upgrader-> alter drop col last error: %s", $wpdb->last_error ));
