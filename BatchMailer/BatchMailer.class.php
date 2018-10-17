<?php
/* BatchMailer Class
 *
 * Author: Ed Zenisek
 * Version: 1
 * Date: 2018-10-17
 *
 * This BatchMailer class extends PHPMailer to allow for sending emails in
 * batches.  Batch information is stored in a folder location on the server
 * until all the batches complete.  This is done so that this class can be
 * called via command line instead of a via a browser in the case that a
 * mailing is very large and it allows a user to browse away from the script or
 * close their browser window while this runs in the background.
 *
 * We do this by overriding the Send() method from PHPMailer.  By the time we
 * get to Send, everything should be set up and ready to go so we can get all
 * the information we need.  When Send() is called, we'll grab all the settings
 * for this email, including recipients, content, attachments, etc.  We'll then
 * store that information on the server in a temporary location so we can
 * retrieve it from the command line script and start sending in the background.
 *
 * The command line script (batchfile) is an accompanying piece of code that
 * runs the batches on the server.
 *
 * The unfortunate downside of doing things this way is that there isn't a way
 * to report errors to the end user during the process.  Because of this, the
 * batchfile will send an email report with the results to the "From" address
 * once the entire batch is complete.
 */

class BatchMailer extends PHPMailer{

  public $batchsize = 250;
  public $batchstatus = false;
  public $storagefolder = '/path/to/batch/storage/file';
  public $batchfile = '/path/to/runbatch.php';
  public $masterlist = array();
  public $owneremail = '';
  public $resultfrom = 'fromaddress@someemail.com';

  public $classfile = false;

  //Base address should be a throwaway address we can use to send each batch to,
  //so that we can send it as BCC to everyone else.
  public $baseAddress = 'somethrowaway@someemail.com';

  public $batches = 0;

  private $remaining = array();

  function __construct(){
    parent::__construct(true);
  }

  function Send(){
    // Grab all the information from the send so we can batch process it, but
    // only if the number of recipients is over the batchsize.
    $recips = $this->getAllRecipientAddresses();
    if(count($recips) < $this->batchsize){
      //Recipient count smaller than batchsize.  Simply send the email and
      //report.
      $this->batchstatus = false;
      return parent::Send();
    }
    $this->batchstatus = 1;
    return $this->batchPrepareRun();
  }

  function batchPrepareRun(){
    if(!$this->classfile){
      $this->classfile = 'batch'.time().'.txt';
    }
    $filepath = $this->storagefolder.$this->classfile;
    $classdata = serialize($this);
    try{
      if(!$newfile = fopen($filepath,"w"))
        throw new Exception("Could not fopen $filepath");
      if(!fwrite($newfile,$classdata))
        throw new Exception("Could not write $newfile");
      fclose($newfile);
    } catch(Exception $e){
      echo "Exception caught: $e->getMessage()";
    }

    // Run the batch process
    $args = array($filepath);
    $command = 'bash -c "exec nohup setsid ';
    $command .= 'php '.$this->batchfile;
    if(!empty($args) && is_array($args)){
      foreach($args as $key=>$val){
        //Escape quotes and ecapsulate in quotes
        $val = escapeshellarg($val);
        $command .= " $val";
      }
    }
    $command .= ' > /dev/null 2>&1 &"';
    writeLog('Running command: '.$command);
    exec($command);
    return true;
  }

  function batchSend(){
    /* Here we use the batchstatus and batchsize properties to process the
     * current batch.  Batchstatus determines what # batch out of the total
     * batches we're on, just to keep track.  After we send and verify that
     * the sending is good, we remove those folks from the $remaining list.
     */

    //If this is the first batch, we need to load the $remaining and
    //$masterlist.
    if($this->batchstatus == 1){
      $this->masterlist = $this->getAllRecipientAddresses();
      $this->remaining = $this->masterlist;
      $this->batches = ceil(count($this->masterlist)/$this->batchsize);
    }

    $currentlist = array_splice($this->remaining,0,$this->batchsize);
    $this->writeLog(print_r($currentlist,1));

    //Clear the list and set it up with our batch addresses.
    $this->clearAllRecipients();
    $this->AddAddress($this->baseAddress);
    foreach($currentlist as $a => $key){
      $this->AddBCC($a);
    }

    //We have the mail prepped and the recipients for this batch loaded.
    //Send it.
    try{
      parent::Send();
      $msg = "Sent batch $this->batchstatus of $this->batches. Batch size is $this->batchsize.";
      $this->writeLog($msg);
      return true;
    }catch (phpmailerException $e) {
      $this->writeLog($e->errorMessage());
      return false;
    }catch (Exception $e) {
      $this->writeLog($e->getMessage());
      return false;
    }
  }

  function batchSuccess(){
    $endmail = new PHPMailer();
    $endmail->AddAddress($this->owneremail);
    $endmail->From = $this->resultfrom;
    $endmail->FromName = 'Mass Mailer System';
    $endmail->Subject = 'Batch mailing completed successfully';
    $content = "This message is simply a notification that your recent mail job"
        ." has completed successfully. \r\n \r\nHave a great day!";
    $endmail->Body = $content;
    try{
      $endmail->Send();
    } catch(phpmailerException $e){
      $this->writeLog("Final notification email failed: $e");
    }
    catch(exeption $e){
      $this->writeLog("Final notification email failed: $e");
    }
  }

  function batchFailure(){
    $endmail = new PHPMailer();
    $endmail->AddAddress($this->owneremail);
    $endmail->From = $this->resultfrom;
    $endmail->FromName = 'Mass Mailer System';
    $endmail->Subject = 'Batch mailing encountered a problem';
    $content ="This message is a notification that your recent mail job has "
        ." failed.  Obvously this isn't supposed to happen.  Please contact "
        ." and administrator about this message. \r\n \r\nThank you.";
    $endmail->Body = $content;
    try{
      $endmail->Send();
    } catch(phpmailerException $e){
      $this->writeLog("Final notification email failed: $e");
    } catch(exeption $e){
      $this->writeLog("Final notification email failed: $e");
    }
  }

  function writeLog($msg){
    writeLog($msg);
  }
}
?>