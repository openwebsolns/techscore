<?php
namespace writers;

use \Writeable;

use \XDoc;
use \XElem;
use \XText;

/**
 * Writes directly to an S3 bucket
 *
 * @author Dayan Paez
 * @created 2012-10-09
 */
class S3WriterViaCli extends AbstractWriter {
  private $bucket;

  public function __construct() {
    require(dirname(__FILE__) . '/S3Writer.conf.local.php');

    if (empty($this->bucket)) {
      throw new TSWriterException("Missing parameters for S3Writer.");
    }
  }

  /**
   * Rudimentary method to determine the type of file based on filename
   *
   * @param String $fname the name of the file
   * @throws TSWriterException if unknown
   */
  protected function getMIME($fname) {
    $tokens = explode('.', $fname);
    if (count($tokens) < 2)
      throw new TSWriterException("No extension for file $fname.");
    $suff = array_pop($tokens);
    switch ($suff) {
    case 'html':
      return 'text/html; charset=utf-8';
    case 'png':
      return 'image/png';
    case 'css':
      return 'text/css';
    case 'js':
      return 'text/javascript';
    case 'ico':
      return 'image/x-icon';
    case 'svg':
      return 'image/svg+xml';
    case 'pdf':
      return 'application/pdf';
    case 'gif':
      return 'image/gif';
    case 'jpg':
      return 'image/jpg';
    default:
      throw new TSWriterException("Unknown extension: $suff");
    }
  }

  public function write($fname, Writeable $elem) {
    $filename = tempnam(sys_get_temp_dir(), 'ts-s3-');
    $fp = fopen($filename, 'w');
    $elem->write($fp);
    fclose($fp);

    $exitCode = null;
    $output = array();
    $command = sprintf(
      'aws s3 cp %s s3://%s%s --content-type %s --no-guess-mime-type',
      $this->getMIME($fname),
      $filename,
      $this->bucket,
      $fname
    );
    exec($command, $output, $exitCode);
    unlink($filename);
    if ($exitCode !== 0) {
      throw new TSWriterException(implode("\n", $output));
    }
  }

  /**
   * Removes the given file, which must not be a directory
   *
   */
  public function remove($fname) {
    $output = array();
    $exitCode = null;
    exec(sprintf('aws s3 rm s3://%s%s', $this->bucket, $fname), $output, $exitCode);
    if ($exitCode !== 0) {
      throw new TSWriterException(implode("\n", $output));
    }
  }
}
