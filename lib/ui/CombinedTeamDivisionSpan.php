<?php
namespace ui;

use \Division;
use \Team;
use \XSpan;

/**
 * Displays division and team name in one span.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class CombinedTeamDivisionSpan extends XSpan {

  const CLASSNAME = 'combined-team-division';
  const TEAM_CLASSNAME = 'combined-team';
  const DIVISION_CLASSNAME = 'combined-division';

  public function __construct(Division $division, Team $team) {
    parent::__construct(
      new XSpan($division, array('class' => self::DIVISION_CLASSNAME)),
      array('class' => self::CLASSNAME)
    );
    $this->add(
      new XSpan($team, array('class' => self::TEAM_CLASSNAME))
    );
  }
}