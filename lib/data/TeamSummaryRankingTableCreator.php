<?php
namespace data;

use \FullRegatta;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;

require_once('xml5/HtmlLib.php');

/**
 * Creates the summary ranking table (and legend).
 *
 * @author Dayan Paez
 * @version 2015-03-22
 * @see TeamRankingTableCreator
 */
class TeamSummaryRankingTableCreator {

  /**
   * @var FullRegatta the regatta we're working with.
   */
  private $regatta;
  /**
   * @var XTable the cached full table.
   */
  private $rankTable;
  /**
   * @var XTable the cached legend able. False means not yet created,
   * while null means no legend table necessary.
   */
  private $legendTable = false;
  /**
   * @var boolean true will generate links, suitable for publishing.
   */
  private $publicMode = false;

  /**
   * Create a new team summary ranking tables generator.
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
    if ($this->rankTable === null) {
      $this->generateTables();
    }
    return $this->rankTable;
  }

  /**
   * Gets the legend table; if any.
   *
   * @return XTable, or null, if no legend necessary.
   */
  public function getLegendTable() {
    if ($this->legendTable === false) {
      $this->generateTables();
    }
    return $this->legendTable;
  }

  /**
   * Internal function to generate the tables.
   *
   */
  private function generateTables() {
    $this->legendTable = null;
    $this->rankTable = new XTable(
      array('class'=>'teamranking results', 'id'=>'teamranking-summary'),
      array(
        new XTHead(
          array(),
          array(
            new XTR(
              array(),
              array(new XTH(),
                    new XTH(array(), "#"),
                    new XTH(array('title'=>'School mascot')),
                    new XTH(array(), "School"),
                    new XTH(array('class'=>'teamname'), "Team"),
                    new XTH(array('title'=>"Win/loss record across all rounds"), "Rec."),
                    new XTH(array('title'=>"Winning percentage"), "%"))))),
        $b = new XTBody()));

    $ranks = $this->regatta->getRankedTeams();
    $explanations = Utils::createTiebreakerMap($ranks);
    $season = $this->regatta->getSeason();
    $prev_group = null;
    foreach ($ranks as $rowIndex => $team) {
      if ($prev_group !== null && $team->rank_group != $prev_group)
        $b->add(new XTR(array(), array(new XTD(array('class'=>'tr-rank-group', 'colspan'=>7, 'title'=>"Next group")))));
      $prev_group = $team->rank_group;

      $mascot = $team->school->drawSmallBurgee("");
      $school = (string)$team->school;
      if ($this->publicMode !== false) {
        $school = new XA(sprintf('%s%s/', $team->school->getURL(), $season), $school);
      }

      $b->add(new XTR(
        array('class'=>sprintf('topborder row%d team-%s', ($rowIndex % 2), $team->id)),
        array(
          new XTD(array('class'=>'tiebreaker', 'title'=>$team->dt_explanation), $explanations[$team->dt_explanation]),
          new XTD(array(), $team->dt_rank),
          new XTD(array('class'=>'burgee-cell'), $mascot),
          new XTD(array(), $school),
          new XTD(array('class'=>'teamname'), $team->getQualifiedName()),
          new XTD(array(), $team->getRecord()),
          new XTD(array(), sprintf('%0.1f', (100 * $team->getWinPercentage())))))
      );
    }

    if (count($explanations) > 1) {
      $this->legendTable = new LegendTable($explanations);
    }
  }
}