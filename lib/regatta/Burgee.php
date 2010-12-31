<?php
/**
 * Simple representation of burgee
 *
 */
class Burgee {

  const FIELDS = "burgee.last_updated, burgee.filedata";
  const TABLES = "burgee";

  public $filedata;
  public $last_updated;

  /**
   * Creates a new burgee after serialization
   *
   */
  public function __construct() {
    if ($this->last_updated !== null)
      $this->last_updated = new DateTime($this->last_updated);
  }
}
?>