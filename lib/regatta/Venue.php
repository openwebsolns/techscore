<?php
/* This file is part of TechScore
 *
 * @version 2.0
 * @package regatta
 */

/**
 * Event venue objects
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-21
 */
class Venue {
  public $id;
  public $name;
  public $address;
  public $city;
  public $state;
  public $zipcode;

  // Constants
  const FIELDS = "venue.id, venue.name, venue.address, venue.city, venue.state, venue.zipcode";
  const TABLES = "venue";
}
?>