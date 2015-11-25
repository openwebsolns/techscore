<?php
/*
 * This class is part of TechScore
 *
 * @author Dayan Paez
 * @version 2015-02-23
 */

use \scripts\AbstractScript;

/**
 * Contains the resources whose public pages to generate.
 *
 * @author Dayan Paez
 * @created 2015-02-23
 * @see scripts/GenerateByUrl
 */
class GeneratorArguments {

  private $generator;
  private $method = 'run';
  private $parameters = array();

  /**
   * Create a new set of arguments for an AbstractScript.
   *
   * @param AbstractScript $generator the initialized object to call.
   * @param Array $params the parameters to pass to the method.
   * @param String $method the method to run (default = 'run').
   */
  public function __construct(AbstractScript $generator, Array $params = array(), $method = 'run') {
    $this->setGenerator($generator);
    $this->setParameters($params);
    $this->setMethod($method);
  }

  public function getGenerator() {
    return $this->generator;
  }
  public function setGenerator(AbstractScript $generator) {
    $this->generator = $generator;
  }
  public function getMethod() {
    return $this->method;
  }
  public function setMethod($method) {
    $method = trim((string)$method);
    if ($method == '') {
      throw new InvalidArgumentException("Empty method provided: $method.");
    }
    $this->method = $method;
  }
  public function getParameters() {
    return $this->parameters;
  }
  public function setParameters(Array $parameters = array()) {
    $this->parameters = $parameters;
  }

  /**
   * Executes the generator's method, with arguments.
   *
   */
  public function execute() {
    call_user_func_array(
      array($this->generator, $this->method),
      $this->parameters
    );
  }
}
?>