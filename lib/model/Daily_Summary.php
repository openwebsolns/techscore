<?php
/*
 * This file is part of Techscore
 */



/**
 * Event summary for a given day of sailing (one to many with regatta)
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class Daily_Summary extends DBObject {
  public $regatta;
  public $summary;
  public $mail_sent;
  public $rp_mail_sent;
  public $tweet_sent;
  protected $summary_date;

  public function db_name() { return 'daily_summary'; }
  protected function db_order() { return array('regatta'=>true, 'summary_date'=>true); }
  public function db_type($field) {
    switch ($field) {
    case 'summary_date': return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }
  public function __toString() { return (string)$this->summary; }
}
