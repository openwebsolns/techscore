<?php
/**
 * @author Dayan Paez
 * @version 2010-06-10
 * @package mysql
 */

/**
 * Query exceptions unique to the creation of queries
 *
 * @author Dayan Paez
 * @version 2010-06-11
 */
class DBQueryException extends Exception {
  public function __construct($mes) {
    parent::__construct($mes);
  }
}

/**
 * The query object. Using the <code>query()</code> method or casting
 * as string will return a "valid" MySQL query. Valid here means that
 * the syntax is correct, even if it might contain a SCHEMA error.
 *
 * For optimal results, this class will use prepared statements when
 * inserting, and the "old-fashioned" way for other operations.
 *
 * @author Dayan Paez
 * @version 2010-06-10
 */
class DBQuery {

  /**
   * Query axes: the different things that can be done when querying
   */
  const SELECT = "select";
  const UPDATE = "update";
  const DELETE = "delete";
  const INSERT = "insert";

  /**
   * Attribute archetypes
   */
  const A_INT = 'd';
  const A_STR = 's';
  const A_FLOAT = 'f';
  const A_BLOB = 'b';

  /**
   * @var Const the kind of query
   */
  private $axis;

  /**
   * @var MySQLi the connection for escaping strings
   */
  private $con;

  /**
   * @var String the table involved in the query. Along with fields,
   * this describes the fields to use
   */
  private $table;
  private $types;
  private $fields;
  private $values;

  /**
   * @var Array the array to use for multiple inserts in one query
   */
  private $multipleValues;

  private $where;
  private $order;
  private $limit;
  private $distinct;

  /**
   * Creates a new query which selects objects by default
   *
   * @param Const $axis the selection axis, or "query type"
   * @throws DBQueryException if the query type is not supported
   */
  public function __construct(MySQLi $con, $axis = self::SELECT) {
    if (!in_array($axis, array(self::SELECT, self::UPDATE, self::DELETE, self::INSERT)))
      throw new DBQueryException("Unsupported query axis $axis.");
    $this->axis = $axis;
    $this->con  = $con;

    $this->table = null;
    $this->fields = array();
    $this->distinct = false;

    if ($axis == DBQuery::INSERT)
      $this->multipleValues = array();
  }

  /**
   * Executes the query, returning a MySQLi_Result object, or null.
   *
   * @return MySQLi_Result|null the query result or null
   */
  public function query() {
    if ($this->axis == self::INSERT) {
      $stmt = $this->con->prepare($this->toSQL());
      if ($stmt === false)
        throw new DBQueryException("Malformed SQL: " . $this->toSQL());
      $args = $this->bindArgs();
      if (count($args) > 1) {
        if (strlen($args[0]) != count($args) - 1) {
          throw new DBQueryException("bindArgs returned wrong argument count.");
        }
        call_user_func_array(array($stmt, 'bind_param'), $args);
      }
      for ($i = 0; $i < 15; $i++) {
        $stmt->execute();
        if ($stmt->errno == 0) {
          $stmt->close();
          return null;
        }
        if ($stmt->errno != 1205 && $stmt->errno != 1213) {
          throw new DBQueryException("MySQL error " . $stmt->errno . " (" . $this->toSQL() . "): " . $stmt->error);
        }
      }
      $stmt->close();
      throw new DBQueryException("Exceeded number of attempts (5) for query (" . $this->toSQL() . ")");
    }

    // old fashioned way
    for ($i = 0; $i < 15; $i++) {
      $res = $this->con->query($this->toSQL());
      if ($this->con->errno == 0)
        return $res;
      if ($this->con->errno != 1205 && $stmt->errno != 1213)
        throw new DBQueryException("MySQL error " . $this->con->errno . " (" . $this->toSQL() . "): " . $this->con->error);
    }
    throw new DBQueryException("Exceeded number of attempts (5) for query (" . $this->toSQL() . ")");
  }

  /**
   * Fetches the template for the prepared statement
   *
   * @return String the query
   */
  public function toSQL() {
    switch ($this->axis) {
    case self::INSERT:
      return $this->prepInsert();

    case self::DELETE:
      return $this->prepDelete();

    case self::UPDATE:
      return $this->prepUpdate();

    case self::SELECT:
      return $this->prepSelect();
    }
    throw new RuntimeException("Unsupported axis {$this->axis}.");
  }


  public function bindArgs() {
    if ($this->axis != self::INSERT)
      throw new DBQueryException("bindArgs is only available for INSERT queries.");
    $args = array("");
    if ($this->multipleValues === null)
      $this->multipleValues = array();
    if (count($this->values) > 0)
      $this->multipleValues = array($this->values);
    for ($i = 0; $i < count($this->multipleValues); $i++) {
      for ($j = 0; $j < count($this->multipleValues[$i]); $j++) {
        $args[0] .= $this->types[$j];
        $args[] =& $this->multipleValues[$i][$j];
      }
    }
    return $args;
  }

  /**
   * Sets whether to fetch distinct rows
   *
   * @param boolean $flag
   */
  public function distinct($flag) {
    $this->distinct = ($flag !== false);
  }

  /**
   * The fields to select. This is not appropriate for DELETE
   * queries; instead leave the $fields argument as an empty array
   *
   * @param Array<String> $fields the fields in that table
   * @param String $table the table to select from
   */
  public function fields(Array $fields, $table) {
    $ar = array();
    $this->values($fields, array(), $ar, $table);
  }

  /**
   * The fields and values to set. This is appropriate for
   * INSERT/UPDATE calls. Note that if using the
   * <code>fields</code> method instead for update calls, then the
   * query call will result in a whole lot of <pre>NULL</pre>
   *
   * @param Array|String $fields the fields in that table
   * @param Array|Const $types list of class constatns with the data
   * type of the corresponding value
   * @param Array|mixed $values the corresponding values
   * @param String $table the table to select from
   */
  public function values(Array $fields, Array $types, Array $values, $table) {
    if (count($types) != count($values))
      throw new DBException("Array of types and values must be of the same size.");
    $this->table = (string)$table;
    $this->fields = array();
    foreach ($fields as $i => $field) {
      if ($field instanceof DBField)
        $this->fields[] = $field;
      else
        $this->fields[] = new DBField($field);
    }
    $this->types = $types;
    $this->values = $values;
  }

  /**
   * When INSERTing multiple items in one query---a desirable
   * effect when using InnoDB due to row-level locking---use this
   * method to queue the different set of values, one for each
   * row. Thus, issue multiple calls to this method, one for each set
   * to add.
   *
   * In such a case, you would first issue a call to 'fields' to
   * indicate which fields (and in which order) to insert.
   * Then, issue as many calls to this method as necessary to get the
   * job done. Make sure to use the same $table (and $alias where
   * appropriate) when doing so.
   *
   * When using this method, DO NOT use 'values', which is meant for
   * single queries only.
   *
   * @param Array $types  ONE set of types (must match)
   * @param Array $values ONE set of values to queue
   * @param String $table the table name
   * @param String|null $alias the optional alias to use
   *
   * @see fields
   *
   * @throws DBQueryException if attempting to use this query
   * for non-insert queries, or if $values does not match the
   * size of $fields.
   */
  public function multipleValues(Array $types, Array $values, $table, $alias = null) {
    if ($this->multipleValues === null)
      throw new DBQueryException("multipleValues only applies to insert queries.");

    if ($this->fields === null ||
        count($values) != count($this->fields) ||
        count($types)  != count($this->fields))
      throw new DBQueryException("# of types/values differs from # of fields for given table ($alias)");
    $this->multipleValues[] = $values;
    $this->types = $types;
  }

  /**
   * The expression to use in the where clause. Build this expression up, by
   * using either DBBool or DBExpression. This function is an alias of
   * where_and
   *
   * @param DBExpression $clause the expression to use in the where clause
   * @see where_and
   */
  public function where(DBExpression $clause = null) {
    $this->where_and($clause);
  }

  /**
   * AND combines the given clause with whatever clause already exists in this
   * query. You incur no penalty in calling this method when there is no
   * previous clause.
   *
   * @param DBExpression $clause the expression to add
   */
  public function where_and(DBExpression $clause = null) {
    if ($clause === null) return;
    if ($this->where === null)
      $this->where = $clause;
    else
      $this->where = new DBBool(array($this->where, $clause), DBBool::mAND);
  }

  /**
   * OR combines the given clause with whatever clause already exists in this
   * query. You incur no penalty in calling this method when there is no
   * previous clause.
   *
   * @param DBExpression $clause the expression to add
   */
  public function where_or(DBExpression $clause = null) {
    if ($clause === null) return;
    if ($this->where === null)
      $this->where = $clause;
    else
      $this->where = new DBBool(array($this->where, $clause), DBBool::mOR);
  }

  /**
   * Sets the "order by" clause to the given parameters in the given
   * order.
   *
   * @param Array $map where keys are fields and values are true/false
   * for ascending or descending.
   */
  public function order_by(Array $args) {
    $this->order = '';
    $i = 0;
    foreach ($args as $key => $asc) {
      if ($i++ > 0)
        $this->order .= ',';
      $this->order .= $key;
      if ($asc === false)
        $this->order .= ' desc';
    }
  }

  /**
   * Sets the limit, that is, writes to the query "limit $min, $max"
   *
   * @param int $min the minimum
   * @param int $max the maximum
   */
  public function limit($min, $max = null) {
    $this->limit = ($max === null) ?
      "limit " . (int)$min :
      "limit " . (int)$min . "," . (int)$max;
  }

  // ------------------------------------------------------------
  // Helper methods
  // ------------------------------------------------------------

  /**
   * Prepare select statement using the parameters
   *
   */
  protected function prepSelect() {
    $stmt = 'select ';
    if ($this->distinct)
      $stmt .= 'distinct ';
    foreach ($this->fields as $i => $f) {
      if ($i > 0)
        $stmt .= ',';
      $stmt .= $f->toSQL($this->table);
    }
    $stmt .= " from {$this->table}";
    if ($this->where !== null)
      $stmt .= " where" . $this->where->toSQL($this->con);
    if ($this->order !== null)
      $stmt .= " order by {$this->order}";
    if ($this->limit !== null)
      $stmt .= " {$this->limit}";

    return $stmt;
  }

  /**
   * Prepare insert statement using the parameters. Note that this
   * method only applies to single tables
   *
   * @throws DBQueryException if multi-tables are detected
   */
  protected function prepInsert() {
    $stmt = "insert into {$this->table} (";
    $hldr = '';
    foreach ($this->fields as $i => $f) {
      if ($i > 0) {
        $stmt .= ',';
        $hldr .= ',';
      }
      $stmt .= $f->toSQL();
      $hldr .= '?';
    }
    $stmt .= ")values($hldr)";
    for ($i = 1; $i < count($this->multipleValues); $i++)
      $stmt .= ",($hldr)";
    return $stmt;
  }

  /**
   * Prepare update statement using the parameters. Like inserts, only
   * one table can be updated at a time.
   *
   * @see prepInsert
   * @throws DBQueryException if multi-tables are detected
   */
  protected function prepUpdate() {
    $stmt = "update {$this->table} set ";
    foreach ($this->fields as $i => $f) {
      if ($i > 0)
        $stmt .= ',';
      $stmt .= $f->toSQL().'=';
      if ($this->values[$i] === null)
        $stmt .= 'NULL';
      else
        $stmt .= '"'.$this->con->real_escape_string($this->values[$i]).'"';
    }
    if ($this->where !== null)
      $stmt .= ' where '.$this->where->toSQL($this->con);
    if ($this->limit !== null)
      $stmt .= " {$this->limit}";
    return $stmt;
  }

  /**
   * Prepare delete statement using the parameters. As before, only
   * one table can be updated at a time
   *
   * @see prepInsert
   * @thorws DBQueryException if multi-tables are detected
   */
  protected function prepDelete() {
    $stmt = "delete from {$this->table}";
    if ($this->where !== null)
      $stmt .= ' where '.$this->where->toSQL($this->con);
    if ($this->limit !== null)
      $stmt .= " {$this->limit}";
    return $stmt;
  }
}

/**
 * Parent class for expressions. This class is EMPTY, and is used only for
 * typehinting, etc.
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
abstract class DBExpression {
  /**
   * Formats this expression recursively
   *
   * @return String with question mark place holders
   */
  abstract public function toSQL(MySQLi $con);
}

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
  public function toSQL(MySQLi $con) {
    $txt = '(';
    foreach ($this->expressions as $i => $c) {
      if ($i > 0)
        $txt .= $this->operator;
      $txt .= $c->toSQL($con);
    }
    return $txt . ')';
  }
}

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

  private static $opers = array(self::EQ, self::NE, self::LT, self::LE, self::GT, self::GE, self::LIKE);

  protected $field, $operator, $value;

  /**
   * The field, the value, and the operator. The operator should be
   * one of the class constants
   *
   * @param String $field the "field" in the database, can be a
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
  public function toSQL(MySQLi $con) {
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
      elseif ($this->value instanceof DateTime)
        $val = '"'.$this->value->format('Y-m-d H:i:s').'"';
      else
        $val  = '"'.$con->real_escape_string($this->value).'"';
    }
    return '(' . $this->field . $oper . $val . ')';
  }
}

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
   * @param String $field, as with DBCond
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
  public function toSQL(MySQLi $con) {
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
    return "({$this->field} {$this->operator} ($val))";
  }
}

/**
 * Field for a query
 *
 * @author Dayan Paez
 * @version 2011-02-07
 */
class DBField {
  /**
   * @var const the function to use, leave null for no function
   */
  public $function;
  public $field;
  public $alias;

  /**
   * Creates a new field with the given name and optional
   * function. For instance, if you want year(date_time),
   *
   * <code>
   * $obj = new DBField('date_time', 'year', 'year');
   * </code>
   *
   * @param String $field the field to choose
   * @param String $func the function to use. Null for no function
   * @param String $alias the alias for the field (especially useful
   * with func
   */
  public function __construct($field, $func = null, $alias = null) {
    $this->field = $field;
    $this->function = $func;
    $this->alias = $alias;
  }

  /**
   * Formats the field for the given table name
   *
   * @param String $table|null the table name for the field
   * @return String the query-safe version
   */
  public function toSQL($table = null) {
    $t = ($table !== null) ? "$table." : "";
    $a = ($this->alias !== null) ? " as {$this->alias}" : "";
    if ($this->function === null)
      return "{$t}{$this->field}$a";
    return "{$this->function}({$t}{$this->field})$a";
  }
}
?>
