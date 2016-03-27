<?php
namespace users\membership;

use \ui\StnCheckbox;
use \users\AbstractUserPane;

use \Account;
use \DB;
use \Session;
use \SoterException;
use \STN;

use \FItem;
use \XP;
use \XPort;
use \XSubmitP;
use \XWarning;

/**
 * Centralized management of membership settings.
 *
 * @author Dayan Paez
 * @version 2016-03-25
 */
class MembershipSettingsPane extends AbstractUserPane {

  const SUBMIT_ENABLE = 'submit-enable';

  public function __construct(Account $user) {
    parent::__construct("Sailor database settings", $user);
  }

  protected function fillHTML(Array $args) {
    $this->fillGeneralSwitch($args);
    $this->fillRole($args);
    $this->fillTextSettings($args);
  }

  private function fillGeneralSwitch(Array $args) {
    $this->PAGE->addContent($p = new XPort("General switch"));
    $cannotEnableMessage = $this->validateSettings();
    if ($cannotEnableMessage !== null) {
      $p->add(new XWarning(sprintf("This feature cannot be enabled until all issues are solved: %s", $cannotEnableMessage)));
    }
    else {
      $p->add($form = $this->createForm());
      $form->add(new FItem("Turn-on:", new StnCheckbox(STN::ENABLE_SAILOR_REGISTRATION, "Go live with registration and sailor eiligibility tracking.")));
      $form->add(new XSubmitP(self::SUBMIT_ENABLE, "Save"));
    }
  }

  private function fillRole(Array $args) {
    $this->PAGE->addContent($p = new XPort("Student role"));
    $p->add(new XP(array(), sprintf("Every student that registers will automatically also get a %s account. Unlike normal scorer registrations, however, these accounts should have a separate role, in order to more correctly limit what these registrants can do. Please indicate the role to use for this purpose below, or create a new one.", DB::g(STN::APP_NAME))));
  }

  private function fillTextSettings(Array $args) {
    $this->PAGE->addContent($p = new XPort("Copy settings"));
  }

  public function process(Array $args) {
    if (array_key_exists(self::SUBMIT_ENABLE, $args)) {
      DB::s(
        STN::ENABLE_SAILOR_REGISTRATION,
        DB::$V->incInt($args, STN::ENABLE_SAILOR_REGISTRATION, 1, 2, null)
      );
      Session::info("Changes saved.");
    }
  }

  private function validateSettings() {
    // ROLE?
    if (DB::getStudentRole() === null) {
      return "Missing the role to assign new students.";
    }
    return null;
  }

}