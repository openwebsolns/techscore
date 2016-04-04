<?php
use \users\RegisterPane;
use \mail\senders\SessionMailSender;

require_once('AbstractTester.php');

/**
 * Test the registration process, including e-mail sending.
 *
 * @author Dayan Paez
 * @version 2016-02-05
 */
class RegisterTest extends AbstractTester {

  private static $url = '/register';

  private $registrationStatus;
  private $email;
  private $divertMailSetting;

  protected function setUp() {
    $this->registrationStatus = DB::g(STN::ALLOW_REGISTER);
    $this->divertMailSetting = DB::g(STN::DIVERT_MAIL);
    DB::s(STN::DIVERT_MAIL, null);
    do {
      $this->email = sprintf('test+%s@localhost.com', rand());
      $account = DB::getAccountByEmail($this->email);
    } while ($account !== null);
    $this->startSession();
  }

  protected function tearDown() {
    DB::s(STN::ALLOW_REGISTER, $this->registrationStatus);
    DB::s(STN::DIVERT_MAIL, $this->divertMailSetting);
    DB::commit();
    $account = DB::getAccountByEmail($this->email);
    if ($account !== null) {
      DB::remove($account);
    }
  }

  public function testRegistrationOff() {
    DB::s(STN::ALLOW_REGISTER, null);
    DB::commit();

    $response = $this->getUrl(self::$url);
    $this->assertResponseStatus($response, 303, "Expected redirect with no registration.");
  }

  public function testRegistrationOn() {
    DB::s(STN::ALLOW_REGISTER, 1);
    DB::commit();

    $response = $this->getUrl(self::$url);
    $this->assertResponseStatus($response);

    // Assert fields exist:
    $body = $response->getBody();
    $root = $body->asXml();
    $inputs = array(
      'email',
      'first_name',
      'last_name',
      'passwd',
      'confirm',
    );
    $selects = array(
      'role',
    );
    foreach ($inputs as $inputName) {
      $this->findInputElement($root, 'input', $inputName);
    }
    foreach ($selects as $inputName) {
      $this->findInputElement($root, 'select', $inputName);
    }

    // Fill out registration
    $params = array(
      RegisterPane::SUBMIT_REGISTER => "Register",
      'role' => Account::ROLE_COACH,
    );
    foreach ($inputs as $inputName) {
      $params[$inputName] = $this->email;
    }

    $response = $this->postUrl(self::$url, $params);
    $this->assertResponseStatus($response, 303);

    $data = $this->getSessionData();
    $this->assertTrue(array_key_exists(SessionMailSender::KEY_INBOX, $data), "No e-mail messages found.");
    $emails = $data[SessionMailSender::KEY_INBOX];
    $this->assertEquals(1, count($emails));

    $email = $emails[0];
    $recipients = $email->getRecipients();
    $this->assertEquals(1, count($recipients));
    $this->assertEquals($this->email, $recipients[0]);

    $linkRegex = DB::addRegexDelimiters(sprintf('(https?://[^/]+/register\?token=[A-Za-z0-9]+)'));
    $regexMatch = array();
    foreach ($email->getAlternatives() as $alternative) {
      $content = $alternative->getContent();
      if (!empty($content)) {
        $this->assertEquals(1, preg_match($linkRegex, $content, $regexMatch), "Unable to find link in...\n\n" . print_r($content, true));
        break;
      }
    }
    $this->assertTrue(count($regexMatch) > 0);
    $link = array_pop($regexMatch);

    // Follow the link!
    $response = $this->getUrl($link);
    $this->assertResponseStatus($response);
    DB::commit();
    $account = DB::getAccountByEmail($this->email);
    $this->assertEquals(Account::STAT_PENDING, $account->status);

    // Email to "admins"?
    $data = $this->getSessionData();
    $this->assertTrue(array_key_exists(SessionMailSender::KEY_INBOX, $data), "No e-mail messages found.");
    $emails = $data[SessionMailSender::KEY_INBOX];
    foreach ($emails as $email) {
      $this->assertNotFalse(mb_strpos($email->getSubject(), RegisterPane::MAIL_REGISTER_ADMIN_SUBJECT));
      foreach ($email->getRecipients() as $recipient) {
        $recipientAddress = $this->extractEmail($recipient);
        $account = DB::getAccountByEmail($recipientAddress);
        $this->assertNotNull($account);
        $this->assertTrue($account->can(Permission::EDIT_USERS));
      }
    }
  }

  /**
   * "Foo Young <email@domain.tld>" -> "email@domain.tld"
   * "email@domain.tld"             -> "email@domain.tld"
   */
  private function extractEmail($address) {
    $match = array();
    $regex = '/^[^<]+<([^>]+)>$/';
    if (preg_match($regex, $address, $match) > 0) {
      return array_pop($match);
    }
    return $address;
  }
}