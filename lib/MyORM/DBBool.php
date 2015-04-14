<?php
namespace MyORM;

/**
 * Boolean operator for one or more DBCond statements
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class DBBool extends DBExpression {
  const mAND = "and";
  const mOR = "or";

  private static $opers = array(self::mAND, self::mOR);

  protected $operator;
  protected $expressions;

  /**
   * Builds a boolean expression with the given type and expressions
   *
   * @param Const $type the type of boolean, either self::mAND, or self::mOR
   * @param Array<DBExpression> $children the children expressions
   * @throws DBQueryException when given invalid parameters
   */
  public function __construct(Array $children, $type = self::mAND) {
    if (!in_array($type, self::$opers)) throw new DBQueryException("Invalid boolean type $type.");
    $this->operator = $type;
    $this->expressions = array();
    foreach ($children as $c)
      $this->add($c);
  }

  /**
   * Appends the given $child expression to this boolean operation
   *
   * @param DBExpression $child the child expression to add. If null, adds nothing
   */
  public function add(DBExpression $child = null) {
    if ($child !== null)
      $this->expressions[] = $child;
  }

  /**
   * Formats this expression recursively, using the given MySQL connection to
   * escape characters if necessary.
   *
   * @param MySQLi $con the connection object to use
   */
  public function toSQL(\MySQLi $con) {
    $txt = '(';
    foreach ($this->expressions as $i => $c) {
      if ($i > 0)
        $txt .= $this->operator;
      $txt .= $c->toSQL($con);
    }
    return $txt . ')';
  }
}
?>