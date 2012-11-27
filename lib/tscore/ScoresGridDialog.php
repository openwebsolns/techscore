<?php
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
   * Create a new rotation dialog for the given regatta
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Race results", $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    // We need to provide either all the rounds, or just some of the
    // rounds. For completeness, we provide all rounds.
    $rounds = $this->REGATTA->getRounds();
    foreach ($rounds as $round) {
      $this->PAGE->addContent($p = new XPort("Round $round"));
      $this->PAGE->addContent($this->getRoundTable($round));
    }
  }

  /**
   * Not to be confused with King Argthur's Court, this method will
   * return a grid indicating the winners/losers of the head to head
   * races involved in the given round, which must exist.
   *
   * @param int $round the round number to fetch (number must be,
   * incidentally, round)
   *
   * @return XTable a grid
   * @throws InvalidArgumentException if this regatta has no such round
   */
  public function getRoundTable($round) {
    $races = $this->REGATTA->getRacesInRound($round);
    if (count($races) == 0)
      throw new InvalidArgumentException("No such round $round in this regatta.");

    $divs = $this->REGATTA->getDivisions();

    // Map of teams in this round (team_id => Team)
    $teams = array();
    $scores = array(); // Map of team_id => (team_id => score)
    foreach ($races as $race) {
      $ts = $this->REGATTA->getRaceTeams($race);
      foreach ($ts as $t) {
        if (!isset($teams[$t->id])) {
          $teams[$t->id] = $t;
          $scores[$t->id] = array();
        }
      }

      $t0 = $ts[0]->id;
      $t1 = $ts[1]->id;
      $s0 = $this->REGATTA->getFinish($race, $ts[0]);
      $s1 = $this->REGATTA->getFinish($race, $ts[1]);

      if ($s0 !== null && $s1 !== null) {
        if (!isset($scores[$t0][$t1])) {
          $scores[$t0][$t1] = 0;
          $scores[$t1][$t0] = 0;
        }
        $scores[$t0][$t1] += $s0->score;
        $scores[$t1][$t0] += $s1->score;
      }
    }

    // Create table
    $table = new XTable(array('class'=>'teamscores'));
    $table->add($header = new XTR(array('class'=>'tr-cols')));
    $header->add(new XTD(array('class'=>'tr-pivot'), "↓ vs →"));
    $header->add(new XTH(array('class'=>'tr-rec-th'), "Record"));
    // Header
    foreach ($teams as $id => $team) {
      $header->add(new XTH(array(), $team));
      $row = new XTR(array('class'=>'tr-row'),
                     array(new XTH(array(), $team),
                           $rec = new XTD(array('class'=>'tr-record'), "")));
      $win = 0;
      $los = 0;
      foreach ($teams as $id2 => $other) {
        if ($id2 == $id)
          $row->add(new XTD(array('class'=>'tr-same'), "X"));
        else {
          if (!isset($scores[$id][$id2]))
            $row->add(new XTD(array('class'=>'tr-na'), ""));
          else {
            if ($scores[$id][$id2] < $scores[$id2][$id]) {
              $row->add(new XTD(array('class'=>'tr-win'), "W"));
              $win++;
            }
            else {
              $row->add(new XTD(array('class'=>'tr-lose'), "L"));
              $los++;
            }
          }
        }
      }
      $table->add($row);
      $rec->add("$win-$los");
    }
    return $table;
  }
}
?>