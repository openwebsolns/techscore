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
 * @created 2010-01-25
 */
class Penalty {

  public $amount;
  public $type;
  public $comments;
  
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
  public function getList() {
    return array(Penalty::DSQ => "DSQ: Disqualification",
		 Penalty::RAF => "RAF: Retire After Finishing",
		 Penalty::OCS => "OCS: On Course Side after start",
		 Penalty::DNF => "DNF: Did Not Finish",
		 Penalty::DNS => "DNS: Did Not Start");
  }

  /**
   * Creates a new penalty, of empty type by default
   *
   * @param String $type, one of the class constants
   * @param int $amount (optional) the amount if assigned, or -1 for automatic
   * @param String $comments (optional)
   *
   * @throws InvalidArgumentException if the type is set to an illegal
   * value
   */
  public function __construct($type, $amount = -1, $comments = "") {
    if (!in_array($type, array_keys($this->getList())))
      throw new InvalidArgumentException(sprintf("Invalid penalty type %s.", $this->type));
    $this->type = $type;
    $this->amount = (int)$amount;
    $this->comments = $comments;
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s|%s|%s", $this->type, $this->amount, $this->comments);
  }
}
?>