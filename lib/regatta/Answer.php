<?php
/*
 * This class is part of TechScore
 *
 * @package regatta
 */

require_once('regatta/DB.php');

/**
 * Answer to a Question asked by an end-user
 *
 * @author Dayan Paez
 * @created 2014-12-15
 */
class Answer extends DBObject {
  protected $question;
  protected $answered_by;
  protected $answered_on;
  public $answer;
  public $publishable;

  public function db_type($field) {
    switch ($field) {
    case 'question':
      require_once('regatta/Question.php');
      return DB::T(DB::QUESTION);
    case 'answered_by':
      require_once('regatta/Account.php');
      return DB::T(DB::ACCOUNT);
    case 'answered_on':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('answered_on' => false);
  }
}
?>