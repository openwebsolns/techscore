<?php
/**
 * 2011-02-07: Allow for prepared fields, which are only good for
 * select statements. Introduced MySQLi_Field object and also the
 * escapedFields method.
 *
 * 2010-08-20: Prepared queries for MySQL. In this version, the
 * 'where' clauses need to be appropriate MyCond objects, such that
 * their value can too be escaped when creating the queries (plugs
 * possible SQL injection hole).
 *
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
class MySQLi_Query_Exception extends Exception {
  public function __construct($mes) {
    parent::__construct($mes);
  }
}

/**
 * The query object. Using the <code>query()</code> method or casting
 * as string will return a "valid" MySQL query. Valid here means that
 * the syntax is correct, even if it might contain a SCHEMA error.
 *
 * @author Dayan Paez
 * @version 2010-06-10
 */
class MySQLi_Query {

  /**
   * Query axes: the different things that can be done when querying
   */
  const SELECT = "select";
  const UPDATE = "update";
  const DELETE = "delete";
  const INSERT = "insert";
  const REPLACE= "replace";

  /**
   * @var Const the kind of query
   */
  private $axis;

  /**
   * @var MySQLi the connection for escaping strings
   */
  private $con;

  /**
   * @var Array the tables involved in the query. Along with fields,
   * this describes the fields to use
   */
  private $tables;
  private $fields;
  private $escapedFields;
  private $values;

  /**
   * @var Array the array to use for multiple inserts/replaces in one query
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
   * @throws MySQLi_Query_Exception if the query type is not supported
   */
  public function __construct(MySQLi $con, $axis = self::SELECT) {
    if (!in_array($axis, array(self::SELECT, self::UPDATE, self::DELETE, self::INSERT, self::REPLACE)))
      throw new MySQLi_Query_Exception("Unsupported query axis $axis.");
    $this->axis = $axis;
    $this->con  = $con;
    
    $this->tables  = array();
    $this->fields  = array();
    $this->escapedFields = array();
    $this->distinct = false;

    if ($axis == MySQLi_Query::INSERT || $axis == MySQLi_Query::REPLACE)
      $this->multipleValues = array();
  }

  /**
   * Returns the query as a string
   *
   * @return String the query
   */
  public function query() {
    switch ($this->axis) {
    case self::INSERT:
    case self::REPLACE:
      return $this->prepInsert();

    case self::DELETE:
      return $this->prepDelete();

    case self::UPDATE:
      return $this->prepUpdate();

    case self::SELECT:
      return $this->prepSelect();
    }
    return "";
  }

  /**
   * Returns the query as a string
   *
   * @return String the query
   * @see query()
   */
  public function __toString() { return $this->query(); }

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
   * @param String $alias the optional alias to use for the table
   */
  public function fields(Array $fields, $table, $alias = null) {
    $this->values($fields, array(), $table, $alias);
  }

  /**
   * Like 'fields', but the array must be MySQLi_Field objects, which
   * helps with the process of serializing the fields correctly
   *
   * @param Array<String> $fields the fields in that table
   * @param String $table the table to select from
   * @param String $alias the optional alias to use for teh table
   */
  public function escapedFields(Array $fields, $table, $alias = null) {
    $table = (string)$table;
    $alias = ($alias === null) ? $table : (string)$alias;

    $this->tables[$alias] = $table;
    $this->escapedFields[$alias] = $fields;
  }

  /**
   * The fields and values to set. This is appropriate for
   * INSERT/REPLACE/UPDATE calls. Note that if using the
   * <code>fields</code> method instead for update calls, then the
   * query call will result in a whole lot of <pre>NULL</pre>
   *
   * @param Array<String> $fields the fields in that table
   * @param Array<String> $values the corresponding values
   * @param String $table the table to select from
   * @param String $alias the optional alias to use for the table
   */
  public function values(Array $fields, Array $values, $table, $alias = null) {
    $table = (string)$table;
    $alias = ($alias === null) ? $table : (string)$alias;

    $this->tables[$alias] = $table;
    $this->fields[$alias] = $fields;
    $this->values[$alias] = $values;
  }

  /**
   * When INSERT/REPLACEing multiple items in one query---a desirable
   * effect when using InnoDB due to row-level locking---use this
   * method to queue the different set of values, one for each
   * row. Thus, issue multiple calls to this method, one for each set
   * to add.
   *
   * In such a case, you would first issue a call to 'fields' to
   * indicate which fields (and in which order) to insert/replace.
   * Then, issue as many calls to this method as necessary to get the
   * job done. Make sure to use the same $table (and $alias where
   * appropriate) when doing so.
   *
   * When using this method, DO NOT use 'values', which is meant for
   * single queries only.
   *
   * @param Array $values ONE set of values to queue
   * @param String $table the table name
   * @param String|null $alias the optional alias to use
   *
   * @see fields
   *
   * @throws MySQLi_Query_Exception if attempting to use this query
   * for non-insert/replace queries, or if $values does not match the
   * size of $fields.
   */
  public function multipleValues(Array $values, $table, $alias = null) {
    $table = (string)$table;
    $alias = ($alias === null) ? $table : (string)$alias;
    if ($this->multipleValues === null)
      throw new MySQLi_Query_Exception("multipleValues only applies to insert/replace queries.");
    if ($this->fields === null || !isset($this->fields[$alias]) ||
	count($values) != count($this->fields[$alias]))
      throw new MySQLi_Query_Exception("# of values differs from # of fields for given table ($alias)");
    
    if (!isset($this->multipleValues[$alias]))
      $this->multipleValues[$alias] = array();
    $this->multipleValues[$alias][] = $values;
  }

  /**
   * The expression to use in the where clause. Build this expression up, by
   * using either MyBoolean or MyExpression. This function is an alias of
   * where_and
   *
   * @param MyExpression $clause the expression to use in the where clause
   * @see where_and
   */
  public function where(MyExpression $clause = null) {
    $this->where_and($clause);
  }

  /**
   * AND combines the given clause with whatever clause already exists in this
   * query. You incur no penalty in calling this method when there is no
   * previous clause.
   *
   * @param MyExpression $clause the expression to add
   */
  public function where_and(MyExpression $clause = null) {
    if ($clause === null) return;
    if ($this->where === null)
      $this->where = $clause;
    else
      $this->where = new MyBoolean(array($this->where, $clause), MyBoolean::mAND);
  }

  /**
   * OR combines the given clause with whatever clause already exists in this
   * query. You incur no penalty in calling this method when there is no
   * previous clause.
   *
   * @param MyExpression $clause the expression to add
   */
  public function where_or(MyExpression $clause = null) {
    if ($clause === null) return;
    if ($this->where === null)
      $this->where = $clause;
    else
      $this->where = new MyBoolean(array($this->where, $clause), MyBoolean::mOR);
  }
  
  /**
   * Sets the "order by" clause to the given parameters in the given
   * order. This function supports variable number of arguments
   *
   * @param String $arg the order by axis
   * @param boolean $asc whether to sort ascending (default)
   */
  public function order_by($asc, $arg) {
    $args = func_get_args();
    $asc  = array_shift($args);
    $this->order = implode(", ", $args);
    if ($asc === false)
      $this->order .= " desc";
  }

  /**
   * Sets the limit, that is, writes to the query "limit $min, $max"
   *
   * @param int $min the minimum
   * @param int $max the maximum
   */
  public function limit($min, $max = null) {
    $this->limit = ($max === null) ?
      sprintf("limit %d", $min) :
      sprintf("limit %d, %d", $min, $max);
  }

  // ------------------------------------------------------------
  // Helper methods
  // ------------------------------------------------------------

  /**
   * Prepare select statement using the parameters
   *
   */
  protected function prepSelect() {
    // prep fields
    $fields = array();
    foreach ($this->fields as $alias => $list) {
      foreach ($list as $field) {
	$fields[] = sprintf("%s.%s", $alias, $field);
      }
    }
    foreach ($this->escapedFields as $alias => $list) {
      foreach ($list as $field) {
	$fields[] = $field->toSQL($alias);
      }
    }
    $fields = implode(", ", $fields);
    
    $tables = array();
    foreach ($this->tables as $alias => $table) {
      $tables[] = sprintf("%s as %s", $table, $alias);
    }
    $tables = implode(", ", $tables);

    $where = ($this->where === null) ? "" : "where ". $this->where->toSQL($this->con);
    $order = ($this->order === null) ? "" : "order by " . $this->order;
    $limit = ($this->limit === null) ? "" : $this->limit;
    $distc = ($this->distinct)       ? "distinct" : "";

    return $this->axis . " $distc $fields from $tables $where $order $limit";
  }

  /**
   * Prepare insert statement using the parameters. Note that this
   * method only applies to single tables
   *
   * @throws MySQLi_Query_Exception if multi-tables are detected
   */
  protected function prepInsert() {
    if (count($this->tables) != 1)
      throw new MySQLi_Query_Exception("Insert statements only support single table");

    $values = array_values($this->tables);
    $table = array_shift($values);
    $values = array_keys($this->tables);
    $alias = array_shift($values);

    $the_values = (isset($this->multipleValues[$alias])) ?
      $this->multipleValues[$alias] : array($this->values[$alias]);
    $list = $this->fields[$alias];
    $fields = array();
    foreach ($list as $i => $field)
      $fields[] = $field;

    // The following code used to use sprintf and implodes, but for
    // performance reason (such as when a column contains a large
    // amount of data), it has been rewritten to be built by
    // concatenatenation [Dayan Paez, Josiah Bradley].
    $v_i = 0;
    $values = '';
    foreach ($the_values as $instance) {
      if ($v_i++ > 0)
	$values .= ',';
      $s_i = 0;
      $values .= '(';
      foreach ($instance as $unit) {
	if ($s_i++ > 0)
	  $values .= ',';
	if ($unit === null)
	  $values .= 'NULL';
	else
	  $values .= '"' . $this->con->real_escape_string($unit) . '"';
      }
      $values .= ')';
    }
    return $this->axis . ' into ' . $table . ' (' . implode(',', $fields) . ') values ' . $values;
  }

  /**
   * Prepare update statement using the parameters. Like inserts, only
   * one table can be updated at a time.
   *
   * @see prepInsert
   * @throws MySQLi_Query_Exception if multi-tables are detected
   */
  protected function prepUpdate() {
    if (count($this->tables) != 1)
      throw new MySQLi_Query_Exception("Insert statements only support single table");

    $tables = array_values($this->tables);
    $table = array_shift($tables);
    unset($tables);

    $fields = '';
    $f_i = 0;
    foreach ($this->fields as $alias => $list) {
      foreach ($list as $i => $field) {
	if ($f_i++ > 0)
	  $fields .= ',';
	if (empty($this->values[$alias][$i]))
	  $fields .= ($field . '=NULL');
	else
	  $fields .= ($field . '="'. $this->con->real_escape_string($this->values[$alias][$i]) . '"');
      }
    }
    $where = ($this->where === null) ? new MyBoolean() : $this->where;
    if (($where = $where->toSQL($this->con)) === null)
      $where = "";
    else
      $where = "where ".$where;
    // $where = ($this->where === null) ? "" : "where " . $this->where->toSQL($this->con);
    $limit = ($this->limit === null) ? "" : $this->limit;

    return ($this->axis . ' ' . $table . ' set ' . $fields . ' ' . $where . ' ' . $limit);
  }

  /**
   * Prepare delete statement using the parameters. As before, only
   * one table can be updated at a time
   *
   * @see prepInsert
   * @thorws MySQLi_Query_Exception if multi-tables are detected
   */
  protected function prepDelete() {
    if (count($this->tables) != 1)
      throw new MySQLi_Query_Exception("Insert statements only support single table");

    $tables = array_values($this->tables);
    $table = array_shift($tables);
    unset($tables);
    
    $where = ($this->where === null) ? new MyBoolean() : $this->where;
    if (($where = $where->toSQL($this->con)) === null)
      $where = "";
    else
      $where = "where ".$where;
    // $where = ($this->where === null) ? "" : "where " . $this->where->toSQL($this->con);
    $limit = ($this->limit === null) ? "" : $this->limit;
    return sprintf("%s from %s %s $limit", $this->axis, $table, $where);
  }
}

/**
 * Parent class for expressions. This class is EMPTY, and is used only for
 * typehinting, etc.
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
abstract class MyExpression {
  /**
   * Formats this expression recursively, using the given MySQL connection to
   * escape characters if necessary.
   *
   * @param MySQLi $con the connection object to use
   */
  abstract public function toSQL(MySQLi $con);
}

/**
 * Boolean operator for one or more MyCond statements
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class MyBoolean extends MyExpression {
  const mAND = "and";
  const mOR = "or";

  private static $opers = array(self::mAND, self::mOR);

  protected $operator;
  protected $expressions;

  /**
   * Builds a boolean expression with the given type and expressions
   *
   * @param Const $type the type of boolean, either self::mAND, or self::mOR
   * @param Array<MyExpression> $children the children expressions
   * @throws MySQLi_Query_Exception when given invalid parameters
   */
  public function __construct(Array $children, $type = self::mAND) {
    if (!in_array($type, self::$opers)) throw new MySQLi_Query_Exception("Invalid boolean type $type.");
    $this->operator = $type;
    $this->expressions = array();
    foreach ($children as $c)
      $this->add($c);
  }

  /**
   * Appends the given $child expression to this boolean operation
   *
   * @param MyExpression $child the child expression to add. If null, adds nothing
   */
  public function add(MyExpression $child = null) {
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
    $sub = array();
    foreach ($this->expressions as $c)
      $sub[] = $c->toSQL($con);
    return "(".implode($this->operator, $sub).")";
  }
}

/**
 * A conditional statement, separated into 'field', 'conditional', 'value'. To
 * be used as feeder for where clauses.
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class MyCond extends MyExpression {
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
   * @throws MySQLi_Query_Exception should the operator be wrong or
   * the field is null
   */
  public function __construct($field, $value, $oper = MyCond::EQ) {
    if ($field == null) throw new MySQLi_Query_Exception("Field must not be null");
    $this->field = $field;
    $this->value = $value;
    if (!in_array($oper, self::$opers)) throw new MySQLi_Query_Exception("Invalid operator $oper.");
    $this->operator = $oper;
  }

  /**
   * Fetches the different elements "intelligently". For instance, if
   * $value is null, then $oper will return "is" for EQ and "is not"
   * otherwise.
   *
   * @param $param the parameter to fetch, one of "field", "value", or
   * "operator"
   * @throws MySQLi_Query_Exception when requesting an invalid parameter
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
      throw new MySQLi_Query_Exception("Invalid parameter requested $param.");
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
      $val  = '"'.$con->real_escape_string($this->value).'"';
    }
    return sprintf('(%s%s%s)', $this->field, $oper, $val);
  }
}

/**
 * Specific expression for MySQL's 'in' function, e.g.: 'field in ()'
 *
 * @author Dayan Paez
 * @version 2010-08-20
 */
class MyCondIn extends MyExpression {
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
   * @param String $field, as with MyCond
   * @param Array|MySQLi_Query $values the value to compare to. If an array,
   * then each element will be escaped. If a query, the query will be written
   * out.
   * @param Const $operator either IN (default) or NOT_IN
   * @throws MySQLi_Query_Exception when given ridiculous values
   */
  public function __construct($field, $values, $oper = MyCondIn::IN) {
    if ($field === null) throw new MySQLi_Query_Exception("Field cannot be null.");
    if (!is_array($values) && !($values instanceof MySQLi_Query))
      throw new MySQLi_Query_Exception("Value must be an array or a query.");

    if (!in_array($oper, self::$opers))
      throw new MySQLi_Query_Exception("Operator $oper not recognized.");

    $this->operator = $oper;
    $this->field = $field;
    $this->values = $values;
  }
  public function toSQL(MySQLi $con) {
    if (is_array($this->values)) {
      $val = "";
      foreach ($this->values as $v)
	$val .= ('"'.$con->real_escape_string($v).'"');
    }
    else
      $val = $this->values->query();
    return sprintf("(%s %s (%s))", $this->field, $this->operator, $val);
  }
}

/**
 * Field for a query
 *
 * @author Dayan Paez
 * @version 2011-02-07
 */
class MySQLi_Field {
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
   * $obj = new MySQLi_Field('date_time', 'year', 'year');
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
    $this->alias = ($alias === null) ? $this->field : $alias;
  }

  /**
   * Formats the field for the given table name
   *
   * @param String $table the table name for the field
   * @return String the query-safe version
   */
  public function toSQL($table) {
    if ($this->function === null)
      return sprintf('%s.%s as %s', $table, $this->field, $this->alias);
    return sprintf('%s(%s.%s) as %s', $this->function, $table, $this->field, $this->alias);
  }
}
?>