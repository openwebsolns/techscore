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
}
?>