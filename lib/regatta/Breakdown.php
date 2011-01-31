<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */


/**
 * Encapsulates a breakdown
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class Breakdown extends Penalty {

  // Constants
  const RDG = "RDG";
  const BKD = "BKD";
  const BYE = "BYE";

  public function getList() {
    return array(Breakdown::BKD => "BKD: Breakdown",
		 Breakdown::RDG => "RDG: Yacht Given Redress",
		 Breakdown::BYE => "BYE: Team is awarded average");
  }

  /**
   * Creates a new breakdown, of BKD type and "average" amount by default
   *
   * @param String $type, one of the class constants
   * @param int $amount the amount
   * @param String comments (optional)
   * @throws InvalidArgumentException if the type is set to an illegal
   * value
   * @throws InvalidArgumentException if the type is set to an illegal
   * value
   */
  public function __construct($type, $amount, $comments = "") {
    parent::__construct($type, $amount, $comments);
  }
}
?>