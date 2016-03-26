<?php
namespace users;

use \DB;
use \SoterException;

/**
 * Fetch the latest burgee for a given school.
 *
 * @author Dayan Paez
 * @version 2016-03-25
 */
class BurgeePane extends AbstractUserPane {

  const INPUT_BURGEE = 'burgee';

  public function __construct() {
    parent::__construct("Burgee");
  }

  /**
   * Expect INPUT_BURGEE to be part of args.
   *
   * @param Array $args include the name of burgee (sans path) requested.
   */
  public function processGET(Array $args) {
    if (!array_key_exists(self::INPUT_BURGEE, $args)) {
      $this->do404();
      return;
    }
    $name = basename($args[self::INPUT_BURGEE], '.png');
    $id = $name;
    $prop = 'burgee';
    if (substr($name, -3) == '-40') {
      $id = substr($name, 0, strlen($name) - 3);
      $prop = 'burgee_small';
    }
    if (($school = DB::getSchool($id)) === null || $school->$prop === null) {
      $this->do404();
      return;
    }

    // Cache headings
    header("Cache-Control: public");
    header("Pragma: public");
    header("Content-Type: image/png");
    header("Expires: Sun, 21 Jul 2030 14:08:53 -0400");
    header(sprintf("Last-Modified: %s", $school->$prop->last_updated->format('r')));
    echo base64_decode($school->$prop->filedata);
  }

  public function fillHTML(Array $args) {}

  private function do404() {
    http_response_code(404);
    echo "Invalid school or burgee requested.";
  }

  public function process(Array $args) {
    throw new SoterException("Method not supported.");
  }
}