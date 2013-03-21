<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Displays a list of all the registered sailors.
 *
 * @author Dayan Paez
 * @version 2010-01-23
 */
class RegistrationsDialog extends AbstractDialog {

  /**
   * Creates a new registrations dialog
   *
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Record of Participation", $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $teams     = $this->REGATTA->getTeams();
    $rpManager = $this->REGATTA->getRpManager();

    $this->PAGE->addContent($p = new XPort("Registrations"));
    $p->add(new XTable(array('class'=>'coordinate sailors'),
                       array(new XTHead(array(),
                                        array(new XTR(array(),
                                                      array(new XTH(array(), "Team"),
                                                            new XTH(array(), "Div."),
                                                            new XTH(array(), "Skipper"),
                                                            new XTH(array(), "Races"),
                                                            new XTH(array(), "Crew"),
                                                            new XTH(array(), "Races"))))),
                             $tab = new XTBody())));

    $races_in_div = array();
    foreach ($divisions as $div)
      $races_in_div[(string)$div] = count($this->REGATTA->getRaces($div));
    $row_index = 0;
    foreach ($teams as $team) {
      $row_index++;

      $first_row = new XTR(array('class'=>'row'.($row_index % 2)),
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
              $row->add(new XTD(array(), $sailors[$i]->sailor));
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
?>
