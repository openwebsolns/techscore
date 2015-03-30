<?php
namespace data;

use \FullRegatta;
use \RP;
use \DB;

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
          if ($i == 0)
            $row->add(new XTD(array('rowspan' => $num_subrows, 'class'=>'division-cell'), $div));


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
        $team_td->set('rowspan', $num_rows);
      }
    }
  }
}