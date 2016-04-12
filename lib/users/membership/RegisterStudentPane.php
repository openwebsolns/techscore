<?php
namespace users\membership;

use \model\StudentProfile;
use \model\StudentProfileContact;
use \ui\CountryStateSelect;
use \ui\ProgressDiv;
use \users\AbstractUserPane;
use \users\utils\RegisterAccountHelper;
use \users\utils\RegistrationEmailSender;
use \xml5\SessionParams;

use \Account;
use \DateTime;
use \DB;
use \Email_Token;
use \InvalidArgumentException;
use \Session;
use \SoterException;
use \STN;
use \Text_Entry;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XEm;
use \XDateInput;
use \XEmailInput;
use \XNumberInput;
use \XP;
use \XPasswordInput;
use \XPort;
use \XRawText;
use \XSelect;
use \XStrong;
use \XSubmitInput;
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
  const SUBMIT_RESEND = 'submit-resend';
  const SUBMIT_CANCEL = 'submit-cancel';
  const SESSION_KEY = 'sailor-registration';
  const KEY_ACCOUNT = 'account';

  const INPUT_EMAIL = 'email';
  const INPUT_TOKEN = 'token';

  private $registrationEmailSender;
  private $registerAccountHelper;

  public function __construct() {
    parent::__construct("Register as a sailor");
  }

  protected function fillHTML(Array $args) {
    if (array_key_exists(self::INPUT_TOKEN, $args)) {
      try {
        $this->processToken(DB::$V->reqID($args, self::INPUT_TOKEN, DB::T(DB::EMAIL_TOKEN), "Invalid token provided."));
      }
      catch (SoterException $e) {
        Session::error($e->getMessage());
        $this->redirect();
      }
    }

    $session = Session::g(self::SESSION_KEY, array());
    if (array_key_exists(self::KEY_ACCOUNT, $session)) {
      $account = DB::getAccount($session[self::KEY_ACCOUNT]);
      if ($account !== null) {
        $this->PAGE->addContent($p = new XPort(sprintf("%s account", DB::g(STN::APP_NAME))));
        $p->add(new XP(array(), "Thank you for registering for an account with TechScore. You should receive an e-mail message shortly with a link to verify your account access."));
        $p->add(new XP(array(),
                       array("If you don't receive an e-mail, please check your junk-mail settings and enable mail from ",
                             new XEm(DB::g(STN::TS_FROM_MAIL)), ".")));
        $p->add($form = $this->createForm());
        $form->add($xp = new XSubmitP(self::SUBMIT_RESEND, "Resend"));
        $xp->add(" ");
        $xp->add(new XSubmitInput(self::SUBMIT_CANCEL, "Cancel"));
        return;
      }
    }

    $this->fillStageIntro($args);
    $this->fillStageTechscoreAccount($args);
  }

  private function fillStageIntro(Array $args) {
    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_REGISTER_MESSAGE);
    if ($cont !== null) {
      $this->PAGE->addContent($p = new XPort("About sailor registrations"));
      $p->add(new XRawText($cont->html));
    }
  }

  private function fillStageTechscoreAccount(Array $args) {
    $this->PAGE->addContent($form = $this->createForm());
    $form->add($p = new XPort(sprintf("%s account", DB::g(STN::APP_NAME))));
    $p->add(new XP(array(), array("Registering as a student will automatically create a system account. ", new XStrong("Important:"), " if you already have an account, you do not need to register again. ", new XA($this->linkTo('HomePane'), "Login instead"), " and create a student profile from the user menu.")));

    $p->add(new FReqItem("Email:", new XEmailInput(self::INPUT_EMAIL, "")));
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

    $form->add(new XSubmitP(self::SUBMIT_REGISTER, "Create profile"));
  }

  public function process(Array $args) {
    // Token?
    if (array_key_exists(self::INPUT_TOKEN, $args)) {
      $this->processToken(DB::$V->reqID($args, self::INPUT_TOKEN, DB::T(DB::EMAIL_TOKEN), "Invalid token provided."));
      return;
    }

    // Resend
    if (array_key_exists(self::SUBMIT_RESEND, $args)) {
      $session = Session::g(self::SESSION_KEY, array());
      if (!array_key_exists(self::KEY_ACCOUNT, $session)) {
        throw new SoterException("No registration in progress.");
      }
      $account = DB::getAccount($session[self::KEY_ACCOUNT]);
      if ($account == null) {
        throw new SoterException("No registration in progress. Please start again.");
      }
      $token = $account->getToken();
      $sender = $this->getRegistrationEmailSender();
      if (!$sender->sendRegistrationEmail($account, $this->link(array(self::INPUT_TOKEN => (string) $token)))) {
        throw new SoterException("There was an error with your request. Please try again later.");
      }

      Session::info("Activation e-mail sent again.");
      return;
    }

    // Cancel
    if (array_key_exists(self::SUBMIT_CANCEL, $args)) {
      Session::d(self::SESSION_KEY);
      return;
    }

    // Stage account
    if (array_key_exists(self::SUBMIT_REGISTER, $args)) {
      $helper = $this->getRegisterAccountHelper();
      $account = $helper->process($args);

      $existingAccount = DB::getAccountByEmail($account->email);
      if ($existingAccount !== null) {
        if ($existingAccount->status != Account::STAT_REQUESTED) {
          throw new SoterException("Invalid e-mail. Remember, if you already have an account, please log-in to continue.");
        }
        $account->id = $existingAccount->id;
      }

      $account->ts_role = DB::getStudentRole();
      if ($account->ts_role === null) {
        throw new InvalidArgumentException("No student role exists. This should NOT be allowed.");
      }
      $account->role = Account::ROLE_STUDENT;

      $token = $account->createToken();
      $sender = $this->getRegistrationEmailSender();
      if (!$sender->sendRegistrationEmail($account, $this->link(array(self::INPUT_TOKEN => (string) $token)))) {
        throw new SoterException("There was an error with your request. Please try again later.");
      }

      // Profile
      $profile = new StudentProfile();
      $profile->first_name = $account->first_name;
      $profile->middle_name = DB::$V->incString($args, 'middle_name', 1, 16000);
      $profile->last_name = $account->last_name;
      $profile->school = DB::$V->reqID($args, 'school', DB::T(DB::SCHOOL), "Invalid school chosen.");
      $profile->gender = DB::$V->reqValue($args, 'gender', array(StudentProfile::MALE, StudentProfile::FEMALE), "Invalid gender chosen.");
      $profile->owner = $account;

      $currentTime = new DateTime();
      $currentYear = $currentTime->format('Y');
      $profile->graduation_year = DB::$V->reqInt($args, 'graduation_year', $currentYear - 1, $currentYear + 7);
      $profile->birth_date = DB::$V->reqDate($args, 'birth_date', new DateTime('1900-01-01'), new DateTime(), "Invalid birth date provided.");
      $profile->status = StudentProfile::STATUS_REQUESTED;

      // Contacts
      $contactSections = DB::$V->reqList($args, 'contact', null, "Missing student contact information.");

      $contactSection = DB::$V->reqList($contactSections, 'school', null, "Missing student's school contact information.");
      $contactSection['email'] = $account->email;
      $schoolContact = StudentProfileContact::createFromArgs($contactSection, "School contact error: %s.");
      $schoolContact->student_profile = $profile;
      $schoolContact->contact_type = StudentProfileContact::CONTACT_TYPE_SCHOOL;

      $contactSection = DB::$V->reqList($contactSections, 'home', null, "Missing student's home contact information.");
      $homeContact = StudentProfileContact::createFromArgs($contactSection, "Home contact error: %s.");
      $homeContact->student_profile = $profile;
      $homeContact->contact_type = StudentProfileContact::CONTACT_TYPE_HOME;

      DB::set($account);
      Session::info("New account request processed.");

      DB::set($profile);
      DB::set($schoolContact);
      DB::set($homeContact);
      Session::info("Sailor profile created.");
      Session::s(self::SESSION_KEY, array(self::KEY_ACCOUNT => $account->id));
      return;
    }
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

  private function processToken(Email_Token $token) {
    $account = $token->account;
    if (!$token->isTokenActive()) {
      throw new SoterException("Token provided has expired.");
    }
    if ($account->ts_role != DB::getStudentRole()) {
      throw new SoterException("This account is not available for sailor registration.");
    }
    if ($account->status != Account::STAT_REQUESTED) {
      throw new SoterException("Invalid token.");
    }
    // Automatically approve, pending profile
    $newStatus = Account::STAT_ACCEPTED;
    if (DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SAILOR_EULA) === null) {
      $newStatus = Account::STAT_ACTIVE;
    }
    $account->status = $newStatus;
    DB::set($account);
    Session::info("Account successfully activated.");
    Session::s(SessionParams::USER, $account->id);
    $this->redirectTo('HomePane');
  }

  public function setRegistrationEmailSender(RegistrationEmailSender $sender) {
    $this->registrationEmailSender = $sender;
  }

  protected function getRegistrationEmailSender() {
    if ($this->registrationEmailSender === null) {
      $this->registrationEmailSender = new RegistrationEmailSender(RegistrationEmailSender::MODE_SAILOR);
    }
    return $this->registrationEmailSender;
  }

  public function setRegisterAccountHelper(RegisterAccountHelper $helper) {
    $this->registerAccountHelper = $helper;
  }

  protected function getRegisterAccountHelper() {
    if ($this->registerAccountHelper === null) {
      $this->registerAccountHelper = new RegisterAccountHelper();
    }
    return $this->registerAccountHelper;
  }
}