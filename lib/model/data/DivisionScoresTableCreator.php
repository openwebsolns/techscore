<?php
namespace data;

use \InvalidArgumentException;

use \DB;
use \Division;
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

/**
 * Creates table with scores for a particular division of a fleet regatta.
 *
 * @author Dayan Paez
 * @version 2015-03-24
 */
class DivisionScoresTableCreator {

  /**
   * @var FullRegatta the regatta we're working with.
   */
  private $regatta;
  /**
   * @var Division the division in question.
   */
  private $division;
  /**
   * @var XTable the cached full table.
   */
  private $scoreTable;
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
   * Creates a new scores table.
   *
   * @param FullRegatta $regatta the regatta in question.
   * @param Division $division one of the divisions in the regatta.
   * @param boolean $publicMode true to create links to schools, etc.
   */
  public function __construct(FullRegatta $regatta, Division $division, $publicMode = false) {
    $this->regatta = $regatta;
    $this->division = $division;
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
    $division = $this->division;
    $rpManager = $this->regatta->getRpManager();
    $ranks = $this->regatta->getRanks($division);
    if (count($ranks) == 0) {
      throw new InvalidArgumentException("There are no teams (ranks) in regatta.");
    }

    $this->scoreTable = new XTable(
      array('class'=>'results coordinate division ' . $division),
      array(
        new XTHead(
          array(),
          array(
            new XTR(
              array(),
              array(
                new XTH(), // asterisks
                new XTH(), // rank
                new XTH(),
                new XTH(array('class'=>'teamname'), "Team"),
                $penalty_th = new XTH(),
                new XTH(array(), "Total"),
                new XTH(array(), "Sailors"),
                new XTH(array(), ""),
                new XTH(array(), ""))))),
            $tab = new XTBody()));

    $has_penalties = false;

    $tiebreakers = Utils::createTiebreakerMap($ranks);
    $outside_sailors = array();

    $rowIndex = 0;
    $order = 1;
    $total_races = count($this->regatta->getRaces($division));
    foreach ($ranks as $rank) {

      $ln = $rank->team->school->name;
      if ($this->publicMode !== false) {
        $ln = new XA(sprintf('%s%s/', $rank->team->school->getURL(), $this->regatta->getSeason()), $ln);
      }

      // deal with explanations
      $sym = $tiebreakers[$rank->explanation];

      // fill the two header rows up until the sailor names column
      $img = $rank->team->school->drawSmallBurgee('');
      $r1 = new XTR(
        array('class'=>'topborder left row' . $rowIndex % 2),
        array(
          $r1c1 = new XTD(array('title'=>$rank->explanation, 'class'=>'tiebreaker'), $sym),
          $r1c2 = new XTD(array(), $order++),
          $r1c3 = new XTD(array('class'=>'burgee-cell'), $img),
          $r1c4 = new XTD(array('class'=>'schoolname'), $ln),
          $r1c5 = new XTD(),
          $r1c6 = new XTD(array('class'=>'totalcell'), $rank->score)));

      if ($rank->penalty != null) {
        $r1c5->add($rank->penalty);
        $r1c5->set('title', sprintf("%s (+20 points)", $rank->comments));
        $has_penalties = true;
      }

      // We use the default team name instead of the qualified name
      // because we are specifying the sailor names explicitly
      $r2 = new XTR(
        array('class'=>'left row'.($rowIndex % 2)),
        array($r2c4 = new XTD(array('class'=>'teamname'), $rank->team->name)));

      $headerRows = array($r1, $r2);
      $num_sailors = 2;
      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array(RP::SKIPPER, RP::CREW) as $index => $role) {
        $sailors  = $rpManager->getRP($rank->team, $division, $role);

        $is_first = true;
        $s_rows = array();
        if (count($sailors) == 0) {
          $headerRows[$index]->add(new XTD()); // name
          $headerRows[$index]->add(new XTD()); // races
          $headerRows[$index]->add(new XTD()); // outside sailors
        }
        foreach ($sailors as $s) {
          if ($is_first) {
            $row = $headerRows[$index];
            $is_first = false;
          }
          else {
            $row = new XTR(array('class'=>'row'.($rowIndex % 2)));
            $s_rows[] = $row;
            $num_sailors++;
          }

          if (count($s->races_nums) == $total_races)
            $amt = "";
          else
            $amt = DB::makeRange($s->races_nums);

          $sup = "";
          if ($s->sailor !== null && $s->sailor->school != $rank->team->school) {
            if (!isset($outside_sailors[$s->sailor->school->nick_name]))
              $outside_sailors[$s->sailor->school->nick_name] = count($outside_sailors) + 1;
            $sup = $outside_sailors[$s->sailor->school->nick_name];
          }
          $row->add(new XTD(array('class'=>'sailor-name ' . $role), $s->getSailor(true, $this->publicMode)));
          $row->add(new XTD(array('class'=>'races'), $amt));
          $row->add(new XTD(array('class'=>'superscript'), $sup));
        }

        // Add rows
        $tab->add($headerRows[$index]);
        if ($role == RP::SKIPPER)
          $r1c4->set('rowspan', (count($s_rows) + 1));
        else
          $r2c4->set('rowspan', (count($s_rows) + 1));
        foreach ($s_rows as $r)
          $tab->add($r);
      }
      $r1c1->set('rowspan', $num_sailors);
      $r1c2->set('rowspan', $num_sailors);
      $r1c3->set('rowspan', $num_sailors);
      $r1c5->set('rowspan', $num_sailors);
      $r1c6->set('rowspan', $num_sailors);
      $rowIndex++;
    } // end of table
    if ($has_penalties) {
      $penalty_th->add("P");
    }

    $this->legendTable = null;
    if (count($tiebreakers) + count($outside_sailors) > 1) {
      $this->legendTable = new LegendTable($tiebreakers, $outside_sailors);
    }
  }
}