<?php
namespace MyORM;
/*
 * DBI and DBObject provide a centralized abstraction for serializing
 * and deserializing objects to a database. DBI is the (M)aster class
 * which handles the querying of the database (in this case MySQL),
 * and provides methods for handling the data.
 *
 * DBI handles DBObject's. Please see the tutorial for more
 * information.
 *
 * This version of the application uses instantiated classes rather
 * than static methods. Hence, 'I' in DBI.php.
 *
 * @author Dayan Paez
 * @version 2011-12-07
 * @package mysql
 */

/**
 * Manages all the connections to the database and provides for basic
 * methods of data serialization.
 *
 * @author Dayan Paez
 * @version 2010-06-14
 */
class DBI {
  /**
   * @var String connection parameters
   */
  private $__con_host;
  private $__con_user;
  private $__con_pass;
  private $__con_name;

  /**
   * @var MySQLi connection
   */
  private $__con;

  /**
   * @var String path to query log, NULL for no logging
   */
  private $log_path = null;

  /**
   * @var Array the list of deserialized objects, indexed by {classname}_{id}.
   */
  protected $objs = array();

  /**
   * @var DBIDelegate the delegate to use on getAll and filter queries
   */
  protected $delegate;

  /**
   * Retrieves the database connection
   *
   * @return MySQLi connection
   */
  public function connection() {
    if ($this->__con === null && $this->__con_host !== null) {
      $this->__con = new DBConnection($this->__con_host,
                                      $this->__con_user,
                                      $this->__con_pass,
                                      $this->__con_name);
    }
    return $this->__con;
  }

  /**
   * Silently cancels the current transaction
   *
   */
  public function rollback() {
    if ($this->__con !== null)
      $this->__con->rollback();
  }

  /**
   * Requests the current transaction be committed.
   *
   */
  public function commit() {
    if ($this->__con !== null)
      $this->__con->commit();
  }

  /**
   * Sets the connection to use. Consider using the constructor to
   * delay opening the MySQL connection until absolutely necessary.
   *
   * @param MySQLi $con the working connection
   */
  public function setConnection(\MySQLi $con) {
    $this->__con = $con;
  }

  /**
   * Sets the template delegate to use on getAll requests
   *
   * @param DBIDelegate $del the delegate to use
   */
  public function setDelegate(DBIDelegate $del) {
    $this->delegate = $del;
    $this->delegate->setDBI($this);
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
  public function __construct($host = null, $user = null, $pass = null, $name = null) {
    if ($host !== null) {
      $this->__con_host = $host;
      $this->__con_user = $user;
      $this->__con_pass = $pass;
      $this->__con_name = $name;
      $this->setDelegate(new DBIDelegate(new DBObject(), $this));
    }
  }

  /**
   * Sets whether or not to log queries. Use NULL for no logging
   *
   * @param String $path the path to the log file, use null to turn off logging
   */
  public function setLogfile($path = null) {
    $this->log_path = $path;
  }

  /**
   * Sends a query to the database
   *
   * @param DBQuery $q the query to send
   * @return DBResult the result
   * @throws BadFunctionCallException if the query reveals errors
   */
  public function query(DBQuery $q) {
    $res = $q->query();
    if ($this->log_path !== null)
      @error_log($q->toSQL(). "\n", 3, $this->log_path);
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
  public function createQuery($type = DBQuery::SELECT) {
    return new DBQuery($this->connection(), $type);
  }

  /**
   * Returns object with given ID from cache, if any
   *
   * @param DBObject $obj the object type
   * @param String|int $id the id of the object
   * @return DBObject|null the object if it is in cache
   */
  public function getCached(DBObject $obj, $id) {
    if ($id === null)
      return null;
    $i = get_class($obj) . '_' . $id;
    return (isset($this->objs[$i])) ? $this->objs[$i] : null;
  }

  public function setCached(DBObject $obj) {
    if ($obj->id === null)
      return;
    $i = get_class($obj) . '_' . $obj->id;
    $this->objs[$i] = $obj;
  }

  /**
   * Empties the internal DBObject cache
   *
   * It is a good idea to call this method with every "transaction"
   * commit, to avoid using stale data.
   */
  public function resetCache() {
    $this->objs = array();
  }

  /**
   * Retrieves the object with the given ID
   *
   * @param DBObject $obj the object type
   * @param String|int $id the id of the object
   * @return mixed|null the matching object, or null if not found
   */
  public function get(DBObject $obj, $id) {
    $id = (string)$id; // make sure NEVER to issue 'id is null'
    if ($obj->db_get_cache() && $id !== null) {
      $c = get_class($obj);
      $i = $c.'_'.$id;
      if (!isset($this->objs[$i])) {
        $r = $this->query($this->prepGet($obj, $id));
        $this->objs[$i] = ($r->num_rows == 0) ? null :
          $r->fetch_object($c);
        $r->free();
        if ($this->objs[$i] !== null)
          $this->objs[$i]->db_set_dbi($this);
      }
      return $this->objs[$i];
    }
    $r = $this->query($this->prepGet($obj, $id));
    if ($r->num_rows == 0) {
      $r->free();
      return null;
    }
    $b = $r->fetch_object(get_class($obj));
    if ($b !== null)
      $b->db_set_dbi($this);
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
  public function set(DBObject $obj, $update = "guess") {
    $this->query($this->prepSet($obj, $update));

    // Update ID if necessary
    if ($obj->id === null)
      $obj->id = $this->connection()->insert_id;
  }

  public function prepSet(DBObject $obj, $update = "guess") {
    if ($update === true) {
      $q = $this->createQuery(DBQuery::UPDATE);
      $q->where(new DBCond("id", $obj->id));
    }
    elseif ($update === false || $obj->id === null) {
      $q = $this->createQuery(DBQuery::INSERT);
    }
    else {
      // guess?
      $exist = $this->get($obj, $obj->id);
      return $this->prepSet($obj, ($exist instanceof DBObject));
    }
    $this->fillSetQuery($obj, $q);
    $obj->db_set_dbi($this);
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
  private function fillSetQuery(DBObject $obj, DBQuery $q, Array $fields = array()) {
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
        $sub = $this->get($value, $value->id);
        if ($sub === null)
          $this->set($value, false);
        $values[] =& $value->id;
        $type = $value->db_type('id');
      }
      elseif ($value instanceof \DateTime)
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
  public function insertAll($list) {
    if (!is_array($list) && !($list instanceof ArrayIterator))
      throw new \InvalidArgumentException("Argument to insertAll must be iterable.");
    if (count($list) == 0) return;
    $tmpl = null;
    $fields = array();
    $q = $this->createQuery(DBQuery::INSERT);
    foreach ($list as $i => $obj) {
      if (!($obj instanceof DBObject))
        throw new \InvalidArgumentException(sprintf("insertAll arguments must be DBObject's; %s found instead.", get_class($obj)));
      if ($tmpl === null) {
        $tmpl = $obj;
        $fields = $tmpl->db_fields();
        $q->fields($fields, $tmpl->db_name());
      }
      elseif (get_class($tmpl) != get_class($obj))
        throw new \InvalidArgumentException(sprintf("Expected element %s to be of type %s, found %s instead.", $i, get_class($tmpl), get_class($obj)));

      $this->fillSetQuery($obj, $q, $fields);
    }
    $this->query($q);
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
   * DBException will be thrown and the ID property of the
   * passed object will not be updated. Should everything go gravily,
   * the passed object will now reflect the newID passed.
   *
   * Finally, note that this method will update all other properties
   * as well as the ID, so if called using
   *
   * <code>
   * $obj = new DBObject();
   * DBM::reID($obj, $obj->id);
   * </code>
   *
   * the result is equivalent to calling set on the object itself.
   *
   * @param DBObject $obj the object whose ID to update
   * @param String $newID the new ID to assign
   * @throws DBException if the $obj is not a previously
   * existing one.
   */
  public function reID(DBObject $obj, $newID) {
    $old_id = $obj->id;
    $obj->id = $newID;
    $q = $this->createQuery(DBQuery::UPDATE);
    $q->where(new DBCond('id', $old_id));

    $this->fillSetQuery($obj, $q);
    $this->query($q);
    $obj->id = $newID;
  }

  /**
   * Removes the given element from the database.
   *
   * @param DBObject $obj the object to remove (using the ID)
   * @see set
   */
  public function remove(DBObject $obj) {
    $q = $this->createQuery(DBQuery::DELETE);
    $q->fields(array(), $obj->db_name());
    $q->where(new DBCond("id", $obj->id));
    $q->limit(1);
    $this->query($q);
  }

  public function removeAll(DBObject $obj, DBExpression $where = null) {
    $q = $this->createQuery(DBQuery::DELETE);
    $q->fields(array(), $obj->db_name());
    $q->where($where);
    $this->query($q);
  }

  /**
   * Fetches a list of all the objects from the database. Returns a
   * DBDelegate object, which behaves much like an
   * array. Optionally add a where statement (unverified) to filter
   * results
   *
   * @param DBObject $obj the object type to retrieve
   * @param String $where the where statement to add, such as 'field = 4'
   * @param int $limit optional simple limit to set on return value.
   * @return Array<DBObject> the list of objects
   * @throws DBException related to an invalid where statement
   * @see filter
   */
  public function getAll(DBObject $obj, DBExpression $where = null, $limit = null) {
    $r = $this->query($this->prepGetAll($obj, $where, array(), $limit));

    $del = clone $this->delegate;
    $del->setClass(get_class($obj));
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
  public function prepGet(DBObject $obj, $id) {
    $q = $this->createQuery();
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
   * @param int $limit optional simple limit for return values.
   * @return Array<DBObject> the list of objects
   * @since 2010-08-23
   * @see prepGet
   */
  public function prepGetAll(DBObject $obj, DBExpression $where = null, Array $fields = array(), $limit = null) {
    $f = (count($fields) == 0) ? $obj->db_fields() : $fields;
    $q = $this->createQuery();
    $q->fields($f, $obj->db_name());
    $q->order_by($obj->db_get_order());
    $q->where($obj->db_where());
    $q->where($where);
    if ($limit !== null) {
      $q->limit($limit);
    }
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
   * names provided are valid, which might cause a DBException
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
   * @throws DBException 
   */
  public function filter(DBObject $obj, $qry, Array $fields = array()) {
    $r = $this->query($this->prepFilter($obj, $qry, $fields));
    $del = clone $this->delegate;
    $del->setClass(get_class($obj));
    return new DBDelegate($r, $del);
  }

  /**
   * Prepares the query that would be executed by filter
   *
   * @see filter
   */
  public function prepFilter(DBObject $obj, $qry, Array $fields = array()) {
    $name = $obj->db_name();
    $q = $this->filter_query($obj, $qry, $fields);
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
  private function filter_query(DBObject $obj, $qry, Array $fields = array()) {
    if (count($fields) == 0)
      $fields = $obj->db_filter();

    $q = $this->createQuery();
    $c = array(); // conditions array
    foreach ($fields as $field) {
      $type = $obj->db_type($field);
      if ($type instanceof DBObject) {
        $sub = $this->filter_query($type, $qry);
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
   * names provided are valid, which might cause a DBException
   * at the time of querying.
   *
   * Unlike 'filter', this method does not check the object tree
   * recursively.
   *
   * @param DBObject $obj the expected object type to return
   * @param String $qry the RAW query (no escaping required)
   * @param Array $fields the optional fields to search by
   * @return Array<DBObject> the matching result
   * @throws DBException
   * @see filter
   */
  public function search(DBObject $obj, $qry, Array $fields = array()) {
    $r = $this->query($this->prepSearch($obj, $qry, $fields));
    return new DBDelegate($r, new DBObject_Delegate(get_class($obj)));
  }

  /**
   * Prepares the query used in the search. This is a helper method
   * for the class, but client users might find them useful if they
   * need to prepare a query for a DBCondIn condition, for instance.
   *
   * @param Array $query_fields if empty array (default) use the
   * object's db_fields. Otherwise, the ones provided therein.
   * @see search
   * @return DBQuery the prepared query
   */
  public function prepSearch(DBObject $obj, $qry, Array $fields = array(), Array $query_fields = array()) {
    $name = $obj->db_name();
    $q = $this->prepGetAll($obj);
    $f = (count($query_fields) == 0) ? $obj->db_fields() : $query_fields;
    $q->fields($f, $obj->db_name());
    if (count($fields) == 0)
      $fields = $obj->db_filter();
    $cond = array();
    foreach ($fields as $field)
      $cond[] = new DBCond($field, "%{$qry}%", DBCond::LIKE);
    $q->where(new DBBool($cond, DBBool::mOR));
    return $q;
  }
}
?>