<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/writers
 */

require_once('AbstractWriter.php');

/**
 * Writes file to 'html' directory in project root.
 *
 * This class may be extended to write to a different location, by
 * overriding the value of getRoot
 *
 * @author Dayan Paez
 * @created 2012-10-09
 */
class LocalHtmlWriter extends AbstractWriter {

  /**
   * @var String local cache of the root, updated by getRoot
   */
  protected static $root = null;

  /**
   * Returns the root at which to write the files
   *
   * @return String the file root, which must exist
   * @throws TSScriptException if root cannot be found/created
   */
  protected function getRoot() {
    if (self::$root === null) {
      $R = realpath(dirname(__FILE__).'/../../html');
      if ($R === false)
        throw new TSWriterException("Unable to find public directory root.");
      self::$root = $R;
    }
    return self::$root;
  }

  /**
   * Writes the files to the local filesystem
   *
   * @see AbstractWriter::write
   */
  public function write($fname, &$contents) {
    $R = $this->getRoot();
    $dir = dirname($fname);
    $root = $R . $dir;
    if (!is_dir($root) && mkdir($root, 0777, true) === false)
      throw new TSWriterException("Unable to create directory $root", 2);

    if (file_put_contents($R . '/' . $fname, $contents) === false)
      throw new TSWriterException("ERROR: unable to write to file $fname.\n", 8);
  }

  /**
   * Removes the given file/directory from filesystem
   *
   * @see AbstractWriter::remove
   */
  public function remove($fname) {
    $R = $this->getRoot();
    $root = $R . $fname;
    if (!file_exists($root))
      return;

    // regular file
    if (is_file($root)) {
      if (unlink($root) === false)
        throw new TSWriterException("Unable to remove file $root.");
      return;
    }

    // directory
    $d = opendir($root);
    if ($d === false)
      throw new TSWriterException("Unable to open directory $root");

    while (($file = readdir($d)) !== false) {
      if ($file != '.' && $file != '..') {
        $path = "$fname/$file";
        $this->remove($path);
      }
    }
    closedir($d);
    if (rmdir($root) === false)
      throw new TSScriptException("Unable to remove directory $root");
  }
}
?>