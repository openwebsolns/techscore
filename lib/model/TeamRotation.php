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
  protected $sails = array();
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
   * Shelter client code from discrepancies in internal representation
   *
   */
  public function sailAt($i) {
    if (isset($this->sails[$i]))
      return $this->sails[$i];
    return null;
  }
  public function colorAt($i) {
    if (isset($this->colors[$i]))
      return $this->colors[$i];
    return null;
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
    if (!$round->hasRaceOrder())
      return array();

    if ($this->count() == 0)
      return array();

    $list = array();
    if ($frequency == Race_Order::FREQUENCY_FREQUENT) {
      $sailIndex = 0;
      $num_divs = count($divisions);
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
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

    if ($frequency == Race_Order::FREQUENCY_INFREQUENT) {
      // Group sails by number of divisions, indexed non-numerically
      $sail_groups = array();
      for ($i = 0; $i < $this->count(); $i++) {
        $num = floor($i / count($divisions));
        $id = 'group' . $num;
        if (!isset($sail_groups[$id]))
          $sail_groups[$id] = array();

        $div = $divisions[$i % count($divisions)];

        $sail = new Sail();
        $sail->sail = $this->sails[$i];
        $sail->color = $this->colors[$i];
        $sail_groups[$id][(string)$div] = $sail;
      }

      // Group races into flights
      $race_groups = array();
      $flight_size = $this->count() / (2 * count($divisions));
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
        $flight = floor($i / $flight_size);
        if (!isset($race_groups[$flight]))
          $race_groups[$flight] = array();

        $race_groups[$flight][] = $i;
      }

      // Keep teams on same sail numbers when going from one flight to
      // the next. Organize by "sail group index"
      $group_names = array_keys($sail_groups);
      $prev_flight = array();
      $next_flight = null;

      foreach ($race_groups as $group) {
        $next_flight = array();
        $available_groups = $group_names;

        // First, assign all carry overs, then distribute remaining
        foreach ($group as $race_num) {
          $pair = $round->getRaceOrderPair($race_num);
          $list[$race_num] = array();

          // First team
          if (isset($prev_flight[$pair[0]])) {
            $group_name = $prev_flight[$pair[0]];
            $i = array_search($group_name, $available_groups);
            array_splice($available_groups, $i, 1);
            $list[$race_num][$pair[0]] = $sail_groups[$group_name];
            $next_flight[$pair[0]] = $group_name;
          }

          // Second team
          if (isset($prev_flight[$pair[1]])) {
            $group_name = $prev_flight[$pair[1]];
            $i = array_search($group_name, $available_groups);
            array_splice($available_groups, $i, 1);
            $list[$race_num][$pair[1]] = $sail_groups[$group_name];
            $next_flight[$pair[1]] = $group_name;
          }
        }

        // Next, use up the boats not yet assigned
        foreach ($group as $race_num) {
          $pair = $round->getRaceOrderPair($race_num);

          if (!isset($list[$race_num][$pair[0]])) {
            $group_name = array_shift($available_groups);
            $list[$race_num][$pair[0]] = $sail_groups[$group_name];
            $next_flight[$pair[0]] = $group_name;
          }

          if (!isset($list[$race_num][$pair[1]])) {
            $group_name = array_shift($available_groups);
            $list[$race_num][$pair[1]] = $sail_groups[$group_name];
            $next_flight[$pair[1]] = $group_name;
          }
        }
        $prev_flight = $next_flight;
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

      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
        $pair = $round->getRaceOrderPair($i);
        $list[] = array($pair[0] => $team_sails[$pair[0] - 1],
                        $pair[1] => $team_sails[$pair[1] - 1]);
      }
    }
    return $list;
  }
}
