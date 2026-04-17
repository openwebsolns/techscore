<?php
namespace users;

use \utils\HttpResponse;

use \Account;
use \DB;
use \SoterException;

use \XDoc;
use \XElem;
use \XText;

/**
 * Searches for a given sailor in the database, returning the record
 *
 * @author Dayan Paez
 * @created 2012-10-12
 */
class SearchSailor extends AbstractUserPane {
  public function __construct(Account $user = null) {
    parent::__construct("Fetch sailors", $user);
  }

  /**
   * Overrides parent method to return XML document, instead of
   * TScorePage
   *
   */
  public function processGET(Array $args): HttpResponse {
    if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
      try {
        $query = DB::$V->reqString($args, 'q', 1, 16000, "Please provide a query (GET=q).");
        if (strlen($query) < 3)
          throw new SoterException("Please provide a valid search query (3 or more characters).");
        $results = DB::searchSailors($query, true);
        $resp = array();
        foreach ($results as $result)
          $resp[] = array('id' => $result->id,
                          'first_name' => $result->first_name,
                          'last_name' => $result->last_name,
                          'year' => $result->year,
                          'gender' => $result->gender,
                          'school' => $result->school->name);

        return HttpResponse::ok(json_encode($resp), ['Content-type' => 'application/json']);
      } catch (SoterException $e) {
        $a = array('error'=>$e->getMessage());
        return HttpResponse::badRequest(json_encode($a), ['Content-Type' => 'application/json']);
      }
    }

    $P = new XDoc('SailorSearch', array('version'=>'1.0'));

    // Validate input
    try {
      $query = DB::$V->reqString($args, 'q', 1, 16000, "Please provide a query (GET=q).");
      if (strlen($query) < 3)
        throw new SoterException("Please provide a long enough query (3 or more characters.");
      $results = DB::searchSailors($query, true);
      $P->set('count', count($results));
      foreach ($results as $result) {
        $P->add(new XElem('Sailor', array('id'=>$result->id, 'external_id'=>$result->external_id),
                          array(new XElem('FirstName', array(), array(new XText($result->first_name))),
                                new XElem('LastName',  array(), array(new XText($result->last_name))),
                                new XElem('Year',      array(), array(new XText($result->year))),
                                new XElem('Gender',    array(), array(new XText($result->gender))),
                                new XElem('School',    array('id' => $result->school->id),
                                          array(new XText($result->school->name))))));
      }

      return HttpResponse::ok($P->toXML());
    } catch (SoterException $e) {
      $P->set('count', -1);
      $P->add(new XElem('Error', array(), array(new XText($e->getMessage()))));
      return HttpResponse::badRequest($P->toXML());
    }
  }

  protected function fillHTML(Array $args) {}
  public function process(Array $args) {
    throw new SoterException("Searching does not accept POST requests.");
  }
}
