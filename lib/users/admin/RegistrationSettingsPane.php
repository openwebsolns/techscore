<?php
namespace users\admin;

use \users\AbstractUserPane;

use \Account;
use \DB;
use \STN;
use \Session;

use \FCheckbox;
use \FItem;
use \XP;
use \XPort;
use \XSubmitP;

/**
 * Toggle registration settings.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RegistrationSettingsPane extends AbstractUserPane {

  const SUBMIT_SCORER_REGISTRATION = 'set-register';

  public function __construct(Account $user) {
    parent::__construct("Registration settings", $user);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Allow scorer registrations"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "Check the box below to allow users to register for scoring accounts. If unchecked, users will not be allowed to apply for new accounts. Note that this action has no effect on any pending account requests."));
    $f->add(new FItem("Allow:", new FCheckbox(STN::ALLOW_REGISTER, 1, "Users may register for new scoring accounts through the site.", DB::g(STN::ALLOW_REGISTER) !== null)));
    $f->add(new XSubmitP(self::SUBMIT_SCORER_REGISTRATION, "Save changes"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Update register flag
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_SCORER_REGISTRATION, $args)) {
      $val = DB::$V->incInt($args, STN::ALLOW_REGISTER, 1, 2, null);
      if ($val != DB::g(STN::ALLOW_REGISTER)) {
        DB::s(STN::ALLOW_REGISTER, $val);
        if ($val === null) {
          Session::warn("Users will no longer be able to register for new accounts.");
        }
        else {
          Session::info("Users can register for new accounts, subject to approval.");
        }
      }
      return;
    }
  }

}
