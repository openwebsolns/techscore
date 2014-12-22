<?php
/*
 * This file is part of Techscore
 */



/**
 * Same as team, but ordered by rank, by default
 *
 * @author Dayan Paez
 * @version 2012-11-14
 */
class RankedSinglehandedTeam extends SinglehandedTeam {
  protected function db_order() { return array('dt_rank'=>true, 'school'=>true, 'id'=>true); }
}
