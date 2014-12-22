<?php
/*
 * This class is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Questions asked by end-users
 *
 * @author Dayan Paez
 * @version 2014-12-11
 */
class Question extends DBObject {
  protected $asker;
  protected $asked_on;
  public $subject;
  public $question;
  public $referer;

  public function db_type($field) {
    switch ($field) {
    case 'asker':
      require_once('regatta/Account.php');
      return DB::T(DB::ACCOUNT);
    case 'asked_on':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }

  /**
   * Return all answers for this question
   *
   * @param boolean $publishable only return publishable answers
   * @return Array:Answer
   */
  public function getAnswers($publishable = false) {
    $cond = new DBCond('question', $this);
    if ($publishable !== false)
      $cond = new DBBool(array($cond, new DBCond('publishable', 1)));

    require_once('regatta/Answer.php');
    return DB::getAll(DB::T(DB::ANSWER), $cond);
  }

  /**
   * Add the given answer to this question
   *
   * @param Answer $answer the answer to add
   */
  public function addAnswer(Answer $answer) {
    $answer->question = $this;
    DB::set($answer);
  }
}
?>