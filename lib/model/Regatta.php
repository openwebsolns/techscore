<?php
/*
 * This file is part of Techscore
 */



/**
 * A non-inactive regatta
 *
 * @author Dayan Paez
 * @version 2012-11-26
 */
class Regatta extends FullRegatta {
  public function db_where() { return new DBCond('inactive', null); }
}
