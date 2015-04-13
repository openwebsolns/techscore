<?php
namespace MyORM;

/**
 * Database connection. Subclasses MySQLi
 *
 * @author Dayan Paez
 * @version 2012-10-08
 */
class DBConnection extends \MySQLi {
  /**
   * Opens connection, sets UTF-8, and autocommit to false
   *
   */
  public function __construct($host = null, $user = null, $pass = null, $db = null, $port = null, $socket = null) {
    parent::__construct($host, $user, $pass, $db, $port, $socket);
    $this->set_charset('utf8');
    $this->autocommit(false);
  }

  /**
   * Automatically commits the transaction
   *
   */
  public function __destruct() {
    $this->commit();
  }

  /**
   * Automatically commits pending transactions
   *
   */
  public function close() {
    $this->commit();
    parent::close();
  }
}
?>