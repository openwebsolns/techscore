<?php
/*
 * This file is part of Techscore
 */



/**
 * Encapsulates an immutable penalty or breakdown
 *
 * @author Dayan Paez
 * @version 2011-01-31
 */
class FinishModifier extends DBObject {

  protected $finish;
  public $amount;
  public $type;
  public $comments;
  /**
   * @var boolean when scoring penalty or breakdown, should the score
   * displace other finishers behind this one? Note that for penalty,
   * this is usually 'yes', which leads to everyone else being bumped
   * up. For breakdowns, however, this is usually 'no'. Note that this
   * is invalid if the 'amount' is non-positive.
   */
  public $displace;

  /**
   * Fetches an associative list of the different penalty types
   *
   * @return Array<Penalty::Const,String> the different penalties
   */
  public static function getList() {
    return array();
  }

  public function db_name() { return 'finish_modifier'; }
  public function db_type($field) {
    if ($field == 'finish')
      return DB::T(DB::FINISH);
    return parent::db_type($field);
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
  public function __construct($type = null, $amount = -1, $comments = "", $displace = 0) {
    if ($this->type === null)     $this->type = $type;
    if ($this->amount === null)   $this->amount = (int)$amount;
    if ($this->comments === null) $this->comments = $comments;
    if ($this->displace === null) $this->displace = $displace;
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return $this->type;
  }
}
