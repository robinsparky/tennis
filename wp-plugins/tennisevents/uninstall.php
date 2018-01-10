<?php
/*
    Need to to much more here:
    1. Delete options
    2. Remove custom db tables
    3. ????
*/
// If uninstall not called from WordPress exit 
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit (); 

global $wpdb, $wp_version;
// Delete option from options table 
delete_option( 'gw_options' ); 

// remove any additional options and custom tables

// Clear any cached data that has been removed
wp_cache_flush();
?>