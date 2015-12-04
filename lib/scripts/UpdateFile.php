<?php
namespace scripts;

use \pub\InitJs;
use \DB;
use \Pub_File;
use \Pub_File_Summary;

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
    // Handle INIT_FILE separately
    if ($filename == Pub_File::INIT_FILE) {
      $this->runInitJs();
      return;
    }

    $path = Pub_File_Summary::getUrlFromFilename($filename);

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
    $path = '/init.js';
    $obj = new InitJs();
    self::write($path, $obj);
    self::errln("Serialized init JS file $path.");
  }

  public function __construct() {
    parent::__construct();
    $this->cli_opts = '[filename] [...]';
    $this->cli_usage = sprintf(
      "
If provided, filename will be either removed or serialized.
Leave blank to serialize all files.

Use '%s' to serialize special /init.js file.",
      Pub_File::INIT_FILE
    );
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $files = array();
    foreach ($opts as $opt) {
      $files[] = $opt;
    }

    if (count($files) == 0) {
      foreach (DB::getAll(DB::T(DB::PUB_FILE_SUMMARY)) as $file)
        $files[] = $file->id;
      $files[] = Pub_File::INIT_FILE;
    }
    foreach ($files as $file) {
      $this->run($file);
    }
  }
}
