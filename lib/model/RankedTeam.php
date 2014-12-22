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
class RankedTeam extends Team {
  protected function db_order() { return array('dt_rank'=>true, 'school'=>true, 'id'=>true); }
}
