<?php
/* This file is part of TechScore
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
  private $con;
  private $username;
  private $properties;

  const FIRST_NAME = "first_name";
  const LAST_NAME  = "last_name";
  const USERNAME   = "username";
  const SCHOOL     = "school";
  const ROLE       = "role";
  const ADMIN      = "admin";

  const FIELDS = "account.first_name, account.last_name, 
                  account.username, account.role,
                  is_admin as admin,
                  school.id, school.nick_name, school.name, school.conference,
                  school.city, school.state, school.burgee";
  const TABLES = "account inner join school on (account.school = school.id)";

  /**
   * Creates (retrieves) the user from the database with the given
   * username.
   *
   * @throws InvalidArgumentException if invalid username
   */
  public function __construct($username) {
    $this->con = new MySQLi(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB);

    $this->username = $username;
    $q = sprintf('select %s from %s where username = "%s"',
		 self::FIELDS, self::TABLES, $username);
    $result = $this->query($q);
    if ($result->num_rows > 0) {
      $this->properties = $result->fetch_assoc();
      
      // Create school object, and delete all temporary fields
      $school = new School();
      $school->id         = $this->properties['id'];
      $school->nick_name  = $this->properties['nick_name'];
      $school->name       = $this->properties['name'];
      $school->conference = Preferences::getConference($this->properties['conference']);
      $school->city       = $this->properties['city'];
      $school->state      = $this->properties['state'];
      $school->burgee     = $this->properties['burgee'];

      $this->properties['school'] = $school;
      unset($this->properties['id'],
	    $this->properties['nick_name'],
	    $this->properties['name'],
	    $this->properties['conference'],
	    $this->properties['city'],
	    $this->properties['state'],
	    $this->properties['burgee']);

      $this->properties['admin'] = ($this->properties['admin'] > 0);
    }
    else {
      $m = sprintf("Invalid username (%s) for user: %s", $username, $this->con->error);
      throw new InvalidArgumentException($m);
    }
  }

  /**
   * Sends the query to the database and handles errors. Returns the
   * resultant mysqli_result object
   */
  private function query($string) {
    if ($q = $this->con->query($string)) {
      return $q;
    }
    throw new BadFunctionCallException($q->error . ": " . $string);
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
    return $this->properties[$key];
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
    $q = sprintf('select %s from %s inner join host on (regatta.id = host.regatta) ' .
		 'where host.account = "%s" order by start_time desc %s',
		 RegattaSummary::FIELDS,
		 RegattaSummary::TABLES,
		 $this->username,
		 $limit);
    $q = $this->query($q);
    $list = array();
    while ($obj = $q->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns just the number of regattas this user is registered as a
   * scorer
   *
   * @return int the total number of regattas
   */
  public function getNumRegattas() {
    $q = sprintf('select count(*) as count from %s ' .
		 'inner join host on (regatta.id = host.regatta) ' .
		 'where host.account = "%s"',
		 RegattaSummary::TABLES, $this->username);
    $q = $this->query($q);
    return (int)$q->fetch_object()->count;
  }

  /**
   * Fetches the user as an account
   *
   * @return Account the account
   */
  public function asAccount() {
    return Preferences::getAccount($this->username);
  }

}

if (basename(__FILE__) == basename($argv[0])) {
  $user = new User("paez@mit.edu");
  print_r($user->asAccount());
}
?>