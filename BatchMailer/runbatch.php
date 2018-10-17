<?php
if(php_sapi_name() !== 'cli') {
  //Prevent access to this page from a browser, it should only be run
  //via command line.
  die("You are not authorized to view this page.");
  exit();
}
$createsession = false;
$fullpath = '/path/to/this/script/';
require_once($fullpath.'BatchMailer.class.php');
require_once($fullpath.'PHPMailerAutoload.php');

if(!isset($argv))
  dieLog("Script run without arguments");

$infofile = $argv[1];
try{
  if(!$content = file_get_contents($infofile))
    throw new Exception('Could not get file contents');
  $mailer = unserialize($content);
} catch(Exception $e){
  $msg = 'Exception caught: '. $e->getMessage();
  // Write to log or handle error
  die($msg);
}

if(!$mailer->batchSend()){
  // We've had a problem somewhere. It should be logged.  We need to
  // figure out where we are in the process and deal with the issue.
  $mailer->batchFailure();
}

//Once this batch is sent, check if this is the last batch.  If it is, delete
//the attachments and the temporary batch file.  If not, rewrite the batch file
//with the new information and call runbatch.php again.  Kill this script once
//the new one has run.
if($mailer->batchstatus == $mailer->batches){
  //Final batch sent.
  $thisdir = dirname($mailer->sentattachments[0]);
  if(is_dir($thisdir)){
    array_map('unlink',glob("$thisdir/*.*"));
    rmdir($thisdir);
  }
  unlink($fullpath.$infofile);

  //If there were any attachments, remove them from the system now that we're
  //done with them.
  foreach($mailer->sentattachments as $file){
    unlink($file);
  }
  writeLog("Mail batch finished successfully.");

  //Finish up
  $mailer->batchSuccess();

} else {
  //More batches to go.  Run the next batch and exit this script.
  sleep(3);
  $mailer->batchstatus++;
  if($mailer->batchPrepareRun()){
    exit('Success');
  } else
    die('Unknown Error.');
}
?>