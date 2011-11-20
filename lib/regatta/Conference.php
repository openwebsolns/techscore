<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Encapsulates a conference object: id, nick and name properties
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class Conference {
  public $id;
  public $name;

  public function __toString() {
    return $this->id;
  }
}

?>
