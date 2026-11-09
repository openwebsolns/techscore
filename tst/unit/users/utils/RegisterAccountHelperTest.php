<?php
namespace users\utils;

use \AbstractUnitTester;
use \Account;

/**
 * Test the helper, of course.
 *
 * @author Dayan Paez
 * @version 2016-04-01
 */
class RegisterAccountHelperTest extends AbstractUnitTester {

  private $testObject;
  private $validArgs;

  protected function setUp(): void {
    $this->testObject = new RegisterAccountHelper();
    $this->validArgs = array(
      RegisterAccountHelper::FIELD_EMAIL => 'test@example.com',
      RegisterAccountHelper::FIELD_FIRST_NAME => 'FirstName',
      RegisterAccountHelper::FIELD_LAST_NAME => 'LastName',
      RegisterAccountHelper::FIELD_PASSWORD => 'TestPassword',
      RegisterAccountHelper::FIELD_PASSWORD_CONFIRM => 'TestPassword',
    );
  }

  public function testSuccessful(): void {
    $account = $this->testObject->process($this->validArgs);
    $this->assertNotNull($account);
    $this->assertEquals(Account::STAT_REQUESTED, $account->status);
    $this->assertEquals($this->validArgs[RegisterAccountHelper::FIELD_EMAIL], $account->email);
    $this->assertEquals($this->validArgs[RegisterAccountHelper::FIELD_FIRST_NAME], $account->first_name);
    $this->assertEquals($this->validArgs[RegisterAccountHelper::FIELD_LAST_NAME], $account->last_name);
    $this->assertNotNull($account->password);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessEmailMissing(): void {
    unset($this->validArgs[RegisterAccountHelper::FIELD_EMAIL]);
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessEmailNotEmail(): void {
    $this->validArgs[RegisterAccountHelper::FIELD_EMAIL] = 'not an e-mail';
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessFirstNameMissing(): void {
    unset($this->validArgs[RegisterAccountHelper::FIELD_FIRST_NAME]);
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessLastNameMissing(): void {
    unset($this->validArgs[RegisterAccountHelper::FIELD_LAST_NAME]);
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessPasswordMissing(): void {
    unset($this->validArgs[RegisterAccountHelper::FIELD_PASSWORD]);
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessPasswordConfirmMissing(): void {
    unset($this->validArgs[RegisterAccountHelper::FIELD_PASSWORD_CONFIRM]);
    $this->testObject->process($this->validArgs);
  }

  /**
   * @expectedException SoterException
   */
  public function testProcessPasswordMismatch(): void {
    $this->validArgs[RegisterAccountHelper::FIELD_PASSWORD_CONFIRM] = "Different one altogether";
    $this->testObject->process($this->validArgs);
  }

}