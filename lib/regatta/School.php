<?php
/**
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */
require_once('conf.php');

/**
 * Encapsulates a school object: id, name, nick_name, etc
 *
 * @author Dayan Paez
 * @created 2009-10-04
 */
class School {
  public $id;
  public $nick_name;
  public $name;
  protected $conference;
  public $city;
  public $state;

  /**
   * @var Burgee|null upon request, this variable will serialize the
   * appropriate burgee object from the database. If uninitialized,
   * the value will be false.
   */
  private $burgee = false;

  const FIELDS = 'school.id, school.nick_name, school.name, school.conference, school.city, school.state';
  const TABLES = 'school';

  /**
   * Used to retrieve the burgee intelligently.
   */
  public function __get($name) {
    if ($name == 'conference') {
      if (!($this->conference instanceof Conference))
	$this->conference = Preference::getConnection($this->conference);
      return $this->conference;
    }
    if ($name == "burgee") {
      // attempt to fetch it
      if ($this->burgee === false)
	$this->burgee = Preferences::getBurgee($this);
      return $this->burgee;
    }
    throw new InvalidArgumentException("No such property in School: $name.");
  }
  public function __set($name, $value) {
    if ($name != "burgee")
      throw new InvalidArgumentException("No such property to set in School: $name.");
    if (!($value instanceof Burgee) && $value !== null)
      throw new InvalidArgumentException("Burgee must be Burgee object. Get it?");
    $this->burgee = $value;
  }

  public function __toString() {
    return $this->name;
  }
}

?>