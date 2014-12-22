<?php
/*
 * This file is part of Techscore
 */



/**
 * Race finish: encompasses a team's finish record in a race,
 * including possible penalties, breakdowns, etc. 
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Finish extends DBObject {
  protected $race;
  protected $team;
  protected $entered;
  public $earned;
  /**
   * @var int the numerical score
   */
  protected $score;
  public $explanation;

  public function db_name() { return 'finish'; }
  protected function db_order() { return array('entered'=>true); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'race': return DB::T(DB::RACE);
    case 'team': return DB::T(DB::TEAM);
    case 'entered': return DB::T(DB::NOW);
    case 'score': return DBQuery::A_STR;
    default:
      return parent::db_type($field);
    }
  }

  /**
   * Provides for a textual representation of the finish's place
   *
   * @return String
   */
  public function getPlace() {
    if (($modifier = $this->getModifier()) !== null)
      return $modifier->type;
    return $this->score;
  }

  public function __set($name, $value) {
    if ($name == 'score') {
      if ($value instanceof Score) {
        $this->score = $value->score;
        $this->explanation = $value->explanation;
      }
      elseif ($value === null) {
        $this->score = null;
        $this->explanation = null;
      }
      else
        throw new InvalidArgumentException("Score property must be Score object.");
      return;
    }
    parent::__set($name, $value);
  }

  /**
   * Creates a new finish with the give id, team and regatta. This is
   * legacy from previous incarnation of TechScore to facilitate
   * migration and manual generation of finish object. Arguments
   * overwrite default values from DBM object creation.
   *
   * @param int $id the id of the finish
   * @param Team $team the team
   * @param Race $race the race
   */
  public function __construct($id = null, Race $race = null, Team $team = null) {
    if ($id !== null) $this->id = $id;
    if ($race !== null) $this->race = $race;
    if ($team !== null) $this->team = $team;
  }

  /**
   * @var Array:FinishModifier the modifiers (if any) for this
   * finish. The default value (null) is a flag that they have not yet
   * been deserialized from the database
   *
   * @see getModifier
   */
  private $modifiers = null;
  /**
   * @var boolean convenient flag to determine if the list of
   * modifiers has been changed
   */
  private $changed_modifier = false;

  /**
   * Convenience method removes other modifiers and optional adds new one
   *
   * @param FinishModifier $mod the modifier
   */
  public function setModifier(FinishModifier $mod = null) {
    $this->modifiers = array();
    $this->changed_modifier = true;
    if ($mod !== null) {
      $mod->finish = $this;
      $this->modifiers[] = $mod;
    }
  }

  /**
   * Adds the given modifier to the list of modifiers
   *
   * @param FinishModifier $mod the modifier to add
   */
  public function addModifier(FinishModifier $mod) {
    $this->getModifiers();
    $mod->finish = $this;
    $this->modifiers[] = $mod;
    $this->changed_modifier = true;
  }

  /**
   * Returns list of all modifiers associated with this finish
   *
   * @return Array:FinishModifier the list of modifiers
   */
  public function getModifiers() {
    if ($this->modifiers === null) {
      $this->modifiers = array();
      foreach (DB::getAll(DB::T(DB::FINISH_MODIFIER), new DBCond('finish', $this)) as $mod)
        $this->modifiers[] = $mod;
    }
    return $this->modifiers;
  }

  /**
   * Removes the given modifier from list, comparing by ID
   *
   * @param FinishModifier $mod the modifier to remove
   * @return boolean true if it was removed
   */
  public function removeModifier(FinishModifier $mod) {
    foreach ($this->getModifiers() as $i => $other) {
      if ($other->id == $mod->id) {
        unset($this->modifiers[$i]);
        $this->changed_modifier = true;
        return true;
      }
    }
    return false;
  }

  /**
   * Gets the first finish modifier, if any, for this finish.
   *
   * @return FinishModifier|null the modifier
   */
  public function getModifier() {
    $mods = $this->getModifiers();
    return (count($mods) == 0) ? null : $mods[0];
  }

  public function hasChangedModifier() { return $this->changed_modifier; }

  /**
   * Creates a hash for this finish consisting of race-team
   *
   */
  public function hash() {
    $rid = ($this->race instanceof Race) ? $this->race->id : $this->race;
    $tid = ($this->team instanceof Team) ? $this->team->id : $this->team;
    return $rid . '-' . $tid;
  }


  // Comparators

  /**
   * Compare by entered value
   *
   * @param Finish $f1 the first finish
   * @param Finish $f2 the second finish
   * @return < 0 if $f1 is less than $f2, 0 if they are the same, 1 if
   * it comes after
   */
  public static function compareEntered(Finish $f1, Finish $f2) {
    return $f1->__get('entered')->format("U") - $f2->__get('entered')->format("U");
  }

  public static function compareEarned(Finish $f1, Finish $f2) {
    if ($f1->earned === null || $f2->earned === null)
      return self::compareEntered($f1, $f2);
    return $f1->earned - $f2->earned;
  }

  
  /**
   * Helper method for team racing regattas.
   *
   * Returns string representation of finishes, such as 1-2-5.
   *
   * @param Array:Finish $places the list of finishes.
   */
  public static function displayPlaces(Array $places = array()) {
    usort($places, 'Finish::compareEarned');
    $disp = "";
    $pens = array();
    foreach ($places as $i => $finish) {
      if ($i > 0)
        $disp .= "-";
      $modifiers = $finish->getModifiers();
      if (count($modifiers) > 0) {
        $disp .= $finish->earned;
        foreach ($modifiers as $modifier)
          $pens[] = $modifier->type;
      }
      else
        $disp .= $finish->score;
    }
    if (count($pens) > 0)
      $disp .= " " . implode(",", $pens);
    return $disp;
  }
}
