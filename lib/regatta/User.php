<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates a user of the program. Provides a number of functions
 * for interacting with the database
 *
 * @author Dayan Paez
 * @version 1.0
 */
class User {

  // Private variables
  private $username;
  private $properties;

  const FIRST_NAME = "first_name";
  const LAST_NAME  = "last_name";
  const USERNAME   = "username";
  const SCHOOL     = "school";
  const ROLE       = "role";
  const ADMIN      = "admin";
  const STATUS     = "status";

  const FIELDS = "account.first_name, account.last_name, 
                  account.id, account.role, account.status,
                  account.school, is_admin as admin";
  const TABLES = "account";

  /**
   * Creates (retrieves) the user from the database with the given
   * username.
   *
   * @throws InvalidArgumentException if invalid username
   */
  public function __construct($username) {
    $this->username = $username;
    $q = sprintf('select %s from %s where id = "%s"',
		 self::FIELDS, self::TABLES, $username);
    $result = Preferences::query($q);
    if ($result->num_rows > 0) {
      $this->properties = $result->fetch_assoc();
      $this->properties['admin'] = ($this->properties['admin'] > 0);
    }
    else {
      $m = sprintf("Invalid username (%s) for user.", $username);
      throw new InvalidArgumentException($m);
    }
  }

  /**
   * Retrieves the named property for this user. Key should be one of 
   * FIRST_NAME, LAST_NAME, USERNAME, SCHOOL, ROLE, ADMIN
   *
   * @return mixed the specified property
   * @throws InvalidArgumentException if the key is not valid
   */
  public function get($key) {
    if (!in_array($key, array_keys($this->properties))) {
      throw new InvalidArgumentException("No such property " . $key);
    }
    if ($key == "school")
      return Preferences::getSchool($this->properties["school"]);

    return $this->properties[$key];
  }

  /**
   * Commits the given property value to database
   *
   * @param Const $key the property name, one of the class constants
   * @param mixed $value the appropriate value. For school, this
   * should be a School object. For status, this should be one of the
   * approved status: 'pending', 'accepted', 'rejected', 'active', and
   * 'inactive'.
   *
   * @throw InvalidArgumentException
   */
  public function set($key, $value) {
    if (!isset($this->properties[$key])) {
      throw new InvalidArgumentException("Invalid User property to update " . $key);
    }
    if ($key == "school") {
      if (!($value instanceof School))
	throw new InvalidArgumentException("User school property must be School object.");

      $value = $value->id;
    }
    $q = sprintf('update account set %s = "%s" where id = "%s"',
		 $key, $value, $this->username);
    Preferences::query($q);
  }

  /**
   * Returns the username
   *
   * @return string the username
   */
  public function username() {
    return $this->username;
  }

  public function __toString() {
    return $this->getName();
  }

  /**
   * Returns the user's name
   *
   * @return string "First Lastname"
   */
  public function getName() {
    return sprintf("%s %s",
		   $this->properties[self::FIRST_NAME],
		   $this->properties[self::LAST_NAME]);
  }

  /**
   * Returns all the regattas for which this user is registered as a
   * scorer, using the given optional indices to limit the list, like
   * the range function in Python.
   *
   * <ul>
   *   <li>To fetch the first ten: <code>getRegattas(10);</code></li>
   *   <li>To fetch the next ten:  <code>getRegattas(10, 20);</code><li>
   * </ul>
   *
   * @param int $start the start index (inclusive)
   * @param int $end   the end index (exclusive)
   * @return Array<RegattaSummary>
   * @throws InvalidArgumentException if one of the parameters is wrong
   */
  public function getRegattas($start = null, $end = null) {
    $limit = "";
    if ($start === null)
      $limit = "";
    else {
      $start = (int)$start;
      if ($start < 0)
	throw new InvalidArgumentException("Start index ($start) must be greater than zero.");
    
      if ($end === null)
	$limit = "limit $start";
      elseif ((int)$end < $start)
	throw new InvalidArgumentException("End index ($end) must be greater than start ($start).");
      else {
	$range = (int)$end - $start;
	$limit = "limit $start, $range";
      }
    }

    // Setup the query
    $q = sprintf('select %s from %s ', RegattaSummary::FIELDS, RegattaSummary::TABLES);
    if ($this->get(User::ADMIN) == 0) // not admin
      $q .= sprintf('inner join host on (regatta.id = host.regatta) ' .
		    'where host.account = "%s" ',
		    $this->username);
    $q .= "order by start_time desc $limit";
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Searches and returns a list of matching regattas.
   *
   * @param String $qry the query to search
   * @return Array:RegattaSummary the regattas
   */
  public function searchRegattas($qry) {
    $q = sprintf('select %s from %s where name like "%%%s%%" ',
		 RegattaSummary::FIELDS, RegattaSummary::TABLES, $qry);
    if ($this->get(User::ADMIN) == 0) // not admin
      $q .= sprintf('and id in (select regatta from host where host.account = "%s") ', $this->username);
    $q .= 'order by start_time desc';
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns all the schools that this user is affiliated with,
   * including the one enrolled as.
   *
   * @param Conference $conf the possible to conference to narrow down
   * school list
   * @return Array:School, indexed by school ID
   */
  public function getSchools(Conference $conf = null) {
    $list = array();
    $school = $this->get(User::SCHOOL);
    if ($conf === null || $school->conference == $conf)
      $list[$school->id] = $school;
    if ($this->get(User::ADMIN) > 0)
      $q = sprintf('select id as school from school');
    else
      $q = sprintf('select school from account_school where account="%s"', $this->username);
    $q = Preferences::query($q);
    while ($r = $q->fetch_object()) {
      $school = Preferences::getSchool($r->school);
      if ($conf === null || $school->conference == $conf)
	$list[$school->id] = $school;
    }
    return $list;
  }

  /**
   * Determines whether the given regatta is in this user's scoring
   * jurisdiction
   *
   * @param Regatta $reg the regatta to check
   */
  public function hasJurisdiction(Regatta $reg) {
    if ($this->get(User::ADMIN) > 0) return true;
    $q = sprintf('select * from host where (account, regatta) = ("%s", %d) limit 1',
		 $this->username(), $reg->id());
    $q = Preferences::query($q);
    $r = ($q->num_rows > 0);
    $q->free();
    return $r;
  }

  /**
   * Returns just the number of regattas this user is registered as a
   * scorer
   *
   * @return int the total number of regattas
   */
  public function getNumRegattas() {
    if ($this->get(User::ADMIN))
      $q = sprintf('select count(*) as count from %s', RegattaSummary::TABLES);
    else
      $q = sprintf('select count(*) as count from %s ' .
		   'inner join host on (regatta.id = host.regatta) ' .
		   'where host.account = "%s"',
		   RegattaSummary::TABLES, $this->username);
    $q = Preferences::query($q);
    return (int)$q->fetch_object()->count;
  }

  /**
   * Fetches the user as an account
   *
   * @return Account the account
   */
  public function asAccount() {
    return AccountManager::getAccount($this->username);
  }

}

?>