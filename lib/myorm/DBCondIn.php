<?php
namespace MyORM;

/**
 * Specific expression for MySQL's 'in' function, e.g.: 'field in ()'
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class DBCondIn extends DBExpression {
  const IN = "in";
  const NOT_IN = "not in";

  private static $opers = array(self::IN, self::NOT_IN);
  protected $operator;
  protected $field;
  protected $values;

  /**
   * Creates a new 'in' condition, which can compare a string field to either an
   * array of values or a SQL query.
   *
   * @param String|DBField $field, as with DBCond
   * @param Array|DBQuery $values the value to compare to. If an array,
   * then each element will be escaped. If a query, the query will be written
   * out.
   * @param Const $operator either IN (default) or NOT_IN
   * @throws DBQueryException when given ridiculous values
   */
  public function __construct($field, $values, $oper = DBCondIn::IN) {
    if ($field === null) throw new DBQueryException("Field cannot be null.");
    if (!is_array($values) && !($values instanceof ArrayIterator) && !($values instanceof DBQuery))
      throw new DBQueryException("Value must be an array or a query.");

    if (!in_array($oper, self::$opers))
      throw new DBQueryException("Operator $oper not recognized.");

    $this->operator = $oper;
    $this->field = $field;
    $this->values =& $values;
  }
  public function toSQL(\MySQLi $con) {
    if (is_array($this->values) || $this->values instanceof ArrayIterator) {
      $val = "";
      $index = 0;
      foreach ($this->values as $v) {
        if ($index++ > 0)
          $val .= ',';
        if ($v instanceof DBObject)
          $v = $v->id;
        $val .= ('"'.$con->real_escape_string($v).'"');
      }
    }
    else
      $val = $this->values->toSQL();
    $field = $this->field;
    if ($field instanceof DBField)
      $field = $field->getName();
    return "({$field} {$this->operator} ($val))";
  }
}
?>