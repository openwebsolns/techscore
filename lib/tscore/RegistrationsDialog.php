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
  public function __construct(Regatta $reg) {
    parent::__construct("Record of Participation", $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $teams     = $this->REGATTA->getTeams();
    $rpManager = $this->REGATTA->getRpManager();

    $this->PAGE->addContent($p = new XPort("Registrations"));
    $p->add($tab = new XQuickTable(array('class'=>'ordinate sailors'), array("Team", "Div.", "Skipper", "Races", "Crew", "Races")));

    $races_in_div = array();
    foreach ($divisions as $div)
      $races_in_div[(string)$div] = count($this->REGATTA->getRaces($div));
    $row_index = 0;
    foreach ($teams as $team) {
      $row_index++;

      $is_first = true;
      // For each division
      foreach ($divisions as $div) {
        // Get skipper and crew
        $skips = $rpManager->getRP($team, $div, RP::SKIPPER);
        $crews = $rpManager->getRP($team, $div, RP::CREW);

        $num_subrows = max(count($skips), count($crews));
        for ($i = 0; $i < $num_subrows; $i++) {
          $row = array();
          $cls = '';
          if ($is_first) {
            $is_first = false;
            $cls = 'topborder ';
            $row[] = new XTD(array('class'=>'schoolname teamname'), $team);
          }
          else {
            $row[] = "";
          }
          // Division
          if ($i == 0)
            $row[] = $div;
          else
            $row[] = "";

          // Skipper and his races
          if (isset($skips[$i])) {
            $row[] = $skips[$i]->sailor;
            if (count($skips[$i]->races_nums) != $races_in_div[(string)$div])
              $row[] = new XTD(array('class'=>'races'), DB::makeRange($skips[$i]->races_nums));
            else
              $row[] = "";
          }
          else {
            $row[] = "";
            $row[] = "";
          }

          // Crew and his races
          if (isset($crews[$i])) {
            $row[] = $crews[$i]->sailor;
            if (count($crews[$i]->races_nums) != $races_in_div[(string)$div])
              $row[] = new XTD(array('class'=>'races'), DB::makeRange($crews[$i]->races_nums));
            else
              $row[] = "";
          }
          else {
            $row[] = "";
            $row[] = "";
          }

          $tab->addRow($row, array('class'=>$cls . 'row' . ($row_index % 2)));
        }
      }
    }
  }

  public function isActive() {
    return count($this->REGATTA->getTeams()) > 0;
  }

  public function process(Array $args) {
    return $args;
  }
}
?>
