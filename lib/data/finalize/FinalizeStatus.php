<?php
namespace data\finalize;

use \InvalidArgumentException;

/**
 * Enum for possible finalize statuses.
 *
 * @author Dayan Paez
 * @version 2015-06-20
 */
class FinalizeStatus {
  const VALID = 'VALID';
  const ERROR = 'ERROR';
  const WARN  = 'WARN';

  private static $TYPES = array(self::VALID, self::ERROR, self::WARN);

  /**
   * @var String the message.
   */
  private $message;

  /**
   * Create a new status with given type and message.
   *
   * @param const $type one of the class constants
   * @param String message the associated message.
   */
  public function __construct($type = null, $message = null) {
    $this->setType($type);
    $this->setMessage($message);
  }

  /**
   * The kind of message.
   *
   * @param const $type the class constant.
   * @throws InvalidArgumentException if invalid type provided.
   */
  public function setType($type) {
    if ($type !== null && !in_array($type, self::$TYPES)) {
      throw new InvalidArgumentException("Invalid type provided: $type.");
    }
    $this->type = $type;
  }

  /**
   * The associated message.
   *
   * @param String anything that can be printed.
   * @throws InvalidArgumentException if non-string provided.
   */
  public function setMessage($message) {
    if ($message !== null && !is_string($message)) {
      throw new InvalidArgumentException("Message must be a string.");
    }
    $this->message = $message;
  }

  public function getType() {
    return $this->type;
  }

  public function getMessage() {
    return $this->message;
  }
}