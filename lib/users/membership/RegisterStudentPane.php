<?php
namespace users\membership;

use \model\StudentProfile;
use \ui\CountryStateSelect;
use \users\AbstractUserPane;

use \DateTime;
use \DB;
use \STN;
use \Text_Entry;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XDateInput;
use \XEmailInput;
use \XNumberInput;
use \XP;
use \XPasswordInput;
use \XPort;
use \XRawText;
use \XSelect;
use \XStrong;
use \XSubmitP;
use \XTelInput;
use \XTextInput;

/**
 * Allows students to self-register as sailors. This is the entry way
 * to the system as manager of the sailor database.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RegisterStudentPane extends AbstractUserPane {

  const SUBMIT_REGISTER = 'submit-register';

  public function __construct() {
    parent::__construct("Register as a sailor");
  }

  protected function fillHTML(Array $args) {
    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_REGISTER_MESSAGE);
    if ($cont !== null) {
      $this->PAGE->addContent(new XRawText($cont->html));
    }

    $this->PAGE->addContent($form = $this->createForm());
    $form->add($p = new XPort(sprintf("%s account", DB::g(STN::APP_NAME))));
    $p->add(new XP(array(), array("Registering as a student will automatically create a system account. ", new XStrong("Important:"), " if you already have an account, you do not need to register again. ", new XA($this->linkTo('HomePane'), "Login instead"), " and create a student profile from the user menu.")));
    $p->add(new FReqItem("Email:", new XEmailInput('email', "")));
    $p->add(new FReqItem("First name:", new XTextInput("first_name", "")));
    $p->add(new FItem("Middle name:", new XTextInput("middle_name", ""), "Middle initial or full name."));
    $p->add(new FReqItem("Last name:",  new XTextInput("last_name", "")));
    $p->add(new FReqItem("Password:", new XPasswordInput("passwd", "")));
    $p->add(new FReqItem("Confirm password:", new XPasswordInput("confirm", "")));

    $form->add($p = new XPort("Sailor profile"));
    $p->add(new FReqItem("School:", $this->getSchoolSelect()));
    $currentTime = new DateTime();
    $currentYear = $currentTime->format('Y');
    $p->add(new FReqItem("Graduation Year:", new XNumberInput('graduation_year', '', $currentYear - 1, $currentYear + 6, 1)));
    $p->add(new FReqItem("Date of birth:", new XDateInput('birth_date')));
    $options = array(
      '' => '',
      StudentProfile::FEMALE => "Female",
      StudentProfile::MALE => "Male",
    );
    $p->add(new FReqItem("Gender:", XSelect::fromArray('gender', $options), "To be eligible for women's regattas, you must enter \"Female\"."));

    $form->add($p = new XPort("School year contact"));
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[school][address_1]', '')));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[school][address_2]', '')));
    $p->add(new FReqItem("City:", new XTextInput('contact[school][city]', '')));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[school][state]')));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[school][postal_code]', '')));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[school][telephone]')));
    $p->add(new FItem("Secondary phone:", new XTelInput('contact[school][secondary_telephone]')));
    $p->add(new FItem("Information current until:", new XDateInput('contact[school][current_until]')));

    $form->add($p = new XPort("Home/permanent contact"));
    $p->add(new FReqItem("Email:", new XEmailInput('contact[home][email]', '')));
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[home][address_1]', '')));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[home][address_2]', '')));
    $p->add(new FReqItem("City:", new XTextInput('contact[home][city]', '')));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[home][state]')));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[home][postal_code]', '')));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[home][telephone]')));
    $p->add(new FItem("Information current until:", new XDateInput('contact[home][current_until]')));

    $form->add(new XSubmitP(self::SUBMIT_REGISTER, "Register"));
  }

  private function getSchoolSelect() {
    $aff = new XSelect('school');
    $aff->add(new FOption('0', "[Choose one]"));
    foreach (DB::getConferences() as $conf) {
      $aff->add($opt = new FOptionGroup($conf));
      foreach ($conf->getSchools() as $school) {
        $opt->add(new FOption($school->id, $school->name));
      }
    }
    return $aff;
  }

  public function process(Array $args) {

  }

}