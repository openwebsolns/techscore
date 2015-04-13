<?php
namespace MyORM;

/**
 * A conditional statement, separated into 'field', 'conditional', 'value'. To
 * be used as feeder for where clauses.
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class DBCond extends DBExpression {
  const EQ = "=";
  const NE = "<>";
  const LT = "<";
  const LE = "<=";
  const GT = ">";
  const GE = ">=";
  const LIKE = " like ";
  const REGEXP = " regexp ";

  private static $opers = array(self::EQ, self::NE, self::LT, self::LE, self::GT, self::GE, self::LIKE, self::REGEXP);

  protected $field, $operator, $value;

  /**
   * The field, the value, and the operator. The operator should be
   * one of the class constants
   *
   * @param DBField|String $field the "field" in the database, can be a
   * function, such as 'concat(first_name, " ", last_name)'. This
   * value will NOT be escaped.
   * @param String|null $value the value. This field WILL BE escaped.
   * @param Const $oper the operator, defaults to 'EQ'
   * @throws DBQueryException should the operator be wrong or
   * the field is null
   */
  public function __construct($field, $value, $oper = DBCond::EQ) {
    if ($field == null) throw new DBQueryException("Field must not be null");
    $this->field = $field;
    $this->value =& $value;
    if (!in_array($oper, self::$opers)) throw new DBQueryException("Invalid operator $oper.");
    $this->operator = $oper;
  }

  /**
   * Fetches the different elements "intelligently". For instance, if
   * $value is null, then $oper will return "is" for EQ and "is not"
   * otherwise.
   *
   * @param $param the parameter to fetch, one of "field", "value", or
   * "operator"
   * @throws DBQueryException when requesting an invalid parameter
   */
  public function __get($param) {
    switch ($param) {
    case "field":
      return $this->field;
    case "value":
      return $this->value;
    case "operator":
      return $this->operator;
    default:
      throw new DBQueryException("Invalid parameter requested $param.");
    }
  }

  /**
   * Formats this object, escaping the values as needed.
   *
   * @param MySQLi $con the connection to use for escaping
   */
  public function toSQL(\MySQLi $con) {
    $oper = null;
    $val  = "NULL";
    if ($this->value === null) {
      if ($this->operator == self::EQ)
        $oper = " is ";
      else
        $oper = " is not ";
    }
    else {
      $oper = $this->operator;
      if ($this->value instanceof DBObject)
        $val = '"'.$con->real_escape_string($this->value->id).'"';
      elseif ($this->value instanceof \DateTime)
        $val = '"'.$this->value->format('Y-m-d H:i:s').'"';
      elseif ($this->value instanceof DBField)
        $val = $this->value->getName();
      else
        $val  = '"'.$con->real_escape_string($this->value).'"';
    }
    $field = $this->field;
    if ($field instanceof DBField)
      $field = $field->getName();
    return '(' . $field . $oper . $val . ')';
  }
}
?>