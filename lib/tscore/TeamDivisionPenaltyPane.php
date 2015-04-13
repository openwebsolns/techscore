<?php
namespace tscore;

use \AbstractPane;

use \XWarning;

use \SoterException;

use \Account;
use \Regatta;

require_once('AbstractPane.php');

/**
 * Drop "division-level" penalties (team penalty) for team racing.
 *
 * @author Dayan Paez
 * @version 2015-04-12
 */
class TeamDivisionPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $regatta) {
    parent::__construct("Team penalty", $user, $regatta);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent(new XWarning("Coming soon."));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to see here, yet.");
  }
}