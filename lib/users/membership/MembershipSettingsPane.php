<?php
namespace users\membership;

use \ui\StnCheckbox;
use \users\AbstractUserPane;
use \xml5\XHtmlPreview;

use \Account;
use \DB;
use \Role;
use \Session;
use \SoterException;
use \STN;
use \Text_Entry;

use \FItem;
use \FReqItem;
use \XA;
use \XDiv;
use \XEm;
use \XP;
use \XPort;
use \XRawText;
use \XSelect;
use \XSpan;
use \XStrong;
use \XSubmitP;
use \XTextInput;
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
    $this->fillSummary($args);
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

  private function fillSummary(Array $args) {
    $this->PAGE->addContent($p = new XPort("Feature summary"));
    $p->add(new XP(array(), array(sprintf("Every student that registers will automatically also get a %s account. Unlike normal scorer registrations, however, these accounts should have a separate role, in order to more correctly limit what these registrants can do. ", DB::g(STN::APP_NAME)), new XStrong("This role does not need any specific permissions."))));

    // Role
    $value = DB::getStudentRole();
    if ($value == null) {
      $value = new XEm("Missing");
    }
    $msg = null;
    $className = 'RoleManagementPane';
    if ($this->isPermitted($className)) {
      $value = new XA($this->linkTo($className), $value);
      $msg = "Click to edit.";
    }
    $p->add($fi = new FItem("Student role:", $value, $msg));

    // Registration message
    $message = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_REGISTER_MESSAGE);
    $value = ($message === null)
      ? new XEm("Missing")
      : new XHtmlPreview(new XRawText($message->html));
    $p->add(new FItem("Registration message:", $value));
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