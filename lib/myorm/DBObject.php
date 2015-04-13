<?php
namespace MyORM;

/**
 * This is the parent class of all objects which are to be serialized
 * and unserialized to an external database. For simple objects, this
 * class provides almost all the necessary mechanisms for
 * synchronizing to the database, purporting to behave as though the
 * object itself was local. However, the final synchronization to the
 * database must occur through an external tool, such as the DBI
 * class.
 *
 * @author Dayan Paez
 * @version 2010-06-10
 */
class DBObject {
  /**
   * @var mixed the id of the object
   */
  protected $id;
  private $dbi;
  private $dborder;
  private $dbcache;
  public function __construct() {}

  /**
   * Sets the DBConnection object to use for commits, etc.
   *
   */
  public function db_set_dbi(DBI $dbi = null) {
    $this->dbi = $dbi;
  }

  // ------------------------------------------------------------
  // Database-dependent parameters
  // ------------------------------------------------------------

  /**
   * By default, this method will return all the public and protected
   * fields of this class. Subclasses should override this method to
   * return only those fields which are also found in the database.
   *
   * @return Array the list of fields this object understands
   */
  public function db_fields() {
    if ($this->_db_fields === null) {
      $this->_db_fields = array();
      $class = new \ReflectionClass($this);
      foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC |
                                     \ReflectionProperty::IS_PROTECTED) as $field)
        $this->_db_fields[] = $field->name;
    }
    return $this->_db_fields;
  }
  /**
   * @var Array a cached list of fields for this object
   */
  private $_db_fields;

  /**
   * Fetches a list of fields to ignore when updating. Default is none
   *
   * @return Array the list of field values to ignore
   */
  public function db_update_ignore() {
    return array();
  }

  /**
   * Gets an (empty) object of the given field, presumably one of those
   * returned by <code>db_fields()</code>. The default is "string"
   *
   * This returned value can be one of the DBQuery A_* constants
   * (default = A_STR), or it can be one of:
   *
   *   - array (will be serialized by implode(\0)
   *   - DateTime
   *   - DBObject
   *   - object
   *
   * @param String $field the field name
   * @return mixed
   */
  public function db_type($field) {
    return DBQuery::A_STR;
  }

  /**
   * The list of values to search by. The default is to include all
   * the db_fields EXCEPT for the ID.
   *
   * @return Array the name of the searchable fields
   */
  public function db_filter() {
    $fields = array();
    foreach ($this->db_fields() as $field)
      if ($field != "id")
        $fields[] = $field;
    return $fields;
  }

  /**
   * Returns the database table name, defaults to lower case version
   * of classname
   *
   * @return String the database table name
   */
  public function db_name() { return strtolower(get_class($this)); }

  /**
   * Returns the native where clause, defaults to null
   *
   * @return String|null the where clause
   */
  public function db_where() { return null; }

  /**
   * Returns the default ordering, defaults to id
   *
   * @return Array array(id=>true);
   */
  protected function db_order() { return array("id"=>true); }
  /**
   * Resets the order-by for this class.
   *
   * Array should be an ordered map of field names => true/false. Use
   * true for ascending order in that particular field. If none
   * provided, this method will reset to the value in db_order
   *
   * @param Array $neworder the desired order or none for default
   * @see db_order
   */
  public function db_set_order(Array $neworder = array()) {
    if (count($neworder) == 0) {
      $this->dborder = $this->db_order();
      return;
    }
    $this->dborder = $neworder;
  }
  public function db_get_order() {
    if ($this->dborder === null)
      $this->dborder = $this->db_order();
    return $this->dborder;
  }

  /**
   * Maintain a copy of this object in memory once serialized.
   *
   * @return boolean true to cache, false otherwise (default)
   */
  protected function db_cache() { return false; }
  public function db_set_cache($val = false) {
    $this->dbcache = ($val !== false);
  }
  public function db_get_cache() {
    if ($this->dbcache === null)
      $this->dbcache = $this->db_cache();
    return $this->dbcache;
  }

  /**
   * Calls dbi->set.
   *
   * @throws BadFunctionCallException if not registered
   */
  public function db_commit(DBI $dbi = null, $update = "guess") {
    if ($dbi === null)
      $dbi = $this->dbi;
    if ($dbi === null)
      throw new \BadFunctionCallException("No DBI to commit.");
    return $dbi->set($this, $update);
  }

  /**
   * Basic type checking, using db_type
   *
   * @param String $name the name of the function
   * @param mixed $value the new value
   * @throws BadFunctionCallException if no such property exists or
   * the type is wrong
   */
  public function __set($name, $value) {
    if (!property_exists($this, $name))
      throw new \BadFunctionCallException(sprintf("Class %s does not have property %s.",
                                                  get_class($this), $name));

    if (in_array($name, $this->db_fields())) {
      $type = $this->db_type($name);
      if ($value !== null && is_object($type) && !($value instanceof $type)) {
        throw new \BadFunctionCallException(sprintf("Property %s in class %s must be of type %s",
                                                    $name, get_class($this), get_class($type)));
      }
    }
    $this->$name = $value;
  }

  public function &__get($name) {
    if (!property_exists($this, $name))
      throw new \BadFunctionCallException(sprintf("Class %s does not have property %s.",
                                                 get_class($this), $name));
    $type = $this->db_type($name);
    if ($this->$name === null)
      return $this->$name;
    if ($type instanceof DBObject && !($this->$name instanceof DBObject)) {
      if ($this->dbi === null)
        throw new \BadFunctionCallException(sprintf("This object (%s) is not registered with the database while trying to unserialize %s.", get_class($this), $name));
      $this->$name = $this->dbi->get($type, $this->$name);
    }
    elseif ($type instanceof DateTime && is_string($this->$name))
      $this->$name = new \DateTime($this->$name);
    elseif (is_array($type) && !is_array($this->$name))
      $this->$name = explode("\0", $this->$name);
    elseif (is_object($type) && is_string($this->$name))
      $this->$name = unserialize($this->$name);
    return $this->$name;
  }

  /**
   * Specifically ignore the $dbi property for this object.
   *
   * For cleanliness, only serialize the db_fields() + 'id'. Among
   * other things, this addresses PHP bug #33772. When overwriting this
   * method, subclasses should also ignore the $dbi property.
   *
   * @return Array the properties to serialize
   */
  public function __sleep() {
    $lst = $this->db_fields();
    $lst[] = 'id';
    return $lst;
  }
}
?>