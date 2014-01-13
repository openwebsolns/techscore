<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Grouping of sail numbers and colors for a team regatta
 *
 * Objects of this class are meant to be serialized to the database,
 * and the class is intentionally kept lightweight.
 *
 * @author Dayan Paez
 * @created 2013-05-16
 */
class TeamRotation {
  private $_count;

  /**
   * @var Array the list of sails
   */
  protected $sail = array();
  /**
   * @var Array the corresponding list of colors
   */
  protected $colors = array();

  public function __get($name) {
    if (property_exists($this, $name))
      return $this->$name;
    throw new InvalidArgumentException("Invalid property requested: $name.");
  }

  public function __set($name, $value) {
    if (!property_exists($this, $name) || $name == '_count')
      throw new InvalidArgumentException("Invalid property requested: $name.");
    if (!is_array($value))
      throw new InvalidArgumentException("Invalid value for $name, expected Array.");
    if ($this->_count !== null && $this->_count != count($value))
      throw new InvalidArgumentException(sprintf("Count for %s must be %s.", $name, $this->_count));

    $this->$name = $value;
    $this->_count = count($value);
  }

  public function count() {
    return (int)$this->_count;
  }

  public function __sleep() {
    return array('sails', 'colors');
  }

  public function __wakeup() {
    foreach ($this->__sleep() as $prop) {
      $count = count($this->$prop);
      if ($count != 0) {
        if ($this->_count !== null && $this->_count != $count)
          throw new InvalidArgumentException("Property array sizes do not match.");
        $this->_count = $count;
      }
    }
  }

  /**
   * Creates and returns sail #/color assignment for given frequency
   *
   * @param Round $round the round whose race_order to use
   * @param Array:Team $teams ordered list of teams
   * @param Array:Division the number of divisions
   * @param Const $frequency one of Race_Order::FREQUENCY_*
   * @return Array a map indexed first by race number, and then by
   * team index, and then by divisions
   */
  public function assignSails(Round $round, Array $teams, Array $divisions, $frequency) {
    if ($round->race_order === null)
      return array();

    if ($this->count() == 0)
      return array();

    $list = array();
    if ($frequency == Race_Order::FREQUENCY_FREQUENT) {
      $sailIndex = 0;
      $num_divs = count($divisions);
      for ($i = 0; $i < count($round->race_order); $i++) {
        $pair = $round->getRaceOrderPair($i);
        $list[$i] = array($pair[0] => array(), $pair[1] => array());
        foreach ($divisions as $div) {
          $sail = new Sail();
          $sail->sail = $this->sails[$sailIndex];
          $sail->color = $this->colors[$sailIndex];
          $list[$i][$pair[0]][(string)$div] = $sail;

          $sail = new Sail();
          $sail->sail = $this->sails[$sailIndex + $num_divs];
          $sail->color = $this->colors[$sailIndex + $num_divs];
          $list[$i][$pair[1]][(string)$div] = $sail;

          $sailIndex = ($sailIndex + 1) % $this->count();
        }
        $sailIndex = ($sailIndex + $num_divs) % $this->count();
      }
    }

    if ($frequency == Race_Order::FREQUENCY_NONE) {
      // Assign the sails to the teams
      $sailIndex = 0;

      $team_sails = array();
      foreach ($teams as $i => $team) {
        $team_sails[$i] = array();
        foreach ($divisions as $div) {
          $sail = new Sail();
          $sail->sail = $this->sails[$sailIndex];
          $sail->color = $this->colors[$sailIndex];
          $team_sails[$i][(string)$div] = $sail;

          $sailIndex++;
        }
      }

      for ($i = 0; $i < count($round->race_order); $i++) {
        $pair = $round->getRaceOrderPair($i);
        $list[] = array($pair[0] => $team_sails[$pair[0] - 1],
                        $pair[1] => $team_sails[$pair[1] - 1]);
      }
    }
    return $list;
  }
}

DB::$TEAM_ROTATION = new TeamRotation();
?>