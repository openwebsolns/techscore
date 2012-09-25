<?php
/**
 * From Wikipedia: "Soter [...] spirit of safety, preservation and deliverance from harm
 *
 * This is the long awaited Validation library that Josiah has been
 * desiring for awhile. Unlike other libraries, this one can be
 * subclassed for greater precision control.
 * 
 * @author Dayan Paez
 * @created 2011-05-17
 * @package validation
 */

/**
 * Exceptions during validation
 *
 * @author Dayan Paez
 * @version 2011-05-17
 * @package validation
 */
class SoterException extends Exception {
  const INTEGER = 1;
  const INTEGER_OUT_OF_BOUNDS = 2;
  const FLOAT = 4;
  const FLOAT_OUT_OF_BOUNDS = 8;
  const NO_KEY = 16;
  const NO_VALUE = 32;
  const STRING = 64;
  const STRING_OUT_OF_BOUNDS = 128;
  const DBOBJECT = 256;
  const DBOBJECT_NULL = 512;
  const NO_LIST = 1024;
  const LIST_SIZE = 2048;
  const DATE = 4096;
  const DATE_OUT_OF_BOUNDS = 8192;
  const FILE = 16384;
  const FILE_OUT_OF_BOUNDS = 32768;
  const NO_FILE = 65536;
  const REGEX = 131072;
}

/**
 * The great validator
 *
 * @author Dayan Paez
 * @create 2011-05-17
 * @package validation
 */
class Soter {

  private $dbm = 'DBM';

  /**
   * Creates a new validator
   *
   */
  public function __construct() {}

  /**
   * Set the DBM (sub)class to use for validating against a
   * database. Because of the static nature of the DBM class, this is
   * merely the name of the class to use when serializing.
   *
   * @param String $name the name of the class
   * @see reqDBM
   * @see incDBM
   * @see hasDBM
   * @throws InvalidArgumentException if no such class exists
   */
  final public function setDBM($name) {
    if (!class_exists($name, true))
      throw new InvalidArgumentException("No such class $name.");
    $this->dbm = $name;
  }

  /**
   * Requires that an integer be present as key $key in array $arg
   *
   * @param Array $args the array to check for int, such as $_POST
   * @param String $key the key that should be present
   * @param int $min the minimum allowed value (inclusive)
   * @param int $max the maximum allowed value (exclusive)
   * @param String $mes the error message to throw upon failure
   * @return int the (truncated) value
   * @throws SoterException
   */
  final public function reqInt(Array $args, $key, $min = 0, $max = PHP_INT_MAX, $mes = "GSE") {
    if (!isset($args[$key]) || !is_numeric($args[$key]))
      throw new SoterException($mes, SoterException::INTEGER);
    $val = (int)$args[$key];
    if ($val < $min || $val >= $max)
      throw new SoterException($mes, SoterException::INTEGER_OUT_OF_BOUNDS);
    return $val;
  }

  /**
   * Requires that a float be present as key $key in array $arg
   *
   * @param Array $args the array to check for float, such as $_POST
   * @param String $key the key that should be present
   * @param int $min the minimum allowed value (inclusive)
   * @param int $max the maximum allowed value (inclusive)
   * @param String $mes the error message to throw upon failure
   * @return float the value
   * @throws SoterException
   */
  final public function reqFloat(Array $args, $key, $min = 0, $max = PHP_INT_MAX, $mes = "GSE") {
    if (!isset($args[$key]) || !is_numeric($args[$key]))
      throw new SoterException($mes, SoterException::FLOAT);
    $val = $args[$key];
    if ($val < $min || $val > $max)
      throw new SoterException($mes, SoterException::FLOAT_OUT_OF_BOUNDS);
    return $val;
  }

  /**
   * Requires that the given $key be contained in the keys to the
   * array given, i.e. in_array($args[$key], array_keys($values))
   *
   * @see reqValue
   * @param Array $args the array to check
   * @param String $key the key
   * @param Array $values the associative array of values
   * @param String $mes the error message to throw
   * @return String $key the key
   * @throws SoterException
   */
  final public function reqKey(Array $args, $key, Array $values, $mes = "GSE") {
    if (!isset($args[$key]) || !isset($values[$args[$key]]))
      throw new SoterException($mes, SoterException::NO_KEY);
    return $args[$key];
  }

  /**
   * Requires that the value in $args[$key] be in the array $values
   *
   * @see reqKey
   * @param Array $args the array to check
   * @param String $key the key
   * @param Array $values the associative array of values
   * @param String $mes the error message to throw
   * @return String $key the key
   * @throws SoterException
   */
  final public function reqValue(Array $args, $key, Array $values, $mes = "GSE") {
    if (!isset($args[$key]) || !in_array($args[$key], $values))
      throw new SoterException($mes, SoterException::NO_VALUE);
    return $args[$key];
  }

  /**
   * Requires a string be present in $args[$key], with a minimum
   * length and a maximum length as given (after trimming)
   *
   * @param Array $args the array
   * @param String $key the key in the array that contains string
   * @param int $min the minimum size (inclusive)
   * @param int $max the maximum size (exclusive)
   * @param String $mes the error message to throw
   * @return String the value
   * @throws SoterException
   */
  final public function reqString(Array $args, $key, $min = 0, $max = 8388608, $mes = "GSE") {
    return self::reqRaw($args, $key, $min, $max, $mes, array('trim'));
  }

  /**
   * Sometimes, we do not want to trim the string input, such as when
   * it is being fed to the DPEditor. For such an occassion, use this
   * method. It is actually a generic form of the reqString above.
   *
   * @param Array $args the array
   * @param String $key the key in the array that contains string
   * @param int $min the minimum size (inclusive)
   * @param int $max the maximum size (exclusive)
   * @param String $mes the error message to throw
   *
   * @param Array:callback $opers an array of callbacks to apply to
   * the string. These callbacks should take one parameter (a string)
   * and return the modified string.
   *
   * @return String the value
   * @throws SoterException
   */
  final public function reqRaw(Array $args, $key, $min = 0, $max = 8388608, $mes = "GSE", $opers = array()) {
    if (!isset($args[$key]) || !is_string($args[$key]))
      throw new SoterException($mes, SoterException::STRING);
    $val = $args[$key];
    foreach ($opers as $oper)
      $val = call_user_func($oper, $val);
    $len = strlen($val);
    if ($len < $min || $len >= $max)
      throw new SoterException($mes, SoterException::STRING_OUT_OF_BOUNDS);

    // Replace illegal M$ chars
    $search = array(chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133));
    $replace = array("'", "'", '"', '"', '-', '-', '.');

    return str_replace($search, $replace, $val);
  }

  /**
   * Requires that the value $args[$key] exist and be an ID of an
   * object of type $obj, which will be serialized by calling
   * DBM::get.  The DBM class to use is the one set with the setDBM
   * method.
   *
   * @see setDBM
   * @param Array $args the list to find the object ID
   * @param String $key the key in the $args where the ID is
   * @param DBObject $obj the type of object to serialize
   * @param String $mes the error message to provide
   * @return DBObject an object of the same type as passed
   * @throws SoterException
   */
  final public function reqID(Array $args, $key, DBObject $obj, $mes = "GSE") {
    if (!isset($args[$key]))
      throw new SoterException($mes, SoterException::DBOBJECT);
    $obj = call_user_func(array($this->dbm, 'get'), $obj, $args[$key]);
    if ($obj === null)
      throw new SoterException($mes, SoterException::DBOBJECT_NULL);
    return $obj;
  }

  /**
   * Requires that $args[$key] exist and that it be an array, with an
   * optional size requirement
   *
   * @param Array $args the list where to find the required list
   * @param String $key the key in $args where to find the list
   * @param int|null $size if greater than 0, the exact size
   * @param String $mes the error message to return
   * @return Array the resulting list
   * @throws SoterException
   */
  final public function reqList(Array $args, $key, $size = null, $mes = "GSE") {
    if (!isset($args[$key]) || !is_array($args[$key]))
      throw new SoterException($mes, SoterException::NO_LIST);
    if ($size > 0 && count($args[$key]) != $size)
      throw new SoterException($mes, SoterException::LIST_SIZE);
    return $args[$key];
  }

  /**
   * Requires that each of the keys in $keys be keys in $args, each of
   * which points to a list. All the lists must be of the same size.
   *
   * @param Array $args the list, like $_POST
   * @param Array $keys the list of keys to be present in $args
   * @param int|null $size the exact size requirement, if positive
   * @param String $mes the error message to spit out
   * @return Array:Array the map
   * @throws SoterException
   * @see reqList
   */
  final public function reqMap(Array $args, Array $keys, $size = null, $mes = "GSE") {
    $map = array();
    foreach ($keys as $key) {
      $map[$key] = $this->reqList($args, $key, $size, $mes);
      if ($size === null)
        $size = count($map[$key]);
    }
    return $map;
  }

  /**
   * Requires that $args[$key] exist and be a valid date, optionally
   * between a $min and $max value. If the date is not properly
   * formatted under $args[$key], a SoterException is thrown.
   *
   * @param Array $args where to look for the date
   * @param String $key the key in $args where the date is hiding
   * @param DateTime|null $min the minimum value (inclusive)
   * @param DateTime|null $max the maximum value (exclusive)
   * @param String $mes the error message to throw
   * @throws SoterException
   */
  final public function reqDate(Array $args, $key, DateTime $min = null, DateTime $max = null, $mes = "GSE") {
    if (!isset($args[$key]))
      throw new SoterException($mes, SoterException::DATE);
    try {
      $date = new DateTime($args[$key]);
    }
    catch (Exception $e) {
      throw new SoterException($mes, SoterException::DATE);
    }
    if (($min !== null && $date < $min) || ($max !== null && $date >= $max))
      throw new SoterException($mes, SoterException::DATE_OUT_OF_BOUNDS);
    return $date;
  }

  /**
   * Requires that $args[$key] be an array regarding an uploaded file,
   * such as one would find from $_FILES.
   *
   * @param Array $args the list of uploaded files: $_FILES would work
   * @param String $key the specific file one is searching
   * @param int $min the minimum size in bytes
   * @param int $max the maximum size in bytes
   * @param String $mes the error message
   * @return Array list with the necessary keys: 'tmp_name', etc.
   * @throw SoterException
   */
  final public function reqFile(Array $args, $key, $min = 0, $max = 8388608, $mes = "GSE") {
    if (!isset($args[$key]) || !is_array($args[$key]) || !isset($args[$key]['error']))
      throw new SoterException($mes, SoterException::FILE);
    if (!isset($args[$key]['size']) || $args[$key]['size'] < $min || $args[$key]['size'] >= $max)
      throw new SoterException($mes, SoterException::FILE_OUT_OF_BOUNDS);
    if ($args[$key]['error'] != 0)
      throw new SoterException($mes, SoterException::NO_FILE);
    return $args[$key];
  }

  /**
   * Requires that $args[$key] exists and satisfies the given regular
   * expression, which must work. If the regular expression does not
   * work, (preg_match returns false), a RuntimeException is thrown.
   *
   * @param Array $args the list in which to check for $key
   * @param String $key the key
   * @param String $regex the (well-formed) regular expression
   * @param String $mes the message of the exception upon failure
   * @return Array the matches of the regular expression. IE the
   * array filled in by PHP's preg_match
   * @throws SoterException
   */
  final public function reqRE(Array $args, $key, $regex, $mes = "GSE") {
    if (!isset($args[$key]))
      throw new SoterException($mes, SoterException::REGEX);
    $matches = array();
    $res = preg_match($regex, $args[$key], $matches);
    if ($res === false)
      throw new RuntimeException("Invalid regex provided $regex.");
    if ($res == 0)
      throw new SoterException($mes, SoterException::REGEX);
    return $matches;
  }

  // ------------------------------------------------------------
  // Include wrappers
  // ------------------------------------------------------------

  /**
   * Look for an integer value in $args[$key] and return it, or the
   * default value, if no valid one found
   *
   * @see reqInt
   */
  final public function incInt(Array $args, $key, $min = 0, $max = PHP_INT_MAX, $default = 0) {
    try {
      return $this->reqInt($args, $key, $min, $max);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incFloat(Array $args, $key, $min = 0, $max = PHP_INT_MAX, $default = 0.0) {
    try {
      return $this->reqFloat($args, $key, $min, $max);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incKey(Array $args, $key, Array $values, $default = null) {
    try {
      return $this->reqKey($args, $key, $values);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incValue(Array $args, $key, Array $values, $default = null) {
    try {
      return $this->reqValue($args, $key, $values);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incString(Array $args, $key, $min = 0, $max = 8388608, $default = null) {
    try {
      return $this->reqString($args, $key, $min, $max);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incRaw(Array $args, $key, $min = 0, $max = 8388608, $default = null, Array $opers = array()) {
    try {
      return $this->reqRaw($args, $key, $min, $max, "GSE", $opers);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incID(Array $args, $key, DBObject $obj, DBObject $default = null) {
    try {
      return $this->reqID($args, $key, $obj);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incList(Array $args, $key, $size = null, Array $default = array()) {
    try {
      return $this->reqList($args, $key, $size);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incMap(Array $args, Array $keys, $size = null, Array $default = array()) {
    try {
      return $this->reqMap($args, $keys, $size);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incDate(Array $args, $key, DateTime $min = null, DateTime $max = null, DateTime $default = null) {
    try {
      return $this->reqDate($args, $key, $min, $max);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incFile(Array $args, $key, $min = 0, $max = 8388608, $default = null) {
    try {
      return $this->reqFile($args, $key, $min, $max);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  final public function incRE(Array $args, $key, $regex, Array $default = array()) {
    try {
      return $this->reqRE($args, $key, $regex);
    }
    catch (SoterException $e) {
      return $default;
    }
  }

  // ------------------------------------------------------------
  // HAS wrappers
  // ------------------------------------------------------------

}
?>