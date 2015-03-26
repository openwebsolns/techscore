<?php
use \data\TeamScoresGrid;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * A dialog suitable for presenting win-lose grid among teams in
 * "rounds", meant for team racing regattas.
 *
 * In team racing regattas, each team sails each other "head-to-head".
 * As such, there is a binary winner/loser for each bout. The grid
 * would show who won across the row and who lost across the column:
 *
 * | Teams | MIT | HAR | BC | BU |
 * +-------+-----+-----+----+----+
 * | MIT   |     | W   | L  | L  |
 * | HAR   | L   |     | W  | L  |
 * | BC    | W   | L   |    | W  |
 * | BU    | W   | W   | L  |    |
 *
 * Naturally, the grid is "symmetric", not in a matrix-strict sense,
 * but certainly insomuch as only the top or lower half is necessary.
 *
 *
 * @author Dayan Paez
 * @version 2012-05-17
 */
class ScoresGridDialog extends AbstractScoresDialog {
  /**
   * Create a new grid dialog for the given regatta
   *
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Race results", $user, $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    // We need to provide either all the rounds, or just some of the
    // rounds. For completeness, we provide all rounds.
    $rounds = $this->REGATTA->getRounds();
    $cnt = 0;
    foreach ($rounds as $round) {
      if (count($round->getSeeds()) > 0) {
        $this->PAGE->addContent($p = new XPort("Round $round"));
        $p->add(new TeamScoresGrid($this->REGATTA, $round));
        $cnt++;
      }
    }

    if ($cnt == 0)
      $this->PAGE->addContent(new XWarning("There are no rounds to show."));
  }
}
?>