<?php
namespace scripts;

use \AbstractUnitTester;

/**
 * Test the simple wrapper and its execution.
 *
 * @author Dayan Paez
 * @version 2015-11-24
 */
class GeneratorArgumentsTest extends AbstractUnitTester {

  public function testDelegation() {
    $script = new GeneratorArgumentsTestScript();
    $method = 'runMethod';
    $arg1 = "Argument1";
    $arg2 = "Argument2";
    $args = array($arg1, $arg2);

    $testObject = new GeneratorArguments(
      $script,
      $args,
      $method
    );

    $this->assertSame($script, $testObject->getGenerator());
    $this->assertEquals($method, $testObject->getMethod());
    $this->assertEquals($args, $testObject->getParameters());

    $testObject->execute();
    $calledArgs = $script->getCalledArgs();
    $this->assertEquals(1, count($calledArgs));
    $this->assertEquals($args, $calledArgs[0]);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testInvalidMethod() {
    $method = ' ';
    $testObject = new GeneratorArguments(
      new GeneratorArgumentsTestScript(),
      array(),
      $method
    );
  }

}

/**
 * Mock AbstractScript.
 */
class GeneratorArgumentsTestScript extends AbstractScript {

  private $args;

  public function __construct() {
    $this->args = array();
  }

  public function runMethod($arg1, $arg2) {
    $this->args[] = array($arg1, $arg2);
  }

  public function getCalledArgs() {
    return $this->args;
  }
}