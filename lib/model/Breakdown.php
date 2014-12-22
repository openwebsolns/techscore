<?php
/*
 * This file is part of Techscore
 */



/**
 * Encapsulates a breakdown
 *
 * @author Dayan Paez
 * @version 2010-01-25
 * @package regatta
 */
class Breakdown extends FinishModifier {

  // Constants
  const RDG = "RDG";
  const BKD = "BKD";
  const BYE = "BYE";

  public static function getList() {
    return array(Breakdown::BKD => "BKD: Breakdown",
                 Breakdown::RDG => "RDG: Yacht Given Redress",
                 Breakdown::BYE => "BYE: Team is awarded average");
  }
}
