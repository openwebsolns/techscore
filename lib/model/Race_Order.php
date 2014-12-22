<?php
/*
 * This file is part of Techscore
 */



/**
 * Template for ordering races in team racing
 *
 * The ID of the entry is a varchar that encodes the four parameters
 * which define a template:
 *
 * (# of divs)-(# of teams)-(# of boats)-(in/frequent)
 *
 * A value of '0' for the last entry means 'infrequent
 * rotation'. Thus, a template that defines 6 teams in 18 boats,
 * rotating frequently, would have ID = 6-18-1.
 *
 * The 'template' property is an array, each successive entry of which
 * is the next "race", encoded as a string. The string is of the form
 * "X-Y", where X and Y represent the (n+1)th team in the round.
 *
 * Thus, if MIT and Harvard are the second and fifth team in the
 * round, respectively, then the race "MIT vs. Harvard" would be
 * encoded as "2-5", and the opposite ("Harvard vs. MIT") would be
 * encoded "5-2". Note that indices are 1-based.
 *
 * @author Dayan Paez
 * @version 2013-05-08
 */
class Race_Order extends DBObject implements Countable {

  const FREQUENCY_FREQUENT = 'frequent';
  const FREQUENCY_INFREQUENT = 'infrequent';
  const FREQUENCY_NONE = 'none';

  public $num_teams;
  public $num_divisions;
  public $num_boats;
  public $frequency;
  public $description;
  protected $template;
  protected $author;
  protected $master_teams;

  public function db_type($field) {
    switch ($field) {
    case 'template':
    case 'master_teams':
      return array();
    case 'author':
      require_once('regatta/Account.php');
      return DB::T(DB::ACCOUNT);
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('num_divisions'=>true, 'num_teams'=>true, 'num_boats'=>true, 'master_teams' => true, 'frequency' => true);
  }

  public function getPair($index) {
    if ($this->template === null || $index < 0  || $index > count($this->__get('template')))
      return array(null, null);
    $pairings = $this->__get('template');
    return explode('-', $pairings[$index]);
  }

  public function count() {
    if ($this->template === null)
      return 0;
    return count($this->__get('template'));
  }

  public function setPairs(Array $pairs = array()) {
    $this->template = array();
    foreach ($pairs as $i => $pair) {
      if (!is_array($pair) || count($pair) != 2)
        throw new InvalidArgumentException("Invalid pair entry with index $i.");
      $this->template[] = implode('-', $pair);
    }
  }

  public static function getFrequencyTypes() {
    return array(self::FREQUENCY_FREQUENT => "Frequent rotation",
                 self::FREQUENCY_INFREQUENT => "Infrequent rotation",
                 self::FREQUENCY_NONE => "No rotation");
  }

  /**
   * Concatenation of num_divisions, num_teams, num_boats, and frequency
   *
   * These values ought to be globally unique per race order.
   *
   * @return String the hash
   */
  public function hash() {
    return sprintf('%s-%s-%s-%s', $this->num_divisions, $this->num_teams, $this->num_boats, $this->frequency);
  }
}
