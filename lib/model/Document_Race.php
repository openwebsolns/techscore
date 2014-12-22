<?php
/*
 * This file is part of Techscore
 */



/**
 * Linking table between documents and races
 *
 * @author Dayan Paez
 * @version 2014-04-26
 */
class Document_Race extends DBObject {
  protected $race;
  protected $document;

  public function db_name() { return 'regatta_document_race'; }
  public function db_type($field) {
    if ($field == 'race')
      return DB::T(DB::RACE);
    if ($field == 'document')
      return DB::T(DB::REGATTA_DOCUMENT_SUMMARY);
    return parent::db_type($field);
  }
}
