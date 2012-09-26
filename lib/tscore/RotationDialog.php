<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Displays the rotation for a given regatta
 *
 */
class RotationDialog extends AbstractDialog {

  private $rotation;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Rotation", $reg);
    $this->rotation = $this->REGATTA->getRotation();
  }

  /**
   * Generates an HTML table for the given division
   *
   * @param Division $div the division
   * @param boolean $link_schools true to create link to school's summary
   * @return Rotation $rot
   */
  public function getTable(Division $div, $link_schools = false) {
    $header = array("", "Team");
    $races = $this->REGATTA->getRaces($div);
    foreach ($races as $race)
      $header[] = (string)$race;

    $tab = new XQuickTable(array('class'=>'coordinate rotation'), $header);

    $rowIndex = 0;
    foreach ($this->REGATTA->getTeams() as $team) {
      $row = array();
      $burgee = "";
      if ($team->school->burgee !== null) {
        $url = sprintf("/inc/img/schools/%s.png", $team->school->id);
        $burgee = new XImg($url, $team->school->id, array('height'=>'30px'));
      }
      $row[] = $burgee;

      // Team name
      $name = (string)$team;
      if ($link_schools !== false)
        $name = array(new XA(sprintf('/schools/%s/%s/', $team->school->id, $this->REGATTA->getSeason()), $team->school->nick_name),
                      " ",
                      $team->name);
      $row[] = new XTD(array('class'=>'teamname'), $name);

      foreach ($races as $race) {
        $sail = $this->rotation->getSail($race, $team);
        $sail = ($sail !== null) ? $sail : "";
        $row[] = $sail;
      }
      $tab->addRow($row, array('class'=>'row'.($rowIndex++%2)));
    }

    return $tab;
  }

  /**
   * Creates a table for each division
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->REGATTA->getDivisions() as $div) {
      $this->PAGE->addContent($p = new XPort(sprintf("Division %s", $div)));
      $p->add($this->getTable($div));
    }
  }
}
