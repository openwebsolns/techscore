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

  public function write($fname, Writeable $elem) {
    $filename = tempnam(sys_get_temp_dir(), 'ts-s3-');
    $fp = fopen($filename, 'w');
    $elem->write($fp);
    fclose($fp);

    $exitCode = null;
    $output = array();
    exec(sprintf('aws s3 cp %s s3://%s%s', $filename, $this->bucket, $fname), $output, $exitCode);
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
