<?php
namespace data;

use \FullRegatta;
use \Division;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;
use \XBr;

require_once('xml5/HtmlLib.php');

/**
 * Creates full scores table for fleet regatta.
 *
 * This is the BIG ONE.
 *
 * @author Dayan Paez
 * @version 2015-03-24
 */
class FullScoresTableCreator {

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

    // Get finished race array: div => Array<Race>, and determine
    // largest scored race number
    $divisions = $this->regatta->getDivisions();
    $num_divs = count($divisions);
    $largest_num = 0;
    $races = array();
    foreach ($divisions as $division) {
      $div = (string)$division;
      $races[$div] = array();
      foreach ($this->regatta->getScoredRaces($division) as $race) {
        $races[$div][$race->number] = $race;
        $largest_num = max($largest_num, $race->number);
      }
    }


    $this->scoreTable = new XTable(
      array('class'=>'results coordinate'),
      array(
        new XTHead(
          array(),
          array(
            $r = new XTR(
              array(),
              array(
                new XTH(), // legend
                new XTH(), // rank
                new XTH(array(), "Team"))))),
        $tab = new XTBody()));

    if ($num_divs > 1) {
      $r->add(new XTH(array(), "Div."));
    }
    for ($i = 1; $i <= $largest_num; $i++) {
      $r->add(new XTH(array('class'=>'right'), $i));
    }
    $r->add($penalty_th = new XTH());
    $r->add(new XTH(array('class'=>'right'), "TOT"));


    $ranks = $this->regatta->getRankedTeams();
    $tiebreakers = Utils::createTiebreakerMap($ranks);

    $has_penalties = false;
    $order = 1;
    foreach ($ranks as $team) {
      $scoreTeam   = 0;
      $scoreRace   = ($largest_num == 0) ? array() : array_fill(0, $largest_num, 0);
      $penaltyTeam = 0;

      // For each division... and race...
      foreach ($races as $div => $raceList) {
        $scoreDiv = 0;

        $tab->add($r = new XTR(array('class'=>"div$div")));

        if ($num_divs == 1) {
          $ln = array($team->toView($this->publicMode), new XBr(), $team->school->nick_name);
          if ($this->publicMode !== false) {
            $ln[2] = new XA(sprintf('%s%s/', $team->school->getURL(), $this->regatta->getSeason()), $ln[2]);
          }

          $r->add(new XTD(array("title" => $team->dt_explanation, "class" => "tiebreaker"), $tiebreakers[$team->dt_explanation]));
          $r->add(new XTD(array(), $order++));
          $r->add(new XTD(array("class"=>"strong"), $ln));
        }
        elseif ($div == "A") {
          $ln = $team->school->nick_name;
          if ($this->publicMode !== false) {
            $ln = new XA(sprintf('%s%s/', $team->school->getURL(), $this->regatta->getSeason()), $ln);
          }

          $r->add(new XTD(array("title" => $team->dt_explanation), $tiebreakers[$team->dt_explanation]));
          $r->add(new XTD(array(), $order++));
          $r->add(new XTD(array(), $ln));
        }
        elseif ($div == "B") {
          $r->add(new XTD());
          $r->add(new XTD());
          $r->add(new XTD(array(), $team->getQualifiedName()));
        }
        else {
          $r->add(new XTD());
          $r->add(new XTD());
          $r->add(new XTD());
        }
        if ($num_divs > 1)
          $r->add(new XTD(array('class'=>'strong'), $div));

        // ...for each race
        for ($i = 1; $i <= $largest_num; $i++) {

          // finish and score
          $r->add($cell = new XTD());
          if (isset($raceList[$i])) {
            $race = $raceList[$i];

            // add score for this race to running team score
            $finish = $this->regatta->getFinish($race, $team);
            $scoreDiv        += $finish->score;
            $scoreTeam       += $finish->score;
            $scoreRace[$i-1] += $finish->score;

            $cell->add($finish->getPlace());
            $cell->set('title', $finish->explanation);
            $cell->set('class', 'right');
          }
        }

        // print penalty, should it exist
        $team_pen = $this->regatta->getDivisionPenalty($team, new Division($div));
        if ($team_pen !== null) {
          $r->add(new XTD(array('title'=>$team_pen->comments, 'class'=>'right'), $team_pen->type));
          $scoreDiv += 20;
          $penaltyTeam += 20;
          $has_penalties = true;
        }
        else {
          $r->add(new XTD());
        }

        // print total score for division
        $r->add(new XTD(array('class'=>'right'), ($scoreDiv == 0) ? "" : $scoreDiv));
      }

      // write total row
      $tab->add($r = new XTR(array("class"=>"totalrow"), array(new XTD(), new XTD(), $burgee_cell = new XTD(array('class'=>'burgee-cell')))));
      $burgee_cell->add($team->school->drawSmallBurgee());
      if ($num_divs > 1)
        $r->add(new XTD());

      for ($i = 0; $i < $largest_num; $i++) {
        $value = array_sum(array_slice($scoreRace, 0, $i + 1));
        $r->add(new XTD(array('class'=>'right sum'), $value));
      }

      // print penalty sum, if they exist
      if ($penaltyTeam == 0) {
        $r->add(new XTD());
      }
      else {
        $r->add(new XTD(array('title' => "Penalty total"), "($penaltyTeam)"));
      }

      // print total
      $r->add(new XTD(array('class'=>'sum total right'), $scoreTeam + $penaltyTeam));
    }

    $this->legendTable = null;
    if (count($tiebreakers) > 1) {
      $this->legendTable = new LegendTable($tiebreakers);
    }
  }
}