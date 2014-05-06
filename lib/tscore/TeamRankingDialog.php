<?php
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
   */
  public function getSummaryTable($link_schools = false) {
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
      if ($link_schools !== false)
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

  /**
   * Fetches the rankings table
   *
   * @param String $link_schools true to include link to schools' season
   * @return Array the table element(s)
   */
  public function getTable($link_schools = false) {
    $ELEMS = array(new XTable(array('class'=>'teamranking results'),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(array('class'=>'tiebreaker')),
                                                                   new XTH(array(), "#"),
                                                                   new XTH(array('title'=>'School mascot')),
                                                                   new XTH(array(), "School"),
                                                                   new XTH(array('class'=>'teamname'), "Team"),
                                                                   new XTH(array('title'=>"Winning record across all rounds"), "Rec."),
                                                                   new XTH(array('class'=>'sailor'), "Skippers"),
                                                                   new XTH(array('class'=>'sailor'), "Crews"))))),
                                    $b = new XTBody())));
    $divs = $this->REGATTA->getDivisions();

    $explanations = array("" => "");
    $season = $this->REGATTA->getSeason();
    $rpm = $this->REGATTA->getRpManager();
    $prev_group = null;
    foreach ($this->REGATTA->getRankedTeams() as $rowIndex => $team) {
      if ($prev_group !== null && $team->rank_group != $prev_group)
        $b->add(new XTR(array(), array(new XTD(array('class'=>'tr-rank-group', 'colspan'=>8, 'title'=>"Next group")))));
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

      $skips = array();
      $crews = array();
      foreach ($divs as $div) {
        foreach ($rpm->getRP($team, $div, RP::SKIPPER) as $s) {
          if ($s->sailor !== null)
            $skips[$s->sailor->id] = $s->getSailor(true);
        }
        foreach ($rpm->getRP($team, $div, RP::CREW) as $s) {
          if ($s->sailor !== null)
            $crews[$s->sailor->id] = $s->getSailor(true);
        }
      }

      $mascot = $team->school->drawSmallBurgee("");
      $school = (string)$team->school;
      if ($link_schools !== false)
        $school = new XA(sprintf('%s%s/', $team->school->getURL(), $season), $school);

      $rowspan = max(1, count($skips), count($crews));
      $rowindex = 'row' . ($rowIndex % 2);
      $b->add($row = new XTR(array('class'=>sprintf('topborder %s team-%s', $rowindex, $team->id)),
                             array(new XTD(array('rowspan'=>$rowspan, 'title'=>$team->dt_explanation, 'class'=>'tiebreaker'), $explanations[$team->dt_explanation]),
                                   new XTD(array('rowspan'=>$rowspan), $team->dt_rank),
                                   new XTD(array('rowspan'=>$rowspan, 'class'=>'burgee-cell'), $mascot),
                                   new XTD(array('rowspan'=>$rowspan), $school),
                                   new XTD(array('class'=>'teamname', 'rowspan'=>$rowspan), $team->getQualifiedName()),
                                   new XTD(array('rowspan'=>$rowspan), $team->getRecord()))));
      // Special case: no RP information
      if (count($skips) + count($crews) == 0) {
        $row->add(new XTD());
        $row->add(new XTD());
        continue;
      }

      // Add RP information
      $rprows = array($row);
      for ($i = 0; $i < $rowspan - 1; $i++) {
        $b->add($row = new XTR(array('class'=>$rowindex)));
        $rprows[] = $row;
      }
      $row_number = 0;
      foreach ($skips as $sailor) {
        $rprows[$row_number]->add(new XTD(array('class'=>'sailor'), $sailor));
        if (count($crews) <= $row_number)
          $rprows[$row_number]->add(new XTD());
        $row_number++;
      }
      $row_number = 0;
      foreach ($crews as $sailor) {
        if (count($skips) <= $row_number)
          $rprows[$row_number]->add(new XTD());
        $rprows[$row_number]->add(new XTD(array('class'=>'sailor'), $sailor));
        $row_number++;
      }
    }

    if (count($explanations) > 1)
      $ELEMS[] = $this->getLegend($explanations);
    return $ELEMS;
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Rankings"));
    foreach ($this->getTable() as $elem)
      $p->add($elem);
  }
}
?>