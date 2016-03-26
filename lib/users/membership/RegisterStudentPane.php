<?php
namespace users\membership;

use \users\AbstractUserPane;

use \DB;
use \STN;
use \Text_Entry;

use \XPort;
use \XRawText;

/**
 * Allows students to self-register as sailors. This is the entry way
 * to the system as manager of the sailor database.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RegisterStudentPane extends AbstractUserPane {

  public function __construct() {
    parent::__construct("Register as a sailor");
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Student Registration"));

    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_REGISTER_MESSAGE);
    if ($cont !== null) {
      $p->add(new XRawText($cont->html));
    }

  }

  public function process(Array $args) {

  }

}