<?php
/*
 * This file is part of Techscore
 */



/**
 * A round-robin of races, for team racing applications
 *
 * @author Dayan Paez
 * @version 2012-12-06
 */
class Round extends DBObject {
  protected $regatta;
  public $title;
  public $relative_order;

  public $num_teams;
  public $num_boats;
  public $rotation_frequency;
  protected $sailoff_for_round;
  protected $race_order;
  protected $rotation;
  protected $boat;

  /**
   * @var Race_Group since team races can be "grouped" for ordering purposes
   */
  protected $round_group;

  public function db_type($field) {
    if ($field == 'regatta')
      return DB::T(DB::REGATTA);
    if ($field == 'sailoff_for_round')
      return DB::T(DB::ROUND);
    if ($field == 'round_group')
      return DB::T(DB::ROUND_GROUP);
    if ($field == 'race_order')
      return array();
    if ($field == 'boat')
      return DB::T(DB::BOAT);
    if ($field == 'rotation') {
      return DB::T(DB::TEAM_ROTATION);
    }
    return parent::db_type($field);
  }
  protected function db_order() { return array('relative_order'=>true); }
  protected function db_cache() { return true; }
  // No indication as to natural ordering
  public function __toString() { return $this->title; }

  /**
   * Does this round have an associated rotation?
   *
   * @return boolean
   */
  public function hasRotation() {
    return ($this->rotation !== null);
  }

  public static function compare(Round $r1, Round $r2) {
    return (int)$r1->relative_order - (int)$r2->relative_order;
  }

  /**
   * Sets the given round as master of this round
   *
   * If provided round is already a master, silently ignore
   *
   * @param Round $master the master
   * @param int $num_teams the number of teams to migrate
   * @throws InvalidArgumentException if $master not in same regatta, etc
   */
  public function addMaster(Round $master, $num_teams) {
    if ($master->__get('regatta') != $this->__get('regatta'))
      throw new InvalidArgumentException("Only rounds from same regatta can be masters.");
    if ($master->relative_order >= $this->relative_order)
      throw new InvalidArgumentException("Master rounds muts come before slave rounds.");

    // Is it already a master?
    foreach ($this->getMasters() as $old_rel) {
      if ($old_rel->master->id == $master->id)
        return;
    }

    $s = new Round_Slave();
    $s->slave = $this;
    $s->master = $master;
    $s->num_teams = $num_teams;
    DB::set($s);
    $this->_masters = null;
  }

  public function getMasters() {
    if ($this->_masters === null) {
      $this->_masters = array();
      foreach (DB::getAll(DB::T(DB::ROUND_SLAVE), new DBCond('slave', $this->id)) as $rel)
        $this->_masters[] = $rel;
    }
    return $this->_masters;
  }

  public function getMasterRounds() {
    $list = array();
    foreach ($this->getMasters() as $rel)
      $list[] = $rel->master;
    return $list;
  }

  public function getSlaves() {
    if ($this->_slaves === null) {
      $this->_slaves = array();
      foreach (DB::getAll(DB::T(DB::ROUND_SLAVE), new DBCond('master', $this->id)) as $rel)
        $this->_slaves[] = $rel;
    }
    return $this->_slaves;
  }

  private $_masters;
  private $_slaves;

  // ------------------------------------------------------------
  // Race orders
  // ------------------------------------------------------------

  /**
   * Fetches the pair of team indices
   *
   * @param int $index the index within the race_order
   * @return Array with two indices: team1, and team2
   */
  public function getRaceOrderPair($index) {
    if (($tmpl = $this->getTemplate()) === null || $index < 0  || $index >= count($tmpl))
      return array(null, null);
    return array($tmpl[$index]->team1, $tmpl[$index]->team2);
  }

  /**
   * Fetches the boat for given index
   *
   * @param int $index the index within the race order
   * @return Boat the corresponding boat
   */
  public function getRaceOrderBoat($index) {
    if (($tmpl = $this->getTemplate()) === null || $index < 0  || $index >= count($tmpl))
      return null;
    return $tmpl[$index]->boat;
  }

  /**
   * Convenience method returns first boat in race order
   *
   * @return Boat|null boat in first race
   */
  public function getBoat() {
    return $this->getRaceOrderBoat(0);
  }

  /**
   * Return all the boats used in this round's template
   *
   * @return Array:Boat
   */
  public function getBoats() {
    $list = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $boat = $this->getRaceOrderBoat($i);
      if ($boat !== null)
        $list[$boat->id] = $boat;
    }
    return array_values($list);
  }

  /**
   * The number of races in internal order
   *
   * @return int the count
   */
  public function getRaceOrderCount() {
    if (($tmpl = $this->getTemplate()) === null)
      return 0;
    return count($tmpl);
  }

  /**
   * Sets or unsets the race order
   *
   * @param Array:Array list of pairs
   * @param Array:Boat if provided, the boat to use in each race
   * @throws InvalidArgumentException if list of boats is invalid
   */
  public function setRaceOrder(Array $order, Array $boats = null) {
    if ($boats !== null && count($boats) != count($order))
      throw new InvalidArgumentException("List of boats must match list of races.");

    $this->_template = array();
    foreach ($order as $i => $pair) {
      if (!is_array($pair) || count($pair) != 2)
        throw new InvalidArgumentException("Missing pair for index $i.");
      $elem = new Round_Template();
      $elem->round = $this;
      $elem->team1 = array_shift($pair);
      $elem->team2 = array_shift($pair);
      $elem->boat = ($boats === null) ? $this->__get('boat') : $boats[$i];
      $this->_template[] = $elem;
    }
  }

  public function setRaceOrderBoat($index, Boat $boat) {
    $this->getTemplate();
    if ($index < 0 || $index >= count($this->_template))
      return;
    $this->_template[$index]->boat = $boat;
  }

  public function removeRaceOrder() {
    $this->_template = null;
  }

  /**
   * Actually commits the internal race order
   *
   */
  public function saveRaceOrder() {
    if ($this->_template === false)
      return;

    DB::removeAll(DB::T(DB::ROUND_TEMPLATE), new DBCond('round', $this));
    if ($this->_template !== null)
      DB::insertAll($this->_template);
  }

  /**
   * Fetches the list of race orders, as pairs
   *
   * @return Array:Array
   */
  public function getRaceOrder() {
    $res = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $res[] = $this->getRaceOrderPair($i);
    }
    return $res;
  }

  public function hasRaceOrder() {
    return $this->getTemplate() !== null;
  }

  public function getRaceBoats() {
    $res = array();
    for ($i = 0; $i < $this->getRaceOrderCount(); $i++) {
      $res[] = $this->getRaceOrderBoat($i);
    }
    return $res;
  }

  public function getTemplate() {
    if ($this->_template === false) {
      $this->_template = null;
      $list = array();
      foreach (DB::getAll(DB::T(DB::ROUND_TEMPLATE), new DBCond('round', $this)) as $entry)
        $list[] = $entry;
      if (count($list) > 0)
        $this->_template = $list;
    }
    return $this->_template;
  }

  private $_template = false;

  // ------------------------------------------------------------
  // Rotation
  // ------------------------------------------------------------

  /**
   * Fetch the list of sails
   *
   * @return Array:String list of sails
   */
  public function getSails() {
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->sails;
  }

  /**
   * Fetch the list of colors
   *
   * @return Array:String corresponding list of colors
   */
  public function getColors() {
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->colors;
  }

  /**
   * Sets the list of sails
   *
   * @param Array:String $sails
   */
  public function setSails(Array $sails = array()) {
    if ($this->rotation === null)
      $this->rotation = new TeamRotation();
    $this->__get('rotation')->sails = $sails;
  }

  /**
   * Sets the list of colors
   *
   * @param Array:String $colors
   */
  public function setColors(Array $colors = array()) {
    if ($this->rotation === null)
      $this->rotation = new TeamRotation();
    $this->__get('rotation')->colors = $colors;
  }

  public function setRotation(Array $sails, Array $colors) {
    $this->rotation = new TeamRotation();
    $this->setSails($sails);
    $this->setColors($colors);
  }

  public function removeRotation() {
    $this->rotation = null;
  }

  public function getRotationCount() {
    if ($this->rotation === null)
      return 0;
    return $this->__get('rotation')->count();
  }

  public function getSailAt($i) {
    if ($this->rotation === null)
      return null;
    return $this->__get('rotation')->sailAt($i);
  }

  public function getColorAt($i) {
    if ($this->rotation === null)
      return null;
    return $this->__get('rotation')->colorAt($i);
  }

  /**
   * Creates and returns sail #/color assignment for given frequency
   *
   * @param Round $round the round whose race_order to use
   * @param Array:Team $teams ordered list of teams
   * @param Array:Division the number of divisions
   * @param Const $frequency one of Race_Order::FREQUENCY_*
   * @return Array a map of sails indexed first by race number, and then by
   *   team index, and then by divisions
   * @throws InvalidArgumentException
   */
  public function assignSails(Array $teams, Array $divisions, $frequency = null) {
    if ($frequency === null)
      $frequency = $this->rotation_frequency;
    if ($frequency === null)
      throw new InvalidArgumentException("No rotation frequency provided.");
    if ($this->rotation === null)
      return array();
    return $this->__get('rotation')->assignSails($this, $teams, $divisions, $frequency);
  }

  // ------------------------------------------------------------
  // Seeding
  // ------------------------------------------------------------

  /**
   * Set the ordered list of teams
   *
   * @param Array:Round_Seed $seeds the seeds (need not be continuous)
   */
  public function setSeeds(Array $seeds) {
    DB::removeAll(DB::T(DB::ROUND_SEED), new DBCond('round', $this));
    $list = array();
    foreach ($seeds as $seed) {
      $seed->round = $this;
      if ($seed->id === null)
        $list[] = $seed;
      else
        DB::set($seed, true);
    }
    if (count($list) > 0)
      DB::insertAll($list);
  }

  /**
   * Retrieve the list of ordered teams for this round, if any
   *
   */
  public function getSeeds() {
    return DB::getAll(DB::T(DB::ROUND_SEED), new DBCond('round', $this));
  }
}
