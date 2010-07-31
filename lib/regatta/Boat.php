<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Encapsulates boat objects, with parameters id, name, and number of
 * occupants.
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class Boat {
  public $id;
  public $name;
  public $occupants;

  const FIELDS = "boat.id, boat.name, boat.occupants";
  const TABLES = "boat";
}
?>