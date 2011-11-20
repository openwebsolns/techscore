<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Encapsulates a penalty
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class Penalty extends FinishModifier {

  // Constants
  const DSQ = "DSQ";
  const RAF = "RAF";
  const OCS = "OCS";
  const DNF = "DNF";
  const DNS = "DNS";

  /**
   * Fetches an associative list of the different penalty types
   *
   * @return Array<Penalty::Const,String> the different penalties
   */
  public static function getList() {
    return array(Penalty::DSQ => "DSQ: Disqualification",
		 Penalty::RAF => "RAF: Retire After Finishing",
		 Penalty::OCS => "OCS: On Course Side after start",
		 Penalty::DNF => "DNF: Did Not Finish",
		 Penalty::DNS => "DNS: Did Not Start");
  }
}
?>