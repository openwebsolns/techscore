<?php
namespace data;

use \FullRegatta;
use \Division;
use \RP;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XSpan;
use \XA;
use \XAbbr;

require_once('xml5/HtmlLib.php');

/**
 * Creates table summarizing scores for fleet regatta.
 *
 * Summarizes the scores for each team by displaying each division's
 * total.
 *
 * @author Dayan Paez
 * @version 2015-03-24
 */
class FleetScoresTableCreator {

  /**
   * @var FullRegatta the regatta we're working with.
   */
  private $regatta;
  /**
   * @var XTable the cached full table.
   */
  private $scoreTable;
  /**
   * @var XTable the cached legend table.
   */
  private $legendTable;
  /**
   * @var boolean true will generate links, suitable for publishing.
   */
  private $publicMode = false;

  /**
   * Creates a new scores table.
   *
   * @param FullRegatta $regatta the regatta in question.
   * @param boolean $publicMode true to create links to schools, etc.
   */
  public function __construct(FullRegatta $regatta, $publicMode = false) {
    $this->regatta = $regatta;
    $this->publicMode = ($publicMode !== false);
  }

  /**
   * Get the main table.
   *
   * @return XTable
   */
  public function getScoreTable() {
    $this->generateTables();
    return $this->scoreTable;
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
    if ($this->scoreTable !== null) {
      return;
    }

    $isSinglehanded = $this->regatta->isSingleHanded();
    $this->scoreTable = new XTable(
      array('class'=>'results coordinate divisional'),
      array(
        new XTHead(
          array(),
          array(
            $r = new XTR(
              array(),
              array(
                new XTH(),
                new XTH(),
                new XTH(),
                new XTH(array(), "School"),
                new XTH(array(), "Team"))))),
        $tab = new XTBody()));

    $penalty_th = array();
    $divisions = $this->regatta->getDivisions();
    $division_has_penalty = array();
    foreach ($divisions as $div) {
      $r->add(new XTH(array(), $div));
      $r->add($th = new XTH(array('title'=>"Team penalty in division $div"), ""));

      $penalty_th[(string)$div] = $th;
      $division_has_penalty[(string)$div] = false;
    }
    $r->add(new XTH(array('title'=>"Total for team"), new XAbbr("TOT", "Total")));

    $ranks = $this->regatta->getRankedTeams();
    $tiebreakers = Utils::createTiebreakerMap($ranks);

    $row = 0;
    foreach ($ranks as $tID => $rank) {

      $ln = new XSpan($rank->school->name, array('itemprop'=>'name'));
      if ($this->publicMode !== false) {
        $ln = new XA(sprintf('%s%s/', $rank->school->getURL(), $this->regatta->getSeason()), $ln, array('itemprop'=>'url'));
      }

      $tab->add(
        $r = new XTR(
          array(
            'class'=>'row' . ($row++ % 2),
            'itemscope'=>'itemscope',
            'itemtype'=>'http://schema.org/CollegeOrUniversity',
            'itemprop'=>'attendee'),
          array(
            new XTD(
              array('title'=>$rank->dt_explanation, 'class'=>'tiebreaker'),
              $tiebreakers[$rank->dt_explanation]),
            new XTD(array(), $tID + 1),
            $bc = new XTD(array('class'=>'burgee-cell')),
            new XTD(array('class'=>'schoolname'), $ln),
            new XTD(array('class'=>'teamname'), $rank->toView($this->publicMode))
          )
        )
      );

      $bc->add($rank->school->drawSmallBurgee(null, array('itemprop'=>'image')));

      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
        $r->add($s_cell = new XTD());
        $r->add($p_cell = new XTD());

        if (($div_rank = $rank->getRank($div)) === null) {
          continue;
        }

        if ($div_rank->penalty !== null) {
          $p_cell->add($div_rank->penalty);
          $p_cell->set('title', "+20 points: " . $div_rank->comments);

          $division_has_penalty[(string)$div] = true;
        }
        $s_cell->add($div_rank->score);
        $s_cell->set('class', 'total');
        $scoreTeam += $div_rank->score;
      }
      $r->add(new XTD(array('class'=>'totalcell'), $scoreTeam));
    }

    // Deal with penalty headers
    foreach ($division_has_penalty as $id => $val) {
      if ($val) {
        $penalty_th[$id]->add("P");
      }
    }

    $this->legendTable = null;
    if (count($tiebreakers) > 1) {
      $this->legendTable = new LegendTable($tiebreakers);
    }
  }
}