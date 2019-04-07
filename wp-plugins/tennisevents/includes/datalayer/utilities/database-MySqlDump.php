<?php
 
//PASS prod db credentials
$username = "carepass"; 
$password = "5T7o8ir6&"; 
$hostname = "db.care4nurses.org"; 
$dbname   = "carepass";

//PASS local dev credentials


//PASS devel on Dreamhost credentials

 
// if mysqldump is on the system path you do not need to specify the full path
// simply use "mysqldump --add-drop-table ..." in this case
$dumpfname = $dbname . "_" . date("Y-m-d_H-i-s").".sql";
$commandDir = "c:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\";
//$commandDir = "/user/bin/";
$command = $commandDir . "mysqldump --default-character-set=latin1 -N --add-drop-table --host=$hostname --user=$username --default-character-set=latin1 -N ";
if ($password) $command.= "--password=". $password ." "; 
$command.= $dbname;
$command.= " > " . $dumpfname;
system($command);
 
// zip the dump file
$zipfname = $dbname . "_" . date("Y-m-d_H-i-s").".zip";
$zip = new ZipArchive();
if($zip->open($zipfname,ZIPARCHIVE::CREATE)) 
{
   $zip->addFile($dumpfname,$dumpfname);
   $zip->close();
}
 
// read zip file and send it to standard output
if (file_exists($zipfname)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename='.basename($zipfname));
    flush();
    readfile($zipfname);
    exit;
}
