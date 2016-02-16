<?php

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

  protected function setUp() {
    $this->registrationStatus = DB::g(STN::ALLOW_REGISTER);
  }

  protected function tearDown() {
    DB::s(STN::ALLOW_REGISTER, $this->registrationStatus);
    DB::commit();
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
  }

}