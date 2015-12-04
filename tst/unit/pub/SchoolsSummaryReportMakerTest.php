<?php
namespace pub;

use \AbstractUnitTester;

/**
 * Test the report maker.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class SchoolsSummaryReportMakerTest extends AbstractUnitTester {

  public function testGetConferencesAndSchoolsPage() {
    $testObject = new SchoolsSummaryReportMaker();
    $page = $testObject->getConferencesPage();
    $this->assertNotNull($page);
  }

  public function testGetSailorsPage() {
    $testObject = new SchoolsSummaryReportMaker();
    $page = $testObject->getSailorsPage();
    $this->assertNotNull($page);
  }

}