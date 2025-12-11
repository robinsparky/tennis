<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$this->log->error_log("Version Upgrader 1.1.0");

$datapath = plugin_dir_path( __FILE__ ) . 'data\\upgrader.php';
$datapath = str_replace( '\\', DIRECTORY_SEPARATOR, $datapath );
$this->log->error_log("Data: {$datapath}");
include $datapath;

$filespath = plugin_dir_path( __FILE__ )  . 'files\\upgrader.php';
$filespath = str_replace( '\\', DIRECTORY_SEPARATOR, $filespath );
$this->log->error_log("Files: {$filespath}");