<?php
namespace data;

use \FullRegatta;
use \RP;
use \DB;
use \STN;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XSpan;

require_once('xml5/HtmlLib.php');

/**
 * Table of sailors participating in fleet racing regatta.
 *
 * 2015-12-03: Also include the division rank.
 *
 * @author Dayan Paez
 * @version 2015-03-23
 */
class RegistrationsTable extends XTable {

  /**
   * Creates a new registrations table.
   *
   * @param FullRegatta $regatta the regatta.
   */
  public function __construct(FullRegatta $regatta) {
    parent::__construct(
      array('class'=>'coordinate sailors'),
      array(
        new XTHead(
          array(),
          array(
            new XTR(
              array(),
              array(
                new XTH(array(), "Team"),
                new XTH(array(), "Div."),
                new XTH(array(), "Rank"),
                new XTH(array(), "Skipper"),
                new XTH(array(), "Races"),
                new XTH(array(), "Crew"),
                new XTH(array(), "Races"))))),
        $tab = new XTBody()));

    $divisions = $regatta->getDivisions();
    $teams     = $regatta->getTeams();
    $rpManager = $regatta->getRpManager();

    // Number of races in each division
    $races_in_div = array();
    foreach ($divisions as $div) {
      $num = count($regatta->getRaces($div));
      $races_in_div[(string)$div] = $num;
    }

    // Fill the table
    $row_index = 0;
    foreach ($teams as $team) {
      $row_index++;

      $first_row = new XTR(
        array('class'=>'row'.($row_index % 2)),
        array($team_td = new XTD(array('class'=>'schoolname teamname'), $team)));

      $is_first = true;
      $num_rows = 0;
      // For each division
      foreach ($divisions as $div) {
        // Get skipper and crew
        $skips = $rpManager->getRP($team, $div, RP::SKIPPER);
        $crews = $rpManager->getRP($team, $div, RP::CREW);
        $rank = $team->getRank($div);

        $num_subrows = max(count($skips), count($crews), 1);
        $num_rows += $num_subrows;
        for ($i = 0; $i < $num_subrows; $i++) {
          if ($is_first) {
            $row = $first_row;
            $row->set('class', 'topborder row'.($row_index % 2));
            $is_first = false;
          }
          else {
            $row = new XTR(array('class'=>'row'.($row_index % 2)));
          }


          // Division
          if ($i == 0) {
            $explanation = '';
            $rankValue = '--';
            if ($rank !== null) {
              $explanation = $rank->explanation;
              $rankValue = $rank->rank;
            }
            $row->add(new XTD(array('rowspan' => $num_subrows, 'class'=>'division-cell'), $div));
            $row->add(
              new XTD(
                array(
                  'rowspan' => $num_subrows,
                  'class' => 'rank-cell',
                  'title' => $explanation,
                ),
                $rankValue
              )
            );
          }


          // Skipper and crew, and his races
          foreach (array($skips, $crews) as $sailors) {
            if (isset($sailors[$i])) {
              $row->add(new XTD(array(), $sailors[$i]->getSailor(true)));
              if (count($sailors[$i]->races_nums) != $races_in_div[(string)$div])
                $row->add(new XTD(array('class'=>'races'), DB::makeRange($sailors[$i]->races_nums)));
              else
                $row->add(new XTD());
            }
            else {
              $row->add(new XTD());
              $row->add(new XTD());
            }
          }
          $tab->add($row);
        }
      }

      // Reserves
      if (DB::g(STN::ALLOW_RESERVES)) {
        $reserves = $rpManager->getReserveSailors($team);
        // OPTION 1: One line for all reserves spanning 4 columns
        if (count($reserves) > 0) {
          $tab->add($row = new XTR(array('class'=>'reserves-row row'.($row_index % 2))));
          $num_rows++;

          $row->add(new XTD(array('title' => "Reserves", 'colspan' => 2), "Reserves"));
          $row->add($td = new XTD(array('class'=>'reserves-cell', 'colspan' => 4)));
          foreach ($reserves as $reserve) {
            $td->add(new XSpan($reserve->toView(), array('class'=>'reserve-entry')));
          }
        }
      }

      $team_td->set('rowspan', $num_rows);
    }
  }
}