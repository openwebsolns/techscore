<?php
/*
 * This file is part of Techscore
 */



/**
 * The individual Regattas that were changed in the merge
 *
 * @author Dayan Paez
 * @version 2014-09-14
 */
class Merge_Regatta_Log extends DBObject {
  protected $merge_sailor_log;
  protected $regatta;

  public function db_type($field) {
    switch ($field) {
    case 'merge_sailor_log': return DB::T(DB::MERGE_SAILOR_LOG);
    case 'regatta': return DB::T(DB::FULL_REGATTA);
    default: return parent::db_type($field);
    }
  }
}
