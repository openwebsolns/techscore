<?php
namespace MyORM;

/**
 * Field for a query
 *
 * @author Dayan Paez
 * @version 2011-02-07
 */
class DBField {
  /**
   * @var String the function to use, leave null for no function
   */
  public $function;
  /**
   * @var String the table whose field this represents
   */
  public $table;
  /**
   * @var String the canonical name of the field
   */
  public $field;
  /**
   * @var String the optional alias for the field
   */
  public $alias;

  /**
   * Creates a new field with the given name and optional
   * function. For instance, if you want year(date_time) as year,
   *
   * <code>
   * $obj = new DBField('event', 'date_time', 'year', 'year');
   * </code>
   *
   * @param String $field the field to choose
   * @param String $func the function to use. Null for no function
   * @param String $alias the alias for the field (especially useful
   * with func
   */
  public function __construct($table, $field, $func = null, $alias = null) {
    $this->table = $table;
    $this->field = $field;
    $this->function = $func;
    $this->alias = $alias;
  }

  /**
   * How is this field known?
   *
   * Returns either the name of the field, or the alias
   *
   * @return String
   */
  public function getName() {
    if ($this->alias !== null)
      return $this->alias;
    return $this->table . '.' . $this->field;
  }

  /**
   * Formats the field for the given table name
   *
   * @return String the query-safe version
   */
  public function toSQL() {
    $a = ($this->alias !== null) ? " as {$this->alias}" : "";
    if ($this->function === null)
      return "{$this->table}.{$this->field}$a";
    return "{$this->function}({$this->table}.{$this->field})$a";
  }
}
?>