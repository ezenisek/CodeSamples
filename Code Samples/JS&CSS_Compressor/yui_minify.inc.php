<?php
/*******************************************************************************
 * yui_minify.inc.php - Maestro
 * Written by Ed Zenisek, March 19, 2014
 * New Mexico State Univeristy
 *
 * The purpose of this file is to take all of the CSS and JS files associated
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
 * Finally, we echo out the script and css html statements to include them in
 * the page.  The whole process takes about a millisecond if the minification
 * does not need to run, so we're not losing any performance as long as the
 * files have not changed.  Depending on the number of files and their size,
 * the minification process can take a few seconds if things need to be rebuilt.
 */

$stylesheets = array(
    $docroot.getSetting('jqueryuicsspath'),
    $docroot.'/colorbox/colorbox.css',
    $docroot.'/javascript/qtip/jquery.qtip.css',
    $docroot.'/javascript/pace/pace.css',
    $docroot.'/javascript/select2/select2.css',
    $docroot.'/javascript/dropzone/dropzone.css',
    $docroot.'/javascript/jqueryplugins/uploadfile.css',
    $docroot.'/javascript/jqueryplugins/parsley.css',
    $docroot.'/javascript/jqueryplugins/badger.css',
    $docroot.'/javascript/datatables/media/css/jquery.dataTables_themeroller.css',
    $docroot.'/javascript/jCounter/css/jquery.jCounter-iosl.css',
    $docroot.'/includes/maestrostyle.css',
    $docroot.'/javascript/popnote/popnote.css',
);
$javascript = array(
    $docroot.getSetting('jquerypath'),
    $docroot.getSetting('jqueryuipath'),
    $docroot.'/colorbox/jquery.colorbox-min.js',
    $docroot.'/mcke/ckeditor.js',
    $docroot.'/javascript/spin.js',
    $docroot.'/javascript/errorhandler.js',
    $docroot.'/javascript/pace/pace.min.js',
    $docroot.'/javascript/popnote/jquery.popnote.js',
    $docroot.'/javascript/noty/packaged/jquery.noty.packaged.js',
    $docroot.'/javascript/autosize-master/jquery.autosize.js',
    $docroot.'/javascript/qtip/jquery.qtip.js',
    $docroot.'/javascript/datatables/media/js/jquery.dataTables.js',
    $docroot.'/javascript/select2/select2.js',
    $docroot.'/javascript/dropzone/dropzone.js',
    $docroot.'/javascript/iframe-resizer/iframeResizer.min.js',
    $docroot.'/javascript/iframe-resizer/iframeResizer.contentWindow.min.js',
    $docroot.'/javascript/jCounter/js/jquery.jCounter-0.1.4.js',
    $docroot.'/javascript/jqueryplugins/jquery.sticky.js',
    $docroot.'/javascript/jqueryplugins/jquery.form.min.js',
    $docroot.'/javascript/jqueryplugins/jquery.uploadfile.js',
    $docroot.'/javascript/jqueryplugins/jquery.spin.js',
    $docroot.'/javascript/jqueryplugins/jquery.elevatezoom.js',
    $docroot.'/javascript/jqueryplugins/parsley.js',
    $docroot.'/javascript/jqueryplugins/badger.js',
    $docroot.'/javascript/jqueryplugins/jquery.megamenu.js',
    $docroot.'/javascript/jqueryplugins/jquery.scrolltab.js',
    $docroot.'/javascript/maestro.js',
    $docroot.'/javascript/notifications.js',
    $docroot.'/javascript/modernizr/modernizr.custom.js',

);

$writedir=$docroot.'/min';

function minifyFiles($cat,$newfile) {
  global $docroot;
  global $writedir;

   // Find and resolve any images in this file if it is css
   $ext = pathinfo($newfile, PATHINFO_EXTENSION);
   if($ext == 'css') {
     preg_match_all('/url\(\s*[\'"]?(\S*\.(?:jpe?g|gif|png))[\'"]?\s*\)[^;}]*?/i', $cat, $urls);
     $urls = $urls[0];
     $imgfiles = array();
     foreach($urls as $u) {
       $u = str_replace('url(','',$u);
       $u = trim($u,") (");
       $rep = $u;
       $u = trim($u,"' \" .");
       $imgfile = basename($u);
       $cat = str_replace($rep,'images/'.$imgfile,$cat);
       $imgfiles[] = $imgfile;
     }
     $imgfiles = array_unique($imgfiles);
     $dir = new RecursiveDirectoryIterator($docroot);
     $imgdir = $writedir.'/images';
     if(!is_dir($imgdir)) {
       mkdir($imgdir, 0777, true);
     }
     foreach(new RecursiveIteratorIterator($dir) as $file) {
       $fn = basename($file);
       if(in_array($fn,$imgfiles)) {
           copy($file,$imgdir.'/'.$fn);
       }
     }
   }

   // Write the file
   if(!file_put_contents($newfile,$cat))
     writeLog("Could not write new file ($newfile)");

   // Check for YUI Compressor
   $yui = getSetting('yui_location');
   if(!file_exists($yui)){
      writeLog("YUI Compressor not found.  Concatinating files with no minification.");
      return true;
   }
   else {
      try{
        // Minify
        $cmd = "java -jar $yui $newfile -o -v $newfile";
        exec($cmd);
      }
      catch(Exception $e){
        writeLog("Could not minify and write new file ($newfile)");
        return false;
      }
      return true;
   }
}

function combineFiles($files,$prepend,$append){
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
      $output .= trim(file_get_contents($file));
  }
  $output .= $append;
  return $output;
}

// Adding uncompressed Google Font Calls
echo "<link href='https://fonts.googleapis.com/css?family=Raleway:500%7CArimo:400,400italic,700' rel='stylesheet' type='text/css'>";

if(isset($_GET['noyui']) && $_GET['noyui'] == 'yes'){
  foreach($javascript as $js){
    $js = str_replace($docroot,'',$js);
   echo "<script type='text/javascript' src='$js'></script>\n";
  }
  foreach($stylesheets as $css){
    $css = str_replace($docroot,'',$css);
   echo "<link href='$css' rel='stylesheet' type='text/css'>\n";
  }
}
else{

  // Get the dates first, if we need to re-load them we'll do it later
  $ret = '';
  foreach($javascript as $js) {
    if(filemtime($js))
      $ret .= date("YmdHis", filemtime($js)).$js;
  }
  $jsmd5 = md5($ret);
  $ret = '';
  foreach($stylesheets as $css) {
    if(filemtime($css))
      $ret .= date("YmdHis", filemtime($css)).$css;
  }
  $cssmd5 = md5($ret);

  $curjs = glob($writedir.'/*.js');
  $curjs = $curjs[0];
  $curcss = glob($writedir.'/*.css');
  $curcss = $curcss[0];

  $prependjs = "var CKEDITOR_BASEPATH='".getSetting('rootlink')."/mcke/';";
  $prependcss = '';
  $appendjs = '';
  $appendcss = '';

  // CSS
  if(!empty($curcss)) {
    if($curcss != $writedir.'/'.$cssmd5.'.css') {
      // The CSS has changed since the last combination / minification
      $finalcss = combineFiles($stylesheets,$prependcss,$appendcss);

      minifyFiles($finalcss,$writedir.'/'.$cssmd5.'.css');
      $cssfile = $rootlink.'/min/'.$cssmd5.'.css';
      writeLog("New CSS file written ($cssmd5.css)");
      // Delete the old file
      unlink($curcss);
      writeLog("Old CSS file removed ($curcss)");
    }
    else {  // The file has not changed
      $cssfile = $rootlink.'/min/'.basename($curcss);
    }
  }
  else { // The file doesn't even exist
    $finalcss = combineFiles($stylesheets,$prependcss,$appendcss);
    minifyFiles($finalcss,$writedir.'/'.$cssmd5.'.css');
    $cssfile = $rootlink.'/min/'.$cssmd5.'.css';
    writeLog("New CSS file written ($cssmd5.css)");
  }

  // JS
  if(!empty($curjs)) {
    if($curjs != $writedir.'/'.$jsmd5.'.js') {
      // The JS has changed since the last combination / minification
      $finaljs = combineFiles($javascript,$prependjs,$appendjs);
      minifyFiles($finaljs,$writedir.'/'.$jsmd5.'.js');
      $jsfile = $rootlink.'/min/'.$jsmd5.'.js';
      writeLog("New JS file written ($jsmd5.js)");
      // Delete the old file
      unlink($curjs);
      writeLog("Old JS file removed($curjs)");
    }
    else { // The file has not changed
      $jsfile = $rootlink.'/min/'.basename($curjs);
    }
  }
  else { // The file doesn't even exist
    $finaljs = combineFiles($javascript,$prependjs,$appendjs);
    minifyFiles($finaljs,$writedir.'/'.$jsmd5.'.js');
    writeLog("New JS file written ($jsmd5.js)");
  }

  echo "<link href='$cssfile' rel='stylesheet' type='text/css'>\n";
  echo "<script type='text/javascript' src='$jsfile'></script>\n";
}