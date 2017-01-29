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
use \WS;

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
use \XScript;
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
  const SESSION_POST = 'POST';

  const INPUT_EMAIL = 'email';
  const INPUT_TOKEN = 'token';

  private $registrationEmailSender;
  private $registerAccountHelper;

  public function __construct(Account $user = null) {
    parent::__construct("Register as a sailor", $user);
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
        if ($account->status == Account::STAT_REQUESTED) {
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

        // Logged-in?
        $this->redirectTo('HomePane');
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
    $ref = Session::g(self::SESSION_POST);
    if ($ref === null) {
      $ref = array();
    }

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/sailorRegistration.js')));
    $this->PAGE->addContent($form = $this->createForm());
    $form->set('id', 'sailor-registration-form');
    if ($this->USER === null) {
      $form->add($p = new XPort(sprintf("%s account", DB::g(STN::APP_NAME))));
      $p->add(new XP(array(), array("Registering as a student will automatically create a system account. ", new XStrong("Important:"), " if you already have an account, you do not need to register again. ", new XA($this->linkTo('HomePane'), "Login instead"), " and create a student profile from the user menu.")));

      $p->add(new FReqItem("Email:", new XEmailInput(self::INPUT_EMAIL, $this->getField($ref, self::INPUT_EMAIL))));
      $p->add(new FReqItem("First name:", new XTextInput('first_name', $this->getField($ref, 'first_name'))));
      $p->add(new FItem("Middle name:", new XTextInput('middle_name', $this->getField($ref, 'middle_name')), "Middle initial or full name."));
      $p->add(new FReqItem("Last name:",  new XTextInput('last_name', $this->getField($ref, 'last_name'))));
      $p->add(new FReqItem("Password:", new XPasswordInput('passwd', "")));
      $p->add(new FReqItem("Confirm password:", new XPasswordInput('confirm', "")));
    }

    $form->add($p = new XPort("Sailor profile"));
    $p->add(new FReqItem("School:", $this->getSchoolSelect($this->getField($ref, 'school'))));
    $currentTime = new DateTime();
    $currentYear = $currentTime->format('Y');
    $p->add(new FReqItem("Graduation Year:", new XNumberInput('graduation_year', $this->getField($ref, 'graduation_year'), 2000, $currentYear + 10, 1)));
    $p->add(new FReqItem("Date of birth:", new XDateInput('birth_date', $this->getDateField($ref, 'birth_date'))));
    $options = array(
      '' => '',
      StudentProfile::FEMALE => "Female",
      StudentProfile::MALE => "Male",
    );
    $p->add(new FReqItem("Gender:", XSelect::fromArray('gender', $options, $this->getField($ref, 'gender')), "To be eligible for women's regattas, you must enter \"Female\"."));

    $contacts = DB::$V->incList($ref, 'contact');

    $cref = DB::$V->incList($contacts, 'school');
    $form->add($p = new XPort("School year contact"));
    $p->set('id', 'school-contact');
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[school][address_1]', $this->getField($cref, 'address_1'))));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[school][address_2]', $this->getField($cref, 'address_2'))));
    $p->add(new FReqItem("City:", new XTextInput('contact[school][city]', $this->getField($cref, 'city'))));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[school][state]', $this->getField($cref, 'state'))));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[school][postal_code]', $this->getField($cref, 'postal_code'))));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[school][telephone]', $this->getField($cref, 'telephone'))));
    $p->add(new FItem("Secondary phone:", new XTelInput('contact[school][secondary_telephone]', $this->getField($cref,  'secondary_telephone'))));
    $p->add(new FItem("Information current until:", new XDateInput('contact[school][current_until]', $this->getDateField($cref, 'current_until'))));

    $cref = DB::$V->incList($contacts, 'home');
    $form->add($p = new XPort("Home/permanent contact"));
    $p->set('id', 'home-contact');
    $p->add(new FReqItem("Email:", new XEmailInput('contact[home][email]', $this->getField($cref, 'email'))));
    $p->add(new FReqItem("Address line 1:", new XTextInput('contact[home][address_1]', $this->getField($cref, 'address_1'))));
    $p->add(new FItem("Address line 2:", new XTextInput('contact[home][address_2]', $this->getField($cref, 'address_2'))));
    $p->add(new FReqItem("City:", new XTextInput('contact[home][city]', $this->getField($cref, 'city'))));
    $p->add(new FReqItem("State:", new CountryStateSelect('contact[home][state]', $this->getField($cref, 'state'))));
    $p->add(new FReqItem("Postal code:", new XTextInput('contact[home][postal_code]', $this->getField($cref, 'postal_code'))));
    $p->add(new FReqItem("Phone:", new XTelInput('contact[home][telephone]', $this->getField($cref, 'telephone'))));
    $p->add(new FItem("Information current until:", new XDateInput('contact[home][current_until]', $this->getDateField($cref, 'current_until'))));

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

    // Register
    if (array_key_exists(self::SUBMIT_REGISTER, $args)) {
      if ($this->USER === null) {
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
      }
      else {
        $account = $this->USER;
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
      $profile->graduation_year = DB::$V->reqInt($args, 'graduation_year', 2000, $currentYear + 11, "Invalid graduation year provided.");
      $profile->birth_date = DB::$V->reqDate($args, 'birth_date', new DateTime('1900-01-01'), new DateTime(), "Invalid birth date provided.");
      $profile->status = StudentProfile::STATUS_REQUESTED;

      // Contacts
      $contactSections = DB::$V->reqList($args, 'contact', null, "Missing student contact information.");

      $contactSection = DB::$V->reqList($contactSections, 'school', null, "Missing student's school contact information.");
      $contactSection['email'] = $account->email;
      $schoolContact = StudentProfileContact::createFromArgs($contactSection, "School contact error: %s");
      $schoolContact->student_profile = $profile;
      $schoolContact->contact_type = StudentProfileContact::CONTACT_TYPE_SCHOOL;

      $contactSection = DB::$V->reqList($contactSections, 'home', null, "Missing student's home contact information.");
      $homeContact = StudentProfileContact::createFromArgs($contactSection, "Home contact error: %s");
      $homeContact->student_profile = $profile;
      $homeContact->contact_type = StudentProfileContact::CONTACT_TYPE_HOME;

      if ($this->USER === null) {
        DB::set($account);
        Session::info("New account request processed.");
      }

      DB::set($profile);
      DB::set($schoolContact);
      DB::set($homeContact);
      Session::info("Sailor profile created.");
      Session::s(self::SESSION_KEY, array(self::KEY_ACCOUNT => $account->id));
      return;
    }
  }

  private function getSchoolSelect($chosen = null) {
    $aff = new XSelect('school');
    $aff->add(new FOption('0', "[Choose one]"));
    foreach (DB::getConferences() as $conf) {
      $aff->add($opt = new FOptionGroup($conf));
      foreach ($conf->getSchools() as $school) {
        $attrs = ($school->id === $chosen) ? array('selected' => 'selected') : array();
        $opt->add(new FOption($school->id, $school->name, $attrs));
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
    // Automatically approve account; profile pending
    $account->status = Account::STAT_ACTIVE;
    DB::set($account);
    Session::info("Account successfully activated.");
    Session::s(SessionParams::USER, $account->id);
    $this->redirectTo('HomePane');
  }

  private function getField(Array $ref, $field_name, $default = null) {
    return (array_key_exists($field_name, $ref)) ? $ref[$field_name] : $default;
  }

  private function getDateField(Array $ref, $field_name, DateTime $default = null) {
    $value = $this->getField($ref, $field_name);
    return ($value !== null) ? new DateTime($value) : $default;
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