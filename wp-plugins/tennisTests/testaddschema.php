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
?> 
</pre>
 

