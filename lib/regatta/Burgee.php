<?php
/**
 * Simple representation of burgee
 *
 */
class Burgee {
  public $filedata;
  public $last_updated;

  /**
   * Creates a new burgee
   *
   * @param String $filedata the base 64 encoded data
   * @param DateTime $last_updated the last time the filedata was changed
   */
  public function __construct($filedata, DateTime $last_updated) {
    $this->filedata = (string)$filedata;
    $this->last_updated = $last_updated;
  }
}
?>