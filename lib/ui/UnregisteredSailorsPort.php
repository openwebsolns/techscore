<?php
namespace ui;

use \School;
use \WS;

use \XPort;
use \XUl;
use \XLi;
use \XEm;

/**
 * Port for displaying school's unregistered sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-06
 */
class UnregisteredSailorsPort extends XPort {

  public function __construct(School $school, Array $sailors) {
    parent::__construct(
      new XA(
        $lnk = WS::link(sprintf('/prefs/%s/sailor', $school->id)),
        "Unreg. sailors for " . $school->nick_name
      )
    );
    $this->set('id', 'port-unregistered');
    $limit = 5;
    if (count($sailors) > 5) {
      $limit = 4;
    }
    $this->add($ul = new XUl());
    for ($i = 0; $i < $limit && $i < count($sailors); $i++) {
      $ul->add(new XLi($sailors[$i]));
    }
    if (count($sailors) > 5) {
      $ul->add(new XLi(new XEm(sprintf("%d more...", (count($sailors) - $limit)))));
    }
  }

}