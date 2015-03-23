<?php
use \data\TeamRankingTableCreator;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the overall rankings for a team regatta
 *
 * @author Dayan Paez
 * @version 2013-02-19
 */
class TeamRankingDialog extends AbstractScoresDialog {
  /**
   * Creates a new team ranking dialog
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Rankings", $reg);
  }

  /**
   * Retrieves the short version of the table (without RP)
   *
   * @param boolean $public_mode true to include links to profile pages
   */
  public function getSummaryTable($public_mode = false) {
    $ELEMS = array(new XTable(array('class'=>'teamranking results', 'id'=>'teamranking-summary'),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(),
                                                                   new XTH(array(), "#"),
                                                                   new XTH(array('title'=>'School mascot')),
                                                                   new XTH(array(), "School"),
                                                                   new XTH(array('class'=>'teamname'), "Team"),
                                                                   new XTH(array('title'=>"Winning record across all rounds"), "Rec."),
                                                                   new XTH(array('title'=>"Winning percentage"), "%"))))),
                                    $b = new XTBody())));

    $explanations = array("" => "");
    $season = $this->REGATTA->getSeason();
    $prev_group = null;
    foreach ($this->REGATTA->getRankedTeams() as $rowIndex => $team) {
      if ($prev_group !== null && $team->rank_group != $prev_group)
        $b->add(new XTR(array(), array(new XTD(array('class'=>'tr-rank-group', 'colspan'=>7, 'title'=>"Next group")))));
      $prev_group = $team->rank_group;

      // Explanation
      if (!empty($team->dt_explanation) && !isset($explanations[$team->dt_explanation])) {
        $count = count($explanations);
        switch ($count) {
        case 1:
          $explanations[$team->dt_explanation] = "*";
          break;
        case 2:
          $explanations[$team->dt_explanation] = "**";
          break;
        default:
          $explanations[$team->dt_explanation] = chr(95 + $count);
        }
      }

      $mascot = $team->school->drawSmallBurgee("");
      $school = (string)$team->school;
      if ($public_mode !== false)
        $school = new XA(sprintf('%s%s/', $team->school->getURL(), $season), $school);

      $b->add($row = new XTR(array('class'=>sprintf('topborder row%d team-%s', ($rowIndex % 2), $team->id)),
                             array(new XTD(array('class'=>'tiebreaker', 'title'=>$team->dt_explanation), $explanations[$team->dt_explanation]),
                                   new XTD(array(), $team->dt_rank),
                                   new XTD(array('class'=>'burgee-cell'), $mascot),
                                   new XTD(array(), $school),
                                   new XTD(array('class'=>'teamname'), $team->getQualifiedName()),
                                   new XTD(array(), $team->getRecord()),
                                   new XTD(array(), sprintf('%0.1f', (100 * $team->getWinPercentage()))))));
    }

    // Print legend, if necessary
    if (count($explanations) > 1)
      $ELEMS[] = $this->getLegend($explanations);
    return $ELEMS;
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Rankings"));
    $maker = new TeamRankingTableCreator($this->REGATTA);
    $p->add($maker->getRankTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $p->add($legend);
    }
  }
}
?>