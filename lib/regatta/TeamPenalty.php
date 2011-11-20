<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Encapsulates a team penalty
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class TeamPenalty {

  public $team;
  public $division;
  public $type;
  public $comments;

  const TABLES = "penalty_team";
  const FIELDS = "team, division, type, comments";
  
  // Constants
  const PFD = "PFD";
  const LOP = "LOP";
  const MRP = "MRP";
  const GDQ = "GDQ";

  public static function getList() {
    return array(TeamPenalty::PFD=>"PFD: Illegal lifejacket",
		 TeamPenalty::LOP=>"LOP: Missing pinnie",
		 TeamPenalty::MRP=>"MRP: Missing RP info",
		 TeamPenalty::GDQ=>"GDQ: General disqualification");
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s|%s|%s|%s",
		   $this->team,
		   $this->division,
		   $this->type,
		   $this->comments);
  }
}
?>