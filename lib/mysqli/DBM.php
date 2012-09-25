<?php
/**
 * DBM and DBObject provide a centralized abstraction for serializing
 * and deserializing objects to a database. DBM is the (M)aster class
 * which handles the querying of the database (in this case MySQL),
 * and provides static methods for handling the data.
 *
 * DBM handles DBObject's. Please see the tutorial for more information.
 *
 * @author Dayan Paez
 * @version 2011-12-07
 * @package mysql
 */

require_once(dirname(__FILE__).'/DBQuery.php');
require_once(dirname(__FILE__).'/DBDelegate.php');

/**
 * Database serialization errors
 *
 * @author Dayan Paez
 * @version 2010-06-11
 */
class DatabaseException extends Exception {
  public function __construct($mes = "") {
    parent::__construct($mes);
  }
}

/**
 * Manages all the connections to the database and provides for basic
 * methods of data serialization.
 *
 * @author Dayan Paez
 * @version 2010-06-14
 */
class DBM {
  /**
   * @var String connection parameters
   */
  private static $__con_host;
  private static $__con_user;
  private static $__con_pass;
  private static $__con_name;

  /**
   * @var MySQLi connection
   */
  private static $__con;

  /**
   * @var String path to query log, NULL for no logging
   */
  private static $log_path = null;

  /**
   * @var Array the list of deserialized objects, indexed by {table
   * name}_{id}.
   */
  protected static $objs = array();

  /**
   * Retrieves the database connection
   *
   * @return MySQLi connection
   */
  public static function connection() {
    if (self::$__con === null && self::$__con_host !== null) {
      self::$__con = new MySQLi(self::$__con_host,
                                self::$__con_user,
                                self::$__con_pass,
                                self::$__con_name);
      self::$__con->set_charset('utf8');
    }
    return self::$__con;
  }

  /**
   * Sets the connection to use. Consider using setConnectionParams to
   * delay opening the MySQL connection until absolutely necessary.
   *
   * @param MySQLi $con the working connection
   */
  public static function setConnection(MySQLi $con) {
    self::$__con = $con;
  }

  /**
   * Sets the parameters to use when creating a MySQLi connection, but
   * does not open that connection until necessary.
   *
   * @version 2011-10-10: This method will "reset" the connection; it
   * will close whatever open connection is already open.
   *
   * @param String $host the hostname
   * @param String $user the username
   * @param String $pass the password
   * @param String $name the name of the databse
   */
  public static function setConnectionParams($host, $user, $pass, $name) {
    self::$__con_host = $host;
    self::$__con_user = $user;
    self::$__con_pass = $pass;
    self::$__con_name = $name;

    if (self::$__con !== null) {
      self::$__con->close();
      self::$__con = null;
    }
  }

  /**
   * Sets whether or not to log queries. Use NULL for no logging
   *
   * @param String $path the path to the log file, use null to turn off logging
   */
  public static function setLogfile($path = null) {
    self::$log_path = $path;
  }

  /**
   * Sends a query to the database
   *
   * @param DBQuery $q the query to send
   * @return DBResult the result
   * @throws BadFunctionCallException if the query reveals errors
   */
  public static function query(DBQuery $q) {
    $res = $q->query();
    if (self::$log_path !== null)
      @error_log($q->toSQL(). "\n", 3, self::$log_path);
    return $res;
  }

  // ------------------------------------------------------------
  // Generic database object serialization
  // ------------------------------------------------------------

  /**
   * Returns an empty DBQuery of the given type
   *
   * @param DBQuery::Const $type the argument to the DBQuery constructor
   * @return DBQuery bound to this object's connection
   */
  public static function createQuery($type = DBQuery::SELECT) {
    return new DBQuery(self::connection(), $type);
  }

  /**
   * Retrieves the object with the given ID
   *
   * @param DBObject $obj the object type
   * @param String|int $id the id of the object
   * @return mixed|null the matching object, or null if not found
   */
  public static function get(DBObject $obj, $id) {
    $id = (string)$id; // make sure NEVER to issue 'id is null'
    $c = get_class($obj);
    if ($obj->db_get_cache() && $id !== null) {
      $i = $c.'_'.$id;
      if (!isset(self::$objs[$i])) {
        $r = self::query(self::prepGet($obj, $id));
        self::$objs[$i] = ($r->num_rows == 0) ? null :
          $r->fetch_object($c);
        $r->free();
      }
      return self::$objs[$i];
    }
    $r = self::query(self::prepGet($obj, $id));
    if ($r->num_rows == 0) {
      $r->free();
      return null;
    }
    $b = $r->fetch_object(get_class($obj));
    $r->free();
    return $b;
  }

  /**
   * Commits the changes associated with this object to the
   * database: whether that be inserting or updating a new record.
   *
   * @param DBObject $obj the object to set
   *
   * @param boolean|String $update update $obj using $update's ID.
   * This is useful only when you are certain that you want an update
   * and you do not wish to incur the slight overhead of checking
   * that $obj already exists in the database (a step taken in this
   * method in order to draft the correct request between update and
   * insert).
   *
   * Three different values are possible for this parameter. If
   * STRICTLY true, this method will DRAFT AN UPDATE REQUEST,
   * REGARDLESS OF WHETHER OR NOT THE OBJECT ALREADY EXISTS. This is
   * important to understand because it might throw a database
   * exception if the primary key fails to exist. If $update is
   * STRICTLY False, then this method will blindly attempt an INSERT
   * statement. Note that this may cause a database exception if there
   * is a primary key failure. Thus, only specify true or false if you
   * are absolutely sure that an insert is required. For any other
   * values, such as the default "guess" value, this method will first
   * check if an object with the same ID as $obj already exists in the
   * database, and update or insert accordingly.
   *
   * This second, slightly problematic, parameter was added purely for
   * the sake of efficiency, and to be used by any recursive calls
   * made by this method itself. For instance, if $obj contains an
   * attribute that is also a DBObject, this method will check if that
   * attribute object exists already. If it does not, then it will
   * recursively call itself with that new DBObject and 'false' as the
   * second parameter, before proceeding to create $obj's query with
   * the newly inserted attribute object ID. However, if it determines
   * that the attribute DBObject already exists, then it will not
   * bother updating it. This prevents a memory-intensive cascade of
   * update requests of the same DBObject. This means that if you wish
   * to make sure an attribute object is indeed committed to the
   * database, you would best set that attribute yourself with a
   * separate call to this method. In conclusion, client scripts
   * should stick to calling this method with only the first argument.
   *
   * @see get
   * @version 2010-12-02: Added second parameter
   */
  public static function set(DBObject $obj, $update = "guess") {
    self::query(self::prepSet($obj, $update));

    // Update ID if necessary
    if ($obj->id === null)
      $obj->id = self::connection()->insert_id;
  }

  public static function prepSet(DBObject $obj, $update = "guess") {
    if ($update === true) {
      $q = self::createQuery(DBQuery::UPDATE);
      $q->where(new DBCond("id", $obj->id));
    }
    elseif ($update === false || $obj->id === null) {
      $q = self::createQuery(DBQuery::INSERT);
    }
    else {
      // guess?
      $exist = self::get($obj, $obj->id);
      return self::prepSet($obj, ($exist instanceof DBObject));
    }
    self::fillSetQuery($obj, $q);
    $q->limit(1);
    return $q;
  }

  /**
   * Helper method prepares the passed DBQuery with the fields
   * and values for the given object.
   *
   * 2011-06-24: Allow for multipleValues instead of just values
   *
   * @param Array $fields provide this flag to use 'multipleValues'
   * instead of just 'values'
   */
  private static function fillSetQuery(DBObject $obj, DBQuery $q, Array $fields = array()) {
    $multiple = true;
    if (count($fields) == 0) {
      $multiple = false;
      $fields = $obj->db_fields();
    }
    $values = array();
    $types  = array();
    foreach ($fields as $field) {
      $value =& $obj->$field;
      $type = "";
      if ($value instanceof DBObject) {
        $sub = self::get($value, $value->id);
        if ($sub === null)
          self::set($value, false);
        $values[] =& $value->id;
        $type = $value->db_type('id');
      }
      elseif ($value instanceof DateTime)
        $values[] = $value->format('Y-m-d H:i:s');
      elseif (is_array($value))
        $values[] = implode("\0", $value);
      elseif (is_object($value) && $obj->db_type($field) != DBQuery::A_STR)
        $values[] = serialize($value);
      else {
        $values[] =& $value;
        if ($value !== null)
          $type = $obj->db_type($field);
      }
      if (strlen($type) != 1)
        $type = DBQuery::A_STR;
      $types[] = $type;
    }
    if ($multiple)
      $q->multipleValues($types, $values, $obj->db_name());
    else
      $q->values($fields, $types, $values, $obj->db_name());
  }

  /**
   * Generates ONE insert query for all the DBObjects in the given
   * list, which must be an Array or ArrayIterator. The DBObjects are
   * inserted, never updated, therefore it is very possible for the
   * query to fail due to primary key failures (this is in contrast to
   * the 'set' method which deals with those issues for you).
   *
   * To be nice and ensure correctness, this method will call 'set' on
   * any property of a DBObject in $list which is itself a DBObject
   * and has a NULL 'id'. It is best practice---and you follow best
   * practices because you are reading this docstring---to set these
   * property objects PRIOR to calling this method, just in case.
   *
   * @param Array|ArrayIterator $list the list of DBObjects to insert
   * in one giant query
   *
   * @throws InvalidArgumentException if argument is not iterable (NOT
   * iterateable, Josiah!) or ANY of its elements is not a
   * DBObject. Careful! While the exception is thrown BEFORE the giant
   * insert query, it could be thrown AFTER a 'set' subquery.
   */
  public static function insertAll($list) {
    if (!is_array($list) && !($list instanceof ArrayIterator))
      throw new InvalidArgumentException("Argument to insertAll must be iterable.");
    if (count($list) == 0) return;
    $tmpl = null;
    $fields = array();
    $q = self::createQuery(DBQuery::INSERT);
    foreach ($list as $i => $obj) {
      if (!($obj instanceof DBObject))
        throw new InvalidArgumentException(sprintf("insertAll arguments must be DBObject's; %s found instead.", get_class($obj)));
      if ($tmpl === null) {
        $tmpl = $obj;
        $fields = $tmpl->db_fields();
        $q->fields($fields, $tmpl->db_name());
      }
      elseif (get_class($tmpl) != get_class($obj))
        throw new InvalidArgumentException(sprintf("Expected element %s to be of type %s, found %s instead.", $i, get_class($tmpl), get_class($obj)));

      self::fillSetQuery($obj, $q, $fields);
    }
    self::query($q);
  }

  /**
   * Assigns the newID to the given object, committing the changes to
   * both the database and the object itself, unless something goes
   * wrong when comitting the new ID to the database, such as a
   * possibly invalid value for the $newID. This method is necessary
   * because the 'set' method uses the assumed "ID" property of the
   * <code>DBObject</code> as a filter in the where clause since ID is
   * assumed to be unique. Therefore, directly replacing the ID
   * property of the DBObject and then calling the <code>set</code>
   * method will likely result in creating a new object, leaving the
   * old one untouched in the database (a feature, I suppose, not a
   * bug).
   *
   * Note that this method will NOT check that the object exists or
   * that its ID is valid ahead of time (something set does in order
   * to check if it should update or insert). Instead, a
   * DatabaseException will be thrown and the ID property of the
   * passed object will not be updated. Should everything go gravily,
   * the passed object will now reflect the newID passed.
   *
   * Finally, note that this method will update all other properties
   * as well as the ID, so if called using
   *
   * <code>
   * $obj = new DBObject();
   * DBM::set($obj, $obj->id);
   * </code>
   *
   * the result is equivalent to calling set on the object itself.
   *
   * @param DBObject $obj the object whose ID to update
   * @param String $newID the new ID to assign
   * @throws DatabaseException if the $obj is not a previously
   * existing one.
   */
  public static function reID(DBObject $obj, $newID) {
    $old_id = $obj->id;
    $obj->id = $newID;
    $q = self::createQuery(DBQuery::UPDATE);
    $q->where(new DBCond('id', $old_id));
    
    self::fillSetQuery($obj, $q);
    self::query($q);
    $obj->id = $newID;
  }

  /**
   * Removes the given element from the database.
   *
   * @param DBObject $obj the object to remove (using the ID)
   * @see set
   */
  public static function remove(DBObject $obj) {
    $q = self::createQuery(DBQuery::DELETE);
    $q->fields(array(), $obj->db_name());
    $q->where(new DBCond("id", $obj->id));
    $q->limit(1);
    self::query($q);
  }

  public static function removeAll(DBObject $obj, DBExpression $where = null) {
    $q = self::createQuery(DBQuery::DELETE);
    $q->fields(array(), $obj->db_name());
    $q->where($where);
    self::query($q);
  }

  /**
   * Fetches a list of all the objects from the database. Returns a
   * DBDelegate object, which behaves much like an
   * array. Optionally add a where statement (unverified) to filter
   * results
   *
   * @param DBObject $obj the object type to retrieve
   * @param String $where the where statement to add, such as 'field = 4'
   * @return Array<DBObject> the list of objects
   * @throws DatabaseException related to an invalid where statement
   * @see filter
   */
  public static function getAll(DBObject $obj, DBExpression $where = null) {
    $r = self::query(self::prepGetAll($obj, $where));

    $del = new DBObject_Delegate(get_class($obj));
    return new DBDelegate($r, $del);
  }

  // ------------------------------------------------------------
  // Prepared queries
  // ------------------------------------------------------------

  /**
   * Prepares and returns the query object, prior to actually making
   * the call to the database, so that children classes can override
   * certain parameters as needed.
   *
   * @param DBObject $obj the object whose query to create
   * @param String $id the id to fetch
   * @return DBQuery the prepared query object
   * @see get
   * @since 2010-08-23
   */
  public static function prepGet(DBObject $obj, $id) {
    $q = self::createQuery();
    $q->fields($obj->db_fields(), $obj->db_name());
    $q->where(new DBBool(array($obj->db_where(), new DBCond("id", $id))));
    $q->limit(1);
    return $q;
  }

  /**
   * Prepares and returns the query object used when selecting
   * multiple objects.
   *
   * @param DBObject $obj the object type to retrieve
   * @param String $where the where statement to add, e.g. 'field=4'
   *
   * @param Array $fields (optional) list of fields to include in
   * response. This is particularly useful when using DBCondIn. If
   * empty, prepare the full object.
   *
   * @return Array<DBObject> the list of objects
   * @since 2010-08-23
   * @see prepGet
   */
  public static function prepGetAll(DBObject $obj, DBExpression $where = null, Array $fields = array()) {
    $f = (count($fields) == 0) ? $obj->db_fields() : $fields;
    $q = self::createQuery();
    $q->fields($f, $obj->db_name());
    $q->order_by($obj->db_get_order());
    $q->where($obj->db_where());
    $q->where($where);
    return $q;
  }

  // ------------------------------------------------------------
  // Search (filtering) tools
  // ------------------------------------------------------------

  /**
   * Performs a search for the given object. Returns the list of
   * matching results.
   *
   * All DBObjects employ the db_filter method which returns an array
   * of fields by which to search. This functions performs a database
   * query for any records of the given DBObject for which ANY of such
   * fields matches the query string, by using the like 'query' MySQL
   * statement.
   *
   * To specify a particular field(s) to search by instead of the
   * defaults returned by db_filter, provide them in the last
   * argument. Note that this method does not check that the field
   * names provided are valid, which might cause a DatabaseException
   * at the time of querying.
   *
   * If any of the fields specified are themselves DBObjects, as
   * specified by the db_type method, then the search is performed
   * recursively for that object.
   *
   * @param DBObject $obj the expected object type to return
   * @param String $qry the RAW query (no escaping required)
   * @param Array $fields the optional fields to search by
   * @return Array<DBObject> the matching result
   * @throws DatabaseException 
   */
  public static function filter(DBObject $obj, $qry, Array $fields = array()) {
    $r = self::query(self::prepFilter($obj, $qry, $fields));
    return new DBDelegate($r, new DBObject_Delegate(get_class($obj)));
  }

  /**
   * Prepares the query that would be executed by filter
   *
   * @see filter
   */
  public static function prepFilter(DBObject $obj, $qry, Array $fields = array()) {
    $name = $obj->db_name();
    $q = self::filter_query($obj, $qry, $fields);
    $q->fields($obj->db_fields(), $name);
    $q->order_by($obj->db_get_order());
    return $q;
  }

  /**
   * Sets up the DBQuery for the given object. This is a helper
   * method for the filter method above
   *
   * @return DBQuery the query, sans fields
   */
  private static function filter_query(DBObject $obj, $qry, Array $fields = array()) {
    if (count($fields) == 0)
      $fields = $obj->db_filter();

    $q = self::createQuery();
    $c = array(); // conditions array
    foreach ($fields as $field) {
      $type = $obj->db_type($field);
      if ($type instanceof DBObject) {
        $sub = self::filter_query($type, $qry);
        $sub->fields(array("id"), $type->db_name());
        $c[] = new DBCondIn($field, $sub);
      }
      else
        $c[] = new DBCond($field, $qry, DBCond::LIKE);
    }
    $q->where(new DBBool(array($obj->db_where(), new DBBool($c, DBBool::mOR))));
    return $q;
  }

  /**
   * Performs a flat search on the given object. Returns the list of
   * matching results.
   *
   * All DBObjects employ the db_filter method which returns an array
   * of fields by which to search. This functions performs a database
   * query for any records of the given DBObject for which ANY of such
   * fields matches the query string, by using the like '%query%' MySQL
   * statement.
   *
   * To specify a particular field(s) to search by instead of the
   * defaults returned by db_filter, provide them in the last
   * argument. Note that this method does not check that the field
   * names provided are valid, which might cause a DatabaseException
   * at the time of querying.
   *
   * Unlike 'filter', this method does not check the object tree
   * recursively.
   *
   * @param DBObject $obj the expected object type to return
   * @param String $qry the RAW query (no escaping required)
   * @param Array $fields the optional fields to search by
   * @return Array<DBObject> the matching result
   * @throws DatabaseException
   * @see filter
   */
  public static function search(DBObject $obj, $qry, Array $fields = array()) {
    $r = self::query(self::prepSearch($obj, $qry, $fields));
    return new DBDelegate($r, new DBObject_Delegate(get_class($obj)));
  }

  /**
   * Prepares the query used in the search. This is a helper method
   * for the class, but client users might find them useful if they
   * need to prepare a query for a DBCondIn condition, for instance.
   *
   * @see search
   * @return DBQuery the prepared query
   */
  public static function prepSearch(DBObject $obj, $qry, Array $fields = array()) {
    $name = $obj->db_name();
    $q = self::prepGetAll($obj);
    if (count($fields) == 0)
      $fields = $obj->db_filter();
    $cond = array();
    foreach ($fields as $field)
      $cond[] = new DBCond($field, "%{$qry}%", DBCond::LIKE);
    $q->where(new DBBool($cond, DBBool::mOR));
    return $q;
  }
}

/**
 * This is the parent class of all objects which are to be serialized
 * and unserialized to an external database. For simple objects, this
 * class provides almost all the necessary mechanisms for
 * synchronizing to the database, purporting to behave as though the
 * object itself was local. However, the final synchronization to the
 * database must occur through an external tool, such as the DBM
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
  private $dborder;
  private $dbcache;
  public function __construct() {}

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
      $class = new ReflectionClass($this);
      foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC |
                                     ReflectionProperty::IS_PROTECTED) as $field)
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
   * @param String $field the field name
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
   * Basic type checking, using db_type
   *
   * @param String $name the name of the function
   * @param mixed $value the new value
   * @throws BadFunctionCallException if no such property exists or
   * the type is wrong
   */
  public function __set($name, $value) {
    if (!property_exists($this, $name))
      throw new BadFunctionCallException(sprintf("Class %s does not have property %s.",
                                                 get_class($this), $name));

    if (in_array($name, $this->db_fields())) {
      $type = $this->db_type($name);
      if ($value !== null && is_object($type) && !($value instanceof $type)) {
        throw new BadFunctionCallException(sprintf("Property %s in class %s must be of type %s",
                                                   $name, get_class($this), get_class($type)));
      }
    }
    $this->$name = $value;
  }

  public function &__get($name) {
    if (!property_exists($this, $name))
      throw new BadFunctionCallException(sprintf("Class %s does not have property %s.",
                                                 get_class($this), $name));
    $type = $this->db_type($name);
    if ($type instanceof DBObject && !($this->$name instanceof DBObject))
      $this->$name = DBM::get($type, $this->$name);
    elseif ($type instanceof DateTime && is_string($this->$name))
      $this->$name = new DateTime($this->$name);
    elseif (is_array($type) && !is_array($this->$name))
      $this->$name = explode("\0", $this->$name);
    elseif (is_object($type) && is_string($this->$name))
      $this->$name = unserialize($this->$name);
    return $this->$name;
  }
}
?>