<?php
/*
 * This file is part of Techscore
 */



/**
 * Saved All-America report parameters
 *
 * @author Dayan Paez
 * @version 2013-06-13
 */
class AA_Report extends DBObject {
  public $type;
  public $role;
  protected $seasons;
  protected $conferences;
  public $min_regattas;
  protected $regattas;
  protected $sailors;
  protected $last_updated;
  public $author;

  public function db_type($field) {
    switch ($field) {
    case 'regattas':
    case 'sailors':
    case 'conferences':
    case 'seasons':
      return array();
    case 'last_updated':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }
}
