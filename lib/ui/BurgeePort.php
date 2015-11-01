<?php
namespace ui;

use \School;
use \WS;

use \XA;
use \XImg;
use \XP;
use \XPort;

/**
 * A port that displays a school's burgee.
 *
 * @author Dayan Paez
 * @version 2015-11-06
 */
class BurgeePort extends XPort {

  public function __construct(School $school) {
    parent::__construct(
      new XA(
        $lnk = WS::link(sprintf('/prefs/%s/logo', $school->id)),
        $school->nick_name . " logo"
      )
    );
    $this->set('id', 'port-burgee');
    if ($school->burgee === null) {
      $this->add(
        new XP(
          array('class'=>'message'),
          new XA($lnk, "Add one now")
        )
      );
    }
    else {
      $this->add(
        new XP(
          array('class'=>'burgee-cell'),
          new XA($lnk, new XImg('data:image/png;base64,'.$school->burgee->filedata, $school->nick_name))
        )
      );
    }
  }

}