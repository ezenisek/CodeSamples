<?php
/*******************************************************************************
 * YUI Minify Class
 * Initially Written by Ed Zenisek, March 19, 2014
 * V2 (Class) Written 6/15/2018
 * New Mexico State Univeristy
 *
 * The purpose of this class is to take all of the CSS and JS files associated
 * with the program and concatenate and minify them using YUI Compressor.  Via
 * this method, JS files are also munged.  This allows all CSS and JS files to
 * be included in program pages using only one minified file each, reducing
 * load time.  Using this script, this process is automatic and there is no
 * need to use minified JS code during development.
 *
 * Files are loaded via the stylesheets and javascript arrays.  In this way,
 * files can be in completely different folders and still be included for
 * minification.  We use a md5 hash of the modify times and filenames to
 * verify that the files have not changed since the last run of this script.
 * The md5 hash is stored as the filename of the new css and js files, so if the
 * existing filename is different than the new hash we check when the script
 * runs we need to re-run the minification and re-write the files.
 *
 * During this process, all the images used in the css files are found and
 * copied to an 'images' folder within the minified directory and all of the
 * refrences in the css are updated so that css image paths are correct in our
 * minified version.
 *
 * Finally, we save the script and css html statements so they can be placed in
 * the page.  The whole process takes about a millisecond if the minification
 * does not need to run, so we're not losing any performance as long as the
 * files have not changed.  Depending on the number of files and their size,
 * the minification process can take a few seconds if things need to be rebuilt.
 *
 * V2 Update - Updated to be a class that can be called and re-called as needed.
 * This allows for pre or post page JS minification.
 *
 * The private variables, $yui_location and $writedir need to be
 * specified for the class to function properly.  If no log file is specified,
 * a log will be written in the $writedir directory with the name yuiclass.log.
 *
 * Usage:
 * Instantiate the class with $yui_location, $writedir, and $docroot.
 * $yui_location should be the location in the filesystem of the yui compressor
 * executable. The $writedir is where the css and js files will be written,
 * along with the log files and images by default.  The $docroot is the root
 * folder from where the class scans for images contained in the css files.
 * Once the class is created,
 * load the $css_array with css files, the $preload_js_array with JS files that
 * you wish to be included before the page is loaded, and the $postload_js_array
 * with JS files that you wish to be included after the page load (on the
 * bottom). If you wish to change the default image saving or log file locations
 * you may do so by changing the $img_folder or $logfile properties.
 *
 * You may optionally add text to prepend or append to the css, pre JS, or Post
 * JS by loading the text into the corresponding prepend or append properties.
 * This could also be achieved by adding a file containing your desired text to
 * the beginning or end of the arrays.
 *
 * Once you have everything loaded call the runFullMinify method.  It will do
 * the magic and place the new minified file locations into the $final
 * properties: $final_css_file, $final_pre_js_file, and $final_post_js_file.
 * The files are returned as file names only, not including the path.  Simply
 * echo the file names out in your script or style statements as usual.  It's
 * up to you where you put them on the page.
 */
class YUIMinify {

  //Settings
  private $yui_location = '';
  private $writedir = '';
  private $docroot = '/';

  // Public Properties
  public $css_array = array();
  public $preload_js_array = array();
  public $postload_js_array = array();
  public $prepend_css ='';
  public $append_css = '';
  public $prepend_pre_js = '';
  public $append_pre_js = '';
  public $prepend_post_js = '';
  public $append_post_js = '';

  public $img_folder = 'images';
  public $logfile = '';

  private $css_md5 = '';
  private $pre_js_md5 = '';
  private $post_js_md5 = '';

  private $final_css_file = '';
  private $final_pre_js_file = '';
  private $final_post_js_file = '';

  function writeLog($msg){
    //You may wish to write your own logging function here
    if(!($fp = fopen($this->logfile,'a')))
    {
      // Now What?  We just...
      $this->dieNice("Could not write log to log file - please ensure the
          log file is writable.");
    }
    $output = date('Y-m-d H:i:s').' - '.$msg."\r\n";
    fwrite($fp,$output);
  }

  function handleError($msg,$fatal = 0){
    //You may wish to write your own error handling function here
    $this->writeLog($msg);
    if($fatal)
        dieNice("Fatal Error: $msg");
  }

  function dieNice($msg)
  {
    //By default this isn't very nice.  Might want to write your own.
    die($msg);
  }

  function __construct($writedir,$yui_location,$docroot){
      if(!empty($writedir))
        $this->writedir = $writedir;
      if(!empty($yui_location))
        $this->yui_location = $yui_location;
      if(!empty($docroot))
        $this->docroot = $docroot;

      if(empty($this->logfile))
          $this->logfile = $this->writedir.'yuiclass.log';

      //Make sure the writedir has a trailing slash.
      $this->writedir = rtrim($this->writedir, '/') . '/';

      $this->checkWriteDir();
  }

  function __get($p){
      return $this->$p;
  }

  function runFullMinify(){
    /* funFullMinify
     * This function is the heart of the class.  It runs all the other methods
     * together to determine if the files need to be re-minified, and, if so,
     * does the minification.  It will then load the minified file information
     * into the appropriate properties to be used.  If the file arrays are
     * empty, it simply writes an empty file so we have a file to compare later
     * runs against.
     *
     * We do the CSS file first, then the Pre JS file, then the Post JS file.
     */
    if(!empty($this->css_array)){
      // Load up all the css files we're going to check
      $this->loadCSS();
      // Now get the filename of the current minified css file
      $curcss = glob($this->writedir.'*.css');
      $curcss = $curcss[0];
      // If the file exists, check it.
      if(!empty($curcss)) {
        if($curcss != $this->writedir.$this->css_md5.'.css') {
          // The CSS has changed since the last combination / minification
          $this->doCombineWriteCSS();
          // Delete the old file
          unlink($curcss);
          $this->writeLog("Old CSS file removed ($curcss)");
        }
        else {  // The file has not changed
          $this->final_css_file = basename($curcss);
        }
      }
      else { // The file doesn't even exist
        $this->doCombineWriteCSS();
      }
    }
    else
      $this->final_css = '';

    if(!empty($this->preload_js_array)){
      // Load up all the JS files we're going to check
      $this->loadPreJS();
      // Now get the filename of the current PRE JS file
      $curprejs = glob($this->writedir.'*.pre.js');
      $curprejs = $curprejs[0];
      // If the file exists, check it.
      if(!empty($curprejs)) {
        if($curprejs != $this->writedir.$this->pre_js_md5.'.pre.js') {
          // The JS has changed since the last combination / minification
          $this->doCombineWritePreJS();
          // Delete the old file
          unlink($curprejs);
          $this->writeLog("Old Pre JS file removed ($curprejs)");
        }
        else {  // The file has not changed
          $this->final_pre_js_file = basename($curprejs);
        }
      }
      else { // The file doesn't even exist
        $this->doCombineWritePreJS();
      }
    }
    else {
      $this->final_pre_js = '';
      $this->writeLog("Empty preload js array found.  No files added.");
    }

    if(!empty($this->postload_js_array)){
    // Load up all the JS files we're going to check
      $this->loadPostJS();
      // Now get the filename of the current POST JS file
      $curpostjs = glob($this->writedir.'*.post.js');
      $curpostjs = $curpostjs[0];
      // If the file exists, check it.
      if(!empty($curpostjs)) {
        if($curpostjs != $this->writedir.$this->post_js_md5.'.post.js') {
          // The JS has changed since the last combination / minification
          $this->doCombineWritePostJS();
          // Delete the old file
          unlink($curpostjs);
          $this->writeLog("Old Post JS file removed ($curpostjs)");
        }
        else {  // The file has not changed
          $this->final_post_js_file = basename($curpostjs);
        }
      }
      else { // The file doesn't even exist
        $this->doCombineWritePostJS();
      }
    }
    else{
      $this->final_post_js = '';
      $this->writeLog("Empty preload js array found.  No files added.");
    }
  }

  function minifyFiles($cat,$newfile) {
    /* minifyFiles
     * This function takes the file contents($cat) and the desired file name
     * ($newfile) and, if possible, minifys them.
     * If the file is css, it parses for all the  images and places them in a
     * common folder, then it re-writes all the paths to those images to point
     * to the correct place.
     * After that, the file is written (js or css) and yui compressor is called
     * on it, if yui compressor can be found.
     */

    // Find and resolve any images in this file if it is css
    $ext = pathinfo($newfile, PATHINFO_EXTENSION);
    if($ext == 'css') {
      $this->writeLog("Re-indexing images in CSS file $newfile");
      preg_match_all(
      '/url\(\s*[\'"]?(\S*\.(?:jpe?g|gif|png))[\'"]?\s*\)[^;}]*?/i',
      $cat, $urls);
      $urls = $urls[0];
      $imgfiles = array();
      foreach($urls as $u) {
        $u = str_replace('url(','',$u);
        $u = trim($u,") (");
        $rep = $u;
        $u = trim($u,"' \" .");
        $imgfile = basename($u);
        $cat = str_replace($rep,$this->img_folder.'/'.$imgfile,$cat);
        $imgfiles[] = $imgfile;
      }
      $imgfiles = array_unique($imgfiles);
      $imgdir = $this->writedir.'images';
      if(!is_dir($imgdir)) {
        if(!mkdir($imgdir, 0777, true)){
          dieLog('Could not create new min or min/images directory.
                This may be due to a permission error.');
        }
      }
      // Search the entire site for the images found in the old css files and
      // move them to the new image location.
      $dir = new RecursiveDirectoryIterator($this->docroot);
      foreach(new RecursiveIteratorIterator($dir) as $file) {
        $fn = basename($file);
        if(in_array($fn,$imgfiles)) {
          if(!copy($file,$imgdir.'/'.$fn)){
            $msg = "Could not copy css image ($file) to new location ($imgdir)
            during compression.  This may be due to a permission error.";
            $this->handleError($msg);
          }
        }
      }
    }

    // Write the file
    if(!file_put_contents($newfile,$cat)){
      $this->writeLog("Could not write new file ($newfile)");
      return false;
    }

    // Check for YUI Compressor
    $yui = getSetting('yui_location');
    if(!file_exists($yui)){
     $this->writeLog("YUI Compressor not found.  Concatinating files with no minification.");
         return true;
    }
    else {
     try{
       // Minify
         $cmd = "java -jar $yui $newfile -o -v $newfile";
         exec($cmd);
       }
       catch(Exception $e){
        $this->writeLog("Could not minify and write new file ($newfile)");
        return false;
       }
     return true;
   }
 }

 function combineFiles($files,$prepend,$append){
     /* combineFiles
      * This function takes an array of files, and an optional $prepend and
      * $append argument.  The files are simply thrown together with the prepend
      * in front and the append behind.
      */
   if(!is_array($files) || !count($files)){
      writeLog('Empty files array sent to combineFiles');
      return false;
   }
   $output = $prepend;
   foreach($files as $file) {
     $ext = pathinfo($file, PATHINFO_EXTENSION);
     if($ext == 'php') {
       //This is a php file, we need to include it and capture the output
       ob_start();
       require($file);
       $output .= ob_get_clean();
       //If the file added any headers (js or css) we need to reset them
       header('content-type: text/html');
     }
     else
       $output .= "\n\r".trim(file_get_contents($file));
   }
   $output .= $append;
   return $output;
 }

 function doCombineWriteCSS(){
   $final_css = $this->combineFiles(
     $this->css_array,
     $this->prepend_css,
     $this->append_css);

   if($this->minifyFiles($final_css,
     $this->writedir.$this->css_md5.'.css')){
      $this->final_css_file = $this->css_md5.'.css';
      $this->writeLog("New CSS file written ($this->css_md5.css)");
   }
 }

 function doCombineWritePreJS(){
   $final_prejs = $this->combineFiles(
     $this->preload_js_array,
     $this->prepend_pre_js,
     $this->append_pre_js);

   if($this->minifyFiles($final_prejs,
     $this->writedir.$this->pre_js_md5.'.pre.js')){
      $this->final_pre_js_file = $this->pre_js_md5.'.pre.js';
      $this->writeLog("New JS file written ($this->pre_js_md5.pre.js)");
   }
 }

 function doCombineWritePostJS(){
   $final_postjs = $this->combineFiles(
     $this->postload_js_array,
     $this->prepend_post_js,
     $this->append_post_js);

   if($this->minifyFiles($final_postjs,
     $this->writedir.$this->post_js_md5.'.post.js')){
      $this->final_post_js_file = $this->post_js_md5.'.post.js';
      $this->writeLog("New JS file written ($this->post_js_md5.post.js)");
   }
 }

 function loadCSS(){
   $ret = '';
   foreach($this->css_array as $css) {
     if(filemtime($css))
       $ret .= date("YmdHis", filemtime($css)).$css;
   }
   $this->css_md5 = md5($ret);
 }

 function loadPreJS(){
   $ret = '';
   foreach($this->preload_js_array as $js) {
     if(filemtime($js))
       $ret .= date("YmdHis", filemtime($js)).$js;
   }
   $this->pre_js_md5 = md5($ret);
 }

 function loadPostJS(){
   $ret = '';
   foreach($this->postload_js_array as $js) {
     if(filemtime($js))
       $ret .= date("YmdHis", filemtime($js)).$js;
   }
   $this->post_js_md5 = md5($ret);
 }

 function checkWriteDir(){
   //This function checks to make sure the writeDir exists and is writable.
   if(!is_writable($this->writedir)){
     mkdir($this->writedir,0755,true);
     writeLog('Directory '.$this->writedir.' created');
   }
   else return true;

 }
}