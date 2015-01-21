<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Script to up/downgrade database schema
 *
 * Look for NNNNN*.sql files in the local 'db' directory, and run the
 * necessary files to bring the database schema up to date. The
 * comparison is done against the _schema_ table of the database,
 * which must exist. If it doesn't, then the table itself is created,
 * using the file db/00000_schema.sql.
 *
 * To make things work easier/smoothier, the default user should have
 * create temporary tables privilege.
 *
 * @author Dayan Paez
 * @created 2014-11-11
 */
class MigrateDB extends AbstractScript {

  private $dry_run = false;

  private $root_password = null;

  private $batch_dir_listing = null;

  private $root_connection_setup = false;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  public function setQuiet() {
    self::setVerbosity(0);
  }

  public function __construct() {
    parent::__construct();
    $this->root_connection_setup = false;
    $this->cli_opts = '[-n] [-q] [--down ID]';
    $this->cli_usage = ' --down ID      Downgrade through given entry ID
 -n, --dry-run  Do not perform upgrade
 -q, --quiet    Suppress output (default v=1)';
  }

  /**
   * Migrate automatically, by syncing filesystem and DB
   *
   */
  public function run() {

    // SETUP ENVIRONMENT
    if (!$this->schemaTableExists()) {
      self::errln("Schema table does not exist...creating");
      $this->runFile('00000_schema.sql');
    }

    // TEMPORARY TABLE PRIVILEGE?
    if (!$this->root_connection_setup) {
      if (!$this->hasCreateTemporaryTablePrivilege()) {
        self::errln(sprintf("
User `%s` has no 'CREATE TEMPORARY TABLE' privilege. It is
strongly recommended that privilege is granted.
",
                    Conf::$SQL_USER));
        $this->setupRootDbConnection();
      }
    }

    $this->createTemporaryTable();

    $PROTO = new TSSchema();
    $TEMPP = new TSNewSchema();

    // FILL TEMPORARY TABLE
    require_once('utils/BatchedDirListing.php');
    $vat = new BatchedDirListing($this->getUpDir());
    $vat->filterByRegexp('/^[0-9]{5}_.+\.sql$/');

    while (($batch = $vat->nextBatch()) !== false) {
      $objs = array();
      foreach ($batch as $name) {
        $obj = new TSNewSchema();
        $obj->id = $name;
        $objs[] = $obj;
      }
      DB::insertAll($objs);
    }

    // DOWNGRADE FIRST
    $res = DB::getAll(
      $PROTO,
      new DBCondIn('id', DB::prepGetAll($TEMPP, null, array('id')), DBCondIn::NOT_IN)
    );
    foreach ($res as $version) {
      $this->runDowngrade($version);
    }

    // UPGRADE NEXT
    $res = DB::getAll(
      $TEMPP,
      new DBCondIn('id', DB::prepGetAll($PROTO, null, array('id')), DBCondIn::NOT_IN)
    );
    foreach ($res as $version) {
      $this->runFile($version->id);
    }
  }

  /**
   * Issues all downgrades and removes DB entries
   *
   * @param TSSchema $schema the last entry to remove
   */
  public function downgradeThrough(TSSchema $schema) {
    if ($schema->id === null)
      throw new InvalidArgumentException("Schema to downgrade must not have empty ID.");

    // Get all schemas
    $res = DB::getAll(
      $schema,
      new DBCond('id', $schema->id, DBCond::GE)
    );
    foreach ($res as $version) {
      $this->runDowngrade($version);
    }

    self::errln(sprintf("Downgraded %d version(s).", count($res)));
  }

  private function schemaTableExists() {
    $obj = new TSSchema();
    $q = sprintf('show tables like "%%%s%%"', $obj->db_name());
    $c = DB::connection();
    $r = $c->query($q);
    return $r->num_rows > 0;
  }

  /**
   * Sets up DB_ROOT_USER connection 
   *
   */
  private function setupRootDbConnection() {
    if (!$this->root_connection_setup) {
      self::errln("Setting up root DB connection", 2);

      if ($this->root_password === null) {
        printf("MySQL User: %s\n", Conf::$DB_ROOT_USER);
        print("MySQL Password: ");
        system('stty -echo');
        $this->root_password = trim(fgets(STDIN));
        system('stty echo');
        print("\n");
      }

      $con = DB::connection();
      $con->change_user(Conf::$DB_ROOT_USER, $this->root_password, Conf::$SQL_DB);
      $this->root_connection_setup = true;
    }
  }

  private function getUpDir() {
    return dirname(dirname(__DIR__)) . '/src/db/up';
  }

  private function getDownDir() {
    return dirname(dirname(__DIR__)) . '/src/db/down';
  }

  private function runFile($filename) {
    $dir = $this->getUpDir();
    $fullpath = $dir . '/' . $filename;
    if (!file_exists($fullpath))
      throw new RuntimeException("Unable to find path $fullpath");

    $this->setupRootDbConnection();

    $con = DB::connection();
    $con->commit();
    self::errln('UPGRADING: ' . $fullpath);
    $this->queryAll($con, file_get_contents($fullpath));
    self::errln("Completed file $fullpath", 2);

    $downpath = $this->getDownDir() . '/' . $filename;
    $downgrade = null;
    self::errln("Looking for downgrade version in $downpath", 3);
    if (file_exists($downpath)) {
      $downgrade = file_get_contents($downpath);
      self::errln("Found corresponding downgrade: $downpath", 2);
    }

    self::errln("Saving $fullpath in _schema_", 2);
    $obj = TSSchema::create($filename, $downgrade);
    self::errln(sprintf("Saved in _schema_ with ID=%s and timestamp=%s",
                        $obj->id, $obj->performed_at->format('Y-m-d H:i:s')), 2);
    self::errln('UPGRADE DONE: ' . $fullpath);
    $con->commit();
  }

  private function runDowngrade(TSSchema $schema) {
    $this->setupRootDbConnection();

    $con = DB::connection();
    $con->commit();
    self::errln("Downgrade file " . $schema->id, 2);
    if ($schema->downgrade !== null) {
      self::errln('DOWNGRADING: ' . $schema->id);
      $this->queryAll($con, $schema->downgrade);
    }
    self::errln("Completed downgrade " . $schema->id, 2);

    self::errln(sprintf("Deleting %s from _schema_", $schema->id), 2);
    DB::remove($schema);
    self::errln('DOWNGRADE DONE: ' . $schema->id);
    $con->commit();
  }

  private function existsLocalFile(TSSchema $schema) {
    $file = $schema->id;
    $file = $this->getDir() . '/' . $file;
    return is_file($file);
  }

  private function createTemporaryTable() {
    $q = sprintf('CREATE TEMPORARY TABLE _schema_new_ (id VARCHAR(100) NOT NULL PRIMARY KEY) engine=InnoDB');
    $con = DB::connection();
    $r = $con->query($q);
    if ($con->errno != 0)
      throw new RuntimeException(sprintf("Error while creating temporary table (%s): %s", $con->errno, $con->error));
  }

  /**
   * Helper method to perform all concatenated queries
   *
   * @param DBConnection $con the connection to use
   * @param String $queries the queries
   * @throws TSScriptException with particular failed query
   */
  private function queryAll(DBConnection $con, $queries) {
    $con->multi_query($queries);
    $i = 0;
    do {
      if ($result = $con->store_result()) {
        // TODO: Support for returned data?
        $result->free();
      } elseif ($con->errno != 0) {
        $mes = sprintf("Error on query #%d:\n  %s: %s", ($i + 1), $con->errno, $con->error);
        $con->rollback();
        throw new TSScriptException($mes);
      }

      $i++;
    } while ($con->more_results() && $con->next_result());
    if ($con->errno != 0) {
      $mes = sprintf("Error on query #%d:\n  %s: %s", ($i + 1), $con->errno, $con->error);
      $con->rollback();
      throw new TSScriptException($mes);
    }
    self::errln("Ran $i queries", 3);
  }

  /**
   * Determine whether current connection can create temporary tables
   *
   * Performs this check by attempting to create a temporary table
   */
  private function hasCreateTemporaryTablePrivilege() {
    $con = DB::connection();
    $nam = uniqid();
    $qry = sprintf('create temporary table %s (id tinyint not null primary key)', $nam);
    $res = $con->query($qry);
    if ($res) {
      $qry = sprintf('drop table %s', $nam);
      $con->query($qry);
    }
    return $res;
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new MigrateDB();
  $opts = $P->getOpts($argv);
  $quiet = false;
  $downgrade = null;
  while (count($opts) > 0) {
    $opt = array_shift($opts);
    if ($opt == '-n' || $opt == '--dry-run')
      $P->setDryRun(true);
    elseif ($opt == '-q' || $opt == '--quiet')
      $quiet = true;
    elseif ($opt == '--down') {
      if (count($opts) == 0)
        throw new TSScriptException("Missing argument for --down");
      $arg = array_shift($opts);
      $downgrade = DB::get(new TSSchema(), $arg);
      if ($downgrade === null) {
        $matches = DB::getAll(new TSSchema(), new DBCond('id', '%' . $arg . '%', DBCond::LIKE));
        if (count($matches) != 1)
          throw new TSScriptException("Invalid ID provided for downgrade: $arg");
        $downgrade = $matches[0];
      }
    }
    else {
      throw new TSScriptException("Invalid option provided: $opt");
    }
  }
  if (!$quiet)
    MigrateDB::setVerbosity(max(1, MigrateDB::getVerbosity()));

  if ($downgrade !== null)
    $P->downgradeThrough($downgrade);
  else
    $P->run();
}
?>