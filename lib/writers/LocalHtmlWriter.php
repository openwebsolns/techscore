<?php
namespace writers;

use \Writeable;

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

  const PARAM_HTML_ROOT = 'html_root';

  /**
   * @var String root directory in which to write HTML files
   */
  private $root = null;

  /**
   * Creates a new writer with provided params.
   *
   * For backwards compatibility, will check S3Writer.conf.local.php
   * if no parameters provided.
   */
  public function __construct(Array $params) {
    $this->root = $params[self::PARAM_HTML_ROOT] ?? realpath(dirname(__FILE__).'/../../html');
  }

  /**
   * Returns the root at which to write the files
   *
   * @return String the file root, which must exist
   * @throws TSScriptException if root cannot be found/created
   */
  protected function getRoot() {
    return $this->root;
  }

  /**
   * Writes the files to the local filesystem
   *
   * @see AbstractWriter::write
   */
  public function write($fname, Writeable $elem) {
    $R = $this->getRoot();
    $dir = dirname($fname);
    $root = $R . $dir;
    if (!is_dir($root) && mkdir($root, 0777, true) === false)
      throw new TSWriterException("Unable to create directory $root", 2);

    $file = fopen($R . '/' . $fname, 'w');
    $elem->write($file);
    fclose($file);
  }

  /**
   * Removes the given file/directory from filesystem
   *
   * This method also trims empty directories
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
      // empty dirs?
      while (strlen($root) > 1) {
        $root = dirname($root);

        if (($res = scandir($root)) === false)
          throw new TSWriterException("Unable to scan directory $root.");
        if (count($res) > 2)
          break;

        if (rmdir($root) === false)
          throw new TSWriterException("Unable to remove directory $root.");
      }
      return;
    }

    // directory
    $d = scandir($root);
    if ($d === false)
      throw new TSWriterException("Unable to open directory $root");

    foreach ($d as $file) {
      if ($file != '.' && $file != '..') {
        $path = "$fname/$file";
        $this->remove($path);
      }
    }
  }
}
