<?php
namespace data;

use \FullRegatta;
use \RP;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;

require_once('xml5/HtmlLib.php');
require_once('xml5/TS.php');

/**
 * Creates tables for team racing ranking display.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class TeamRankingTableCreator {

  /**
   * @var FullRegatta the regatta we're working with.
   */
  private $regatta;
  /**
   * @var XTable the cached full table.
   */
  private $rankTable;
  /**
   * @var XTable the cached legend able.
   */
  private $legendTable;
  /**
   * @var boolean true will generate links, suitable for publishing.
   */
  private $publicMode = false;

  /**
   * Create a new team ranking tables generator.
   *
   * @param FullRegatta $regatta the regatta.
   * @param boolean $publicMode true to generate links to schools.
   */
  public function __construct(FullRegatta $regatta, $publicMode = false) {
    $this->regatta = $regatta;
    $this->publicMode = ($publicMode !== false);
  }

  /**
   * Gets the rank table; generated but once.
   *
   * @return XTable
   */
  public function getRankTable() {
    $this->generateTables();
    return $this->rankTable;
  }

  /**
   * Gets the legend table; if any.
   *
   * @return XTable, or null, if no legend necessary.
   */
  public function getLegendTable() {
    $this->generateTables();
    return $this->legendTable;
  }

  /**
   * Internal function to generate the tables.
   *
   */
  private function generateTables() {
    if ($this->rankTable !== null) {
      return;
    }
    $this->legendTable = null;
    $this->rankTable = new XTable(
      array('class'=>'teamranking results'),
      array(
        new XTHead(
          array(),
          array(
            new XTR(
              array(),
              array(
                new XTH(array('class'=>'tiebreaker')),
                new XTH(array(), "#"),
                new XTH(array('title'=>'School mascot')),
                new XTH(array(), "School"),
                new XTH(array('class'=>'teamname'), "Team"),
                new XTH(array('title'=>"Win/loss record across all rounds"), "Rec."),
                new XTH(array('class'=>'sailor'), "Skippers"),
                new XTH(array('class'=>'sailor'), "Crews"))))),
        $b = new XTBody()));

    $ranks = $this->regatta->getRankedTeams();
    $explanations = Utils::createTiebreakerMap($ranks);
    $divs = $this->regatta->getDivisions();
    $season = $this->regatta->getSeason();
    $rpm = $this->regatta->getRpManager();
    $prev_group = null;
    foreach ($ranks as $rowIndex => $team) {
      if ($prev_group !== null && $team->rank_group != $prev_group) {
        $b->add(
          new XTR(
            array(),
            array(
              new XTD(array('class'=>'tr-rank-group', 'colspan'=>8, 'title'=>"Next group")))));
      }

      $prev_group = $team->rank_group;

      $skips = array();
      $crews = array();
      foreach ($divs as $div) {
        foreach ($rpm->getRP($team, $div, RP::SKIPPER) as $s) {
          if ($s->sailor !== null)
            $skips[$s->sailor->id] = $s->getSailor(true, $this->publicMode);
        }
        foreach ($rpm->getRP($team, $div, RP::CREW) as $s) {
          if ($s->sailor !== null)
            $crews[$s->sailor->id] = $s->getSailor(true, $this->publicMode);
        }
      }

      $mascot = $team->school->drawSmallBurgee("");
      $school = (string)$team->school;
      if ($this->publicMode !== false)
        $school = new XA(sprintf('%s%s/', $team->school->getURL(), $season), $school);

      $rowspan = max(1, count($skips), count($crews));
      $rowindex = 'row' . ($rowIndex % 2);
      $row = new XTR(
        array('class'=>sprintf('topborder %s team-%s', $rowindex, $team->id)),
        array(
          new XTD(array('rowspan'=>$rowspan, 'title'=>$team->dt_explanation, 'class'=>'tiebreaker'), $explanations[$team->dt_explanation]),
          new XTD(array('rowspan'=>$rowspan), $team->dt_rank),
          new XTD(array('rowspan'=>$rowspan, 'class'=>'burgee-cell'), $mascot),
          new XTD(array('rowspan'=>$rowspan), $school),
          new XTD(array('class'=>'teamname', 'rowspan'=>$rowspan), $team->getQualifiedName()),
          new XTD(array('rowspan'=>$rowspan), $team->getRecord())));

      $b->add($row);

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

    if (count($explanations) > 1) {
      $this->legendTable = new LegendTable($explanations);
    }
  }
}