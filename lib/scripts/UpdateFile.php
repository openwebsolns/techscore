<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once('AbstractScript.php');

/**
 * Serializes (or removes) public files
 *
 * The path to the file is determined automagically from the
 * filetype. If CSS file, then /inc/css/, if Javascript, then
 * /inc/js/, if image, /inc/img. Anything else, /
 *
 * @author Dayan Paez
 * @created 2013-10-04
 */
class UpdateFile extends AbstractScript {

  /**
   * Writes or removes file, based on name
   *
   */
  public function run($filename) {
    $path = '/' . $filename;
    $tokens = explode('.', $filename);
    if (count($tokens) > 1) {
      $ext = array_pop($tokens);
      if ($ext == 'css')
        $path = '/inc/css/' . $filename;
      elseif ($ext == 'js')
        $path = '/inc/js/' . $filename;
      elseif (in_array($ext, array('png', 'gif', 'jpg', 'jpeg')))
        $path = '/inc/img/' . $filename;
    }

    $obj = DB::getFile($filename);
    if ($obj === null) {
      self::remove($path);
      self::errln("Removed file $path.");
    }
    else {
      self::write($path, $obj);
      self::errln("Serialized file $path.");
    }
  }

  /**
   * Writes the init.js file
   *
   */
  public function runInitJs() {
    require_once('public/InitJs.php');
    $path = '/init.js';
    $obj = new InitJs();
    self::write($path, $obj);
    self::errln("Serialized init JS file $path.");
  }

  protected $cli_opts = '[filename] [...]';
  protected $cli_usage = "
If provided, filename will be either removed or serialized.
Leave blank to serialize all files.

Use 'init' to serialize special /init.js file.";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateFile();
  $opts = $P->getOpts($argv);
  $files = array();
  $init = false;
  foreach ($opts as $opt) {
    if ($opt == 'init')
      $init = true;
    else
      $files[] = $opt;
  }

  if (count($files) == 0) {
    foreach (DB::getAll(DB::$PUB_FILE_SUMMARY) as $file)
      $files[] = $file->id;
    $init = true;
  }
  foreach ($files as $file)
    $P->run($file);
  if ($init)
    $P->runInitJs();
}
?>