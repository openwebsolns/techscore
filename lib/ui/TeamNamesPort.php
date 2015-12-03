<?php
namespace ui;

use \School;
use \WS;

use \XA;
use \XLi;
use \XOl;
use \XP;
use \XPort;
use \XStrong;

/**
 * Port to display a school's chosen names.
 *
 * @author Dayan Paez
 * @version 2015-11-06
 */
class TeamNamesPort extends XPort {

  public function __construct(School $school) {
    parent::__construct(
      new XA(
        $lnk = WS::link('/schools-edit', array('id' => $school->id)),
        "Team names for " . $school->nick_name
      )
    );
    ;

    $names = $school->getTeamNames();
    if (count($names) == 0) {
      $this->set('id', 'port-team-names-missing');
      $this->add(
        new XP(
          array(),
          array(
            new XStrong("Note:"), " There are no team names for your school. ",
            new XA($lnk, "Add one now"),
            "."
          )
        )
      );
    }
    else {
      $this->set('id', 'port-team-names');
      $this->add($ul = new XOl());
      foreach ($names as $name) {
        $ul->add(new XLi($name));
      }
    }
  }
}