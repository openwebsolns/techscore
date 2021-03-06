<?php
namespace data;

use \Division;
use \FullRegatta;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;
use \SailTD;

require_once('xml5/HtmlLib.php');
require_once('xml5/TS.php');

/**
 * A fleet racing rotation table.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class RotationTable extends XTable {

  const SCORED_CLASSNAME = 'scored';
  const SCORED_TITLE = "Race has been scored";

  /**
   * Generates an HTML table for the given regatta and division.
   *
   * @param Regatta $regatta the regatta.
   * @param Division $div the division.
   * @param boolean $link_schools true to create link to school's summary.
   */
  public function __construct(FullRegatta $regatta, Division $div, $link_schools = false) {
    parent::__construct(
      array('class'=>'rotation'),
      array(
        new XTHead(array(), array($head = new XTR())),
        $body = new XTBody(),
      )
    );

    $rotation = $regatta->getRotationManager();
    $races = $regatta->getRaces($div);
    $head->add(new XTH());
    $head->add(new XTH(array(), "Team"));
    $scoredRaces = array();
    foreach ($races as $race) {
      $attrs = array();
      if (count($regatta->getFinishes($race)) > 0) {
        $scoredRaces[$race->id] = $race;
        $attrs['class'] = self::SCORED_CLASSNAME;
        $attrs['title'] = self::SCORED_TITLE;
      }
      $head->add(new XTH($attrs, (string) $race));
    }

    $rowIndex = 0;
    $season = $regatta->getSeason();
    foreach ($regatta->getTeams() as $team) {
      $body->add($row = new XTR(array('class'=>'row'.($rowIndex++%2))));
      $row->add(new XTD(array('class'=>'burgee-cell'), $team->school->drawSmallBurgee("")));

      // Team name
      $name = (string)$team;
      if ($link_schools !== false) {
        $name = array(
          new XA(sprintf('%s%s/', $team->school->getURL(), $season), $team->school->nick_name),
          " ",
          $team->toView($link_schools)
        );
      }
      $row->add(new XTD(array('class'=>'teamname'), $name));

      foreach ($races as $race) {
        $attrs = array();
        if (array_key_exists($race->id, $scoredRaces)) {
          $attrs['class'] = self::SCORED_CLASSNAME;
          $attrs['title'] = self::SCORED_TITLE;
        }
        $sail = $rotation->getSail($race, $team);
        $row->add(new SailTD($sail, $attrs));
      }
    }
  }
}