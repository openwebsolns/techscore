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
  public $conference;
  public $city;
  public $state;
  public $burgee;

  const FIELDS = 'school.id, school.nick_name, school.name, school.conference, school.city, school.state, school.burgee';

  public function __toString() {
    return $this->name;
  }
}

?>