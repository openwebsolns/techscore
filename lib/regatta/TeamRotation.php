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
 * This provides TS the ability to remember what rotation(s) have been
 * utilized in a given regatta.
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
   * @var Array the list of sails for the first team
   */
  protected $sails1 = array();
  /**
   * @var Array the list of sails for the second team
   */
  protected $sails2 = array();
  /**
   * @var Array the list of colors for the first team
   */
  protected $colors1 = array();
  /**
   * @var Array the list of colors for the second team
   */
  protected $colors2 = array();

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
    return array('sails1', 'sails2', 'colors1', 'colors2');
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

    $sails1 = $this->__get('sails1');
    $sails2 = $this->__get('sails2');
    $colors1 = $this->__get('colors1');
    $colors2 = $this->__get('colors2');

    $list = array();
    if ($frequency == Race_Order::FREQUENCY_FREQUENT) {
      $sailIndex = 0;
      for ($i = 0; $i < count($round->race_order); $i++) {
        $pair = $round->getRaceOrderPair($i);
        $list[$i] = array($pair[0] => array(), $pair[1] => array());
        foreach ($divisions as $div) {
          $sail = new Sail();
          $sail->sail = $sails1[$sailIndex];
          $sail->color = $colors1[$sailIndex];
          $list[$i][$pair[0]][(string)$div] = $sail;

          $sail = new Sail();
          $sail->sail = $sails2[$sailIndex];
          $sail->color = $colors2[$sailIndex];
          $list[$i][$pair[1]][(string)$div] = $sail;

          $sailIndex = ($sailIndex + 1) % count($sails1);
        }
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
          $sail->sail = $sails1[$sailIndex];
          $sail->color = $colors1[$sailIndex];
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