<?php
namespace MyORM;

/**
 * The query object.
 *
 * This class allows creation of SQL queries as objects, rather than
 * as strings, a boon to productivity and good code.
 *
 * Using the <code>query()</code> method or casting
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
   * @var Array:String the tables involved in the query. Along with $fields,
   * this describes the fields to use
   */
  private $tables;
  private $types;
  /**
   * @var Map:String:DBField an associative array indexed by table name
   */
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
  public function __construct(\MySQLi $con, $axis = self::SELECT) {
    if (!in_array($axis, array(self::SELECT, self::UPDATE, self::DELETE, self::INSERT)))
      throw new DBQueryException("Unsupported query axis $axis.");
    $this->axis = $axis;
    $this->con  = $con;

    $this->tables = array();
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
      for ($i = 0; $i < 5; $i++) {
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
    for ($i = 0; $i < 5; $i++) {
      $res = $this->con->query($this->toSQL());
      if ($this->con->errno == 0)
        return $res;
      if ($this->con->errno != 1205 && $this->con->errno != 1213)
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
    throw new \RuntimeException("Unsupported axis {$this->axis}.");
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
      throw new DBQueryException("Array of types and values must be of the same size.");
    if (!isset($this->tables[$table]))
      $this->tables[$table] = $table;
    $this->fields[$table] = array();
    foreach ($fields as $i => $field) {
      if ($field instanceof DBField) {
        $field->table = $table;
        $this->fields[$table][] = $field;
      }
      else
        $this->fields[$table][] = new DBField($table, $field);
    }
    $this->types = $types;
    $this->values = $values;
  }

  /**
   * Returns the total number of fields across all tables in this query
   *
   * @return int the field count
   */
  public function getFieldCount() {
    $cnt = 0;
    foreach ($this->fields as $fields)
      $cnt += count($fields);
    return $cnt;
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

    $num_fields = $this->getFieldCount();
    if ($num_fields == 0 || count($values) != $num_fields || count($types) != $num_fields)
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
    $i = 0;
    $t = 0;
    $tables = '';
    foreach ($this->fields as $table => $fields) {
      if ($t++ > 0)
        $tables .= ',';
      $tables .= $table;
      foreach ($fields as $f) {
        if ($i++ > 0)
          $stmt .= ',';
        $stmt .= $f->toSQL($table);
      }
    }
    $stmt .= ' from ' . $tables;
    if ($this->where !== null)
      $stmt .= ' where' . $this->where->toSQL($this->con);
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
    if (count($this->tables) != 1)
      throw new DBQueryException("Cannot only insert one table at a time.");
    $stmt = 'insert into ';
    foreach ($this->tables as $t) {
      $stmt .= $t;
      break;
    }
    $stmt .= ' (';
    $hldr = '';
    foreach ($this->fields[$t] as $i => $f) {
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
    $stmt = 'update ';
    $t = 0;
    foreach ($this->tables as $table) {
      if ($t++ > 0)
        $stmt .= ',';
      $stmt .= $table;
    }
    $stmt .= ' set ';
    $i = 0;
    foreach ($this->fields as $table => $fields) {
      foreach ($fields as $f) {
        if ($i > 0)
          $stmt .= ',';
        $stmt .= $f->toSQL($table).'=';
        if ($this->values[$i] === null)
          $stmt .= 'NULL';
        elseif ($this->values[$i] instanceof DBField)
          $stmt .= $this->values[$i]->toSQL();
        else
          $stmt .= '"'.$this->con->real_escape_string($this->values[$i]).'"';
        $i++;
      }
    }
    if ($this->where !== null)
      $stmt .= ' where '.$this->where->toSQL($this->con);
    if ($this->limit !== null)
      $stmt .= ' ' . $this->limit;
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
    $stmt = 'delete from ';
    $t = 0;
    foreach ($this->tables as $table) {
      if ($t++ > 0)
        $stmt .= ',';
      $stmt .= $table;
    }
    if ($this->where !== null)
      $stmt .= ' where '.$this->where->toSQL($this->con);
    if ($this->limit !== null)
      $stmt .= ' '.$this->limit;
    return $stmt;
  }
}
?>
