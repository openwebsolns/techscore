<?php
/**
 * This file is part of TechScore
 *
 * @package regatta
 */

/**
 * Encapsulates a score. Objects can only be created. Their
 * attributes are not setable after that.
 *
 * @author Dayan Paez
 * @created 2010-01-30
 */
class Score {

  private $place;
  private $score;
  private $explanation;

  /**
   * Create a score with the given parameters
   *
   * @param String $place the place (integer, or penalty descriptor)
   * @param int $score the numerical score
   * @param String $exp the explanation
   */
  public function __construct($place, $score, $exp = "") {
    $this->place = (string)$place;
    $this->score = (int)$score;
    $this->explanation = $exp;
  }

  /**
   * Fetches the given value
   *
   */
  public function __get($name) {
    return $this->$name;
  }
}
?>
