<?php
namespace MyORM;

/**
 * Interface for delegate actions
 *
 * @author Dayan Paez
 * @version 2010-05-14
 */
interface DBDelegatable {
  
  /**
   * This method is responsible for fetching the current value from
   * the DBDelegate, formatting it as required, and returning the
   * result. Instance methods could, for instance, return the result
   * as an object of a specific type, by calling the appropriate
   * <code>fetch_object</code> method in DBDelegate
   *
   * @param DBDelegate $pointer the pointer at which to fetch the
   * current value
   * @return mixed the resulting object or array
   */
  public function delegate_current(\MySQLi_Result $pointer);
}
?>