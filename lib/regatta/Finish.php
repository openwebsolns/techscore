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
 * Encapsulates a finish and all its greatness
 *
 */
class Finish {

  // Parameters
  private $id;
  private $race;
  private $team;
  private $entered = null;
  private $penalty = null;

  private $score;
  private $explanation;

  private $listeners;

  /**
   * Creates a new finish with the give id, race, team and regatta
   *
   * @param int $id the id of the finish
   * @param Race $race the race
   * @param Team $team the team
   * @param Regatta $reg the regatta
   */
  public function __construct($id, Race $race, Team $team) {
    $this->id = (int)$id;
    $this->race = $race;
    $this->team = $team;

    $this->listeners = array();
  }
  
  public function __set($name, $value) {
    switch ($name) {
    case "entered":
      if ($value instanceof DateTime) {
	$this->entered = $value;
	$this->fireChange(FinishListener::ENTERED);
      }
      else
	throw new InvalidArgumentException("Entered property must be DateTime object.");
      break;

    case "score":
      if ($value == null ||
	  $value instanceof Score) {
	$this->score = $value->score;
	$this->explanation = $value->explanation;
	$this->fireChange(FinishListener::SCORE);
      }
      else
	throw new InvalidArgumentException("Score property must be Score object.");
      break;

    case "penalty":
      if ($value == null || $value instanceof FinishModifier) {
	$this->penalty = $value;
	$this->fireChange(FinishListener::PENALTY);
      }
      else
	throw new InvalidArgumentException("Penalty object not a valid FinishModifier.");
      break;

    default:
      throw new BadFunctionCallException(sprintf("Property (%s) not valid for Finish.", $name));
    }
  }

  public function __get($name) {
    if ($name == 'place') {
      if ($this->penalty === null)
	return $this->score;
      return $this->penalty->type;
    }
    return $this->$name;
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
    return $f1->entered->format("U") - $f2->entered->format("U");
  }

  //
  // Listeners
  //

  /**
   * Registers the listener
   *
   * @param FinishListener $listener the listener
   */
  public function addListener(FinishListener $listener) {
    $this->listeners[] = $listener;
  }

  /**
   * Fire finish change
   *
   * @param FinishListener::CONST $type the type of change
   */
  private function fireChange($type) {
    foreach ($this->listeners as $listener) {
      $listener->finishChanged($type, $this);
    }
  }
}

?>
