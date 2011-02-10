<?php
/**
 * 2011-01-04: Delay database connection until needed by passing the
 * arguments to the setConnectionParams function instead
 *
 * 2010-12-13: New cached version of the libraries introduced.
 * Includes changes so that deserialized objects are kept in memory,
 * rather than being deserialized every time. Note that this library
 * is mutually exclusive to the standard DBM class, and is meant as a
 * drop-in replacement for the same.
 *
 * 2010-08-20: Incompatible changes to the way where clauses are handled.
 *
 * Dealing with and interacting with the database.
 *
 * Assumes existence of MySQLi-like functions. In this reiteration,
 * all objects subclass one abstract parent called DBObject, which is
 * by all means a simple class. Its only function is to provide a list
 * of fields and a database name for each particular subclass. It no
 * longer actually deals with the database and its connections.
 *
 * Instead, the DBM class takes care of any database connections and
 * subsequent calls to it. It provides static methods for general
 * purpose calls (such as retrieving a particular object from the
 * database). The instantiated objects of DBM are useful for
 * dynamically creating object's properties which are in turn
 * deserialized from the database. That way, entire "object trees" can
 * be deserialized from the database, but only when requested by the
 * client code.
 *
 * For instance, the Endowment project has a property called "report"
 * which is in turn a "Report" object. When the DBM static method
 * creates the Endowment object, it will leave a DBM instantiated
 * object as a stand-in for the "report" property. The first time this
 * property is requested by the client code, the DBObject calls the
 * <pre>serialize</pre> method on the DBM object standing in for
 * "report" in order to create the Report object on the fly. This
 * helps reduce bandwidth and unnecessary database calls.
 *
 * To ensure that all this happens, the DBObject class provides the
 * <pre>__get</pre> and <pre>__set</pre> methods, and requires that the
 * objects which need to be serialized with DBM have protected (not
 * public) fields (otherwise, __get and __set would never be
 * called). DBObject returns all of the subclass's public and
 * protected fields as database fields by default. It also provides
 * for an ID field to exist in all cases.
 *
 * @author Dayan Paez
 * @version 2010-06-10
 * @package mysql
 */

require_once(dirname(__FILE__).'/MySQLi_Query.php');
require_once(dirname(__FILE__).'/MySQLi_delegate.php');

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
    if (self::$__con === null && self::$__con_host !== null)
      self::$__con = new MySQLi(self::$__con_host,
				self::$__con_user,
				self::$__con_pass,
				self::$__con_name);
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
   * @param MySQLi_Query $q the query to send
   * @return MySQLi_Result the result
   * @throws BadFunctionCallException if the query reveals errors
   */
  public static function query(MySQLi_Query $q) {
    $con = DBM::connection();
    $res = $con->query($q->query());
    if ($con->errno > 0)
      throw new BadFunctionCallException("MySQL error ($q): " . $con->error);
    if (self::$log_path !== null)
      @error_log($q->query(). "\n", 3, self::$log_path);
    return $res;
  }

  // ------------------------------------------------------------
  // Generic database object serialization
  // ------------------------------------------------------------

  /**
   * Returns an empty MySQLi_Query of the given type
   *
   * @param MySQLi_Query::Const $type the argument to the MySQLi_Query constructor
   * @return MySQLi_Query bound to this object's connection
   */
  public static function createQuery($type = MySQLi_Query::SELECT) {
    return new MySQLi_Query(self::connection(), $type);
  }

  /**
   * Retrieves the object with the given ID
   *
   * @param DBObject $obj the object type
   * @param String|int $id the id of the object
   * @return mixed|null the matching object, or null if not found
   */
  public static function get(DBObject $obj, $id) {
    $c = get_class($obj);
    if ($obj->db_cache() && $obj->id !== null) {
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
   * @return boolean true if an update was done, false otherwise
   *
   * @see get
   * @version 2010-12-02: Added second parameter
   */
  public static function set(DBObject $obj, $update = "guess") {
    if ($update === true) {
      $q = self::createQuery(MySQLi_Query::UPDATE);
      $q->where(new MyCond("id", $obj->id));
    }
    elseif ($update === false) {
      $q = self::createQuery(MySQLi_Query::INSERT);
    }
    else {
      // guess?
      $exist = self::get($obj, $obj->id);
      return self::set($obj, ($exist instanceof DBObject));
    }
    self::prepSet($obj, $q);
    $q->limit(1);
    self::query($q);

    // Update ID if necessary
    if ($update === false && $obj->id == null) // then it must be auto-increment!
      $obj->id = self::connection()->insert_id;
    $obj->db_set_hook();
    return $update;
  }

  /**
   * Helper method prepares the passed mysqli_query with the fields
   * and values for the given object.
   *
   */
  private static function prepSet(DBObject $obj, MySQLi_Query $q) {
    $fields = $obj->db_fields();
    $values = array();
    foreach ($fields as $field) {
      $value = $obj->$field;
      if ($value instanceof DBObject) {
	$sub = self::get($value, $value->id);
	if ($sub === null)
	  self::set($value, false);
	$values[] = $value->id;
      }
      elseif ($value instanceof DateTime)
	$values[] = $value->format('Y-m-d H:i:s');
      else
	$values[] = $value;
    }
    $q->values($fields, $values, $obj->db_name());
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
    $fields = $obj->db_fields();
    $values = array();
    foreach ($fields as $field) {
      $value = $obj->$field;
      if ($field == "id")
	$values[] = $newID;
      elseif ($value instanceof DBObject) {
	self::set($value);
	$values[] = $value->id;
      }
      elseif ($value instanceof DateTime)
	$values[] = $value->format('Y-m-d H:i:s');
      else
	$values[] = $value;
    }

    $q = self::createQuery(MySQLi_Query::UPDATE);
    $q->where(new MyCond("id", $obj->id));
    $q->values($fields, $values, $obj->db_name());
    $q->limit(1);

    self::query($q);
  }

  /**
   * Removes the given element from the database.
   *
   * @param DBObject $obj the object to remove (using the ID)
   * @see set
   */
  public static function remove(DBObject $obj) {
    $q = self::createQuery(MySQLi_Query::DELETE);
    $q->fields(array(), $obj->db_name());
    $q->where(new MyCond("id", $obj->id));
    $q->limit(1);
    self::query($q);
  }

  /**
   * Fetches a list of all the objects from the database. Returns a
   * MySQLi_Delegate object, which behaves much like an
   * array. Optionally add a where statement (unverified) to filter
   * results
   *
   * @param DBObject $obj the object type to retrieve
   * @param String $where the where statement to add, such as 'field = 4'
   * @return Array<DBObject> the list of objects
   * @throws DatabaseException related to an invalid where statement
   * @see filter
   */
  public static function getAll(DBObject $obj, MyExpression $where = null) {
    $r = self::query(self::prepGetAll($obj, $where));

    $del = new MySQLi_Object_Delegate(get_class($obj));
    return new MySQLi_Delegate($r, $del);
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
   * @return MySQLi_Query the prepared query object
   * @see get
   * @since 2010-08-23
   */
  public static function prepGet(DBObject $obj, $id) {
    $q = self::createQuery();
    $q->fields($obj->db_fields(), $obj->db_name());
    $q->where(new MyBoolean(array($obj->db_where(), new MyCond("id", $id))));
    $q->limit(1);
    return $q;
  }

  /**
   * Prepares and returns the query object used when selecting
   * multiple objects.
   *
   * @param DBObject $obj the object type to retrieve
   * @param String $where the where statement to add, such as 'field = 4'
   * @return Array<DBObject> the list of objects
   * @since 2010-08-23
   * @see prepGet
   */
  public static function prepGetAll(DBObject $obj, MyExpression $where = null) {
    $q = self::createQuery();
    $q->fields($obj->db_fields(), $obj->db_name());
    $q->order_by($obj->db_order_by(), $obj->db_order());
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
    $name = $obj->db_name();
    $q = self::filter_query($obj, $qry, $fields);
    $q->fields($obj->db_fields(), $name);
    $q->order_by($obj->db_order_by(), $obj->db_order());

    $r = self::query($q);
    return new MySQLi_Delegate($r, new MySQLi_Object_Delegate($name));
  }

  /**
   * Sets up the MySQLi_Query for the given object. This is a helper
   * method for the filter method above
   *
   * @return MySQLi_Query the query, sans fields
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
	$c[] = new MyCondIn($field, $sub);
      }
      else
	$c[] = new MyCond($field, $qry, MyCond::LIKE);
    }
    $q->where(new MyBoolean(array($obj->db_where(),
				  new MyBoolean($c, MyBoolean::mOR))));

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
    return new MySQLi_Delegate($r, new MySQLi_Object_Delegate(get_class($obj)));
  }

  /**
   * Prepares the query used in the search. This is a helper method
   * for the class, but client users might find them useful if they
   * need to prepare a query for a MyCondIn condition, for instance.
   *
   * @see search
   * @return MySQLi_Query the prepared query
   */
  public static function prepSearch(DBObject $obj, $qry, Array $fields = array()) {
    $name = $obj->db_name();
    $q = self::prepGetAll($obj);
    if (count($fields) == 0)
      $fields = $obj->db_filter();
    $cond = array();
    foreach ($fields as $field)
      $cond[] = new MyCond($field, sprintf('%%%s%%', $qry), MyCond::LIKE);
    $q->where(new MyBoolean($cond, MyBoolean::mOR));
    return $q;
  }

  // ------------------------------------------------------------
  // Instance objects: provides a delegate when DBObjects need to
  // retrieve properties which are also DBObjects
  // ------------------------------------------------------------

  private $obj_type;
  private $obj_id;

  public function __construct(DBObject $type, $id) {
    $this->obj_type = $type;
    $this->obj_id   = $id;
  }

  public function serialize() {
    return DBM::get($this->obj_type, $this->obj_id);
  }
}

/**
 * This is the parent class of all objects which are to be serialized
 * and unserialized to an external MySQL database. For simple objects,
 * this class provides almost all the necessary mechanisms for
 * synchronizing to the database, purporting to behave as though the
 * object itself was local. However, the final synchronization to the
 * database must occur through an external tool, such as the DBM class.
 *
 * @author Dayan Paez
 * @version 2010-06-10
 */
class DBObject {

  /**
   * @var mixed the id of the object
   */
  protected $id;

  public function __construct() {
    if ($this->id === null) return;
    
    foreach ($this->db_fields() as $field) {
      $type = $this->db_type($field);
      if ($type instanceof DBObject && $this->$field !== null) {
	$this->$field = new DBM($type, $this->$field);
      }
      if ($type instanceof DateTime && $this->$field !== null)
	$this->$field = new DateTime($this->$field);
    }
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
    $class = new ReflectionClass($this);
    $fields = array();
    foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC |
				   ReflectionProperty::IS_PROTECTED) as $field)
      $fields[] = $field->name;
    return $fields;
  }

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
    return "string";
  }

  /**
   * Retrieves the value to be stored in the database for the given
   * field, presumably one of the <code>db_fields</code>. By default,
   * this will return the value of the field, unless such a value is
   * a DBObject, in which case the ID is returned instead, or a
   * DateTime object in which ase YYYY-MM-DD HH:II:SS is returned
   * instead.
   *
   * @param String $field the field name
   * @return String|int the value of the object
   * @throws InvalidArgumentException if the field is bogus
   * @see __get
   */
  public function db_value($field) {
    $value = $this->__get($field);
    if ($value instanceof DBObject)
      return $value->id;
    elseif ($value instanceof DateTime)
      return $value->format('Y-m-d H:i:s');
    return $value;
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
   * @return String "id"
   */
  public function db_order() { return "id"; }
  public function db_order_by() { return true; }

  /**
   * Optional hook to run after the DBM-like object has been committed
   * to the database. Useful for tweaking after the fact.
   *
   */
  public function db_set_hook() {}

  public function db_cache() { return false; }

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
						   $name, get_class($this), $type));
      }
    }
    $this->$name = $value;
  }

  public function __get($name) {
    if (!property_exists($this, $name))
      throw new BadFunctionCallException(sprintf("Class %s does not have property %s.",
						 get_class($this), $name));
    if ($this->$name instanceof DBM)
      $this->$name = $this->$name->serialize();
    if ($name == "id")
      return ($this->id === null) ? "" : $this->id;
    return $this->$name;
  }
}
?>