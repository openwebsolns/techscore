<?php
namespace users\utils;

use \AbstractUnitTester;
use \Account;
use \DB;
use \Email_Token;
use \InvalidArgumentException;
use \School;
use \STN;

/**
 * Unit test the registration e-mail sender.
 *
 * @author Dayan Paez
 * @version 2015-11-06
 */
class RegistrationEmailSenderTest extends AbstractUnitTester {

  private $testObject;
  private $core;

  protected function setUp() {
    $this->core = new RegistrationEmailSenderTestDB();
    $this->testObject = new RegistrationEmailSender();
    $this->testObject->setCore($this->core);

    $_SERVER['HTTP_HOST'] = "localhost";
  }

  public function testSendRegistrationEmail() {
    $template = "Test Template";
    $subject = "Test Subject";
    $sendTo = "test@email.com";

    $link = 'TestLink';

    $account = new Account();
    $account->id = "foo";
    $account->email = $sendTo;

    $this->testObject->setEmailSubject($subject);
    $this->testObject->setEmailTemplate($template);
    $result = $this->testObject->sendRegistrationEmail($account, $link);
    $this->assertTrue($result);

    // Verify!
    $recipients = $this->core->getMailedTos();
    $this->assertEquals(1, count($recipients));
    $this->assertEquals($sendTo, $recipients[0]);

    $subjects = $this->core->getMailedSubjects();
    $this->assertContains($subject, $subjects[0]);

    $bodies = $this->core->getMailedBodies();
    $this->assertEquals($template, $bodies[0]);
  }

  public function testAutoinjection() {
    $link = "token";
    $account = new Account();
    $account->id = "foo";
    $account->email = "send";

    $result = $this->testObject->sendRegistrationEmail($account, $link);
    $this->assertTrue($result);    
  }
}

/**
 * Mock DB.
 */
class RegistrationEmailSenderTestDB extends DB {

  private static $mailedTo = array();
  private static $mailedSubject = array();
  private static $mailedBody = array();

  public static function g($name) {
    switch ($name) {
    case STN::MAIL_REGISTER_USER:
      return "{BODY}";
    case STN::APP_NAME:
      return "TS";
    default:
      throw new InvalidArgumentException("Did not expect $name.");
    }
  }

  public static function keywordReplace($template, Account $account, School $school = null) {
    return $template;
  }

  public static function mail($to, $subject, $body, $wrap = true, Array $extra_headers = array(), Array $attachments = array(), $read_token = null) {
    self::$mailedTo[] = $to;
    self::$mailedSubject[] = $subject;
    self::$mailedBody[] = $body;
    return true;
  }

  public function getMailedTos() {
    return self::$mailedTo;
  }

  public function getMailedSubjects() {
    return self::$mailedSubject;
  }

  public function getMailedBodies() {
    return self::$mailedBody;
  }
}