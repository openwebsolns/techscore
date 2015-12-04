<?php
namespace scripts;

use \pub\SchoolsSummaryReportMaker;
use \writers\AbstractWriter;

use \AbstractUnitTester;
use \Writeable;
use \XElem;

/**
 * Test the update school summary.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class UpdateSchoolsSummaryTest extends AbstractUnitTester {

  private $testObject;
  private $writer;
  private $maker;

  protected function setUp() {
    $this->maker = new UpdateSchoolsSummaryTestSchoolsSummaryReportMaker();
    $this->writer = new UpdateSchoolsSummaryTestWriter();
    UpdateSchoolsSummary::setWriters(array($this->writer));

    $this->testObject = new UpdateSchoolsSummary();
    $this->testObject->setSchoolSummaryReportMaker($this->maker);
  }

  public function testRunSchools() {
    $this->testObject->runSchools();

    $this->assertEmpty($this->writer->getRemoved());
    $written = $this->writer->getWritten();
    $this->assertCount(1, $written);
    $page = $written[0]['elem'];
    $this->assertNotNull($page);
  }

  public function testRunConferences() {
    $this->testObject->runConferences();

    $this->assertEmpty($this->writer->getRemoved());
    $written = $this->writer->getWritten();
    $this->assertCount(1, $written);
    $page = $written[0]['elem'];
    $this->assertNotNull($page);
  }

  public function testRunSailors() {
    $this->testObject = new UpdateSchoolsSummary();
    $this->testObject->setRunSailors(true);
    $this->testObject->runSailors();

    $this->assertEmpty($this->writer->getRemoved());
    $written = $this->writer->getWritten();
    $this->assertCount(1, $written);
    $page = $written[0]['elem'];
    $this->assertNotNull($page);
  }

  public function testSkipRunSailors() {
    $this->testObject->setRunSailors(false);
    $this->testObject->runSailors();

    $this->assertEmpty($this->writer->getRemoved());
    $this->assertEmpty($this->writer->getWritten());
  }

  public function testRun() {
    $this->testObject->run();

    $elem = $this->maker->getElem();
    $written = $this->writer->getWritten();
    foreach ($written as $args) {
      $this->assertEquals($elem, $args['elem']);
    }
  }
}

/**
 * Mock writer for verification.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class UpdateSchoolsSummaryTestWriter extends AbstractWriter {

  private $written = array();
  private $removed = array();

  public function write($fname, Writeable $elem) {
    $this->written[] = array(
      'fname' => $fname,
      'elem' => $elem,
    );
  }

  public function remove($fname) {
    $this->removed[] = array(
      'fname' => $fname,
    );
  }

  public function getWritten() {
    return $this->written;
  }

  public function getRemoved() {
    return $this->removed;
  }
}

class UpdateSchoolsSummaryTestSchoolsSummaryReportMaker extends SchoolsSummaryReportMaker {

  private $elem;

  public function __construct() {
    $this->elem = new XElem('elem');
  }

  public function getSchoolsPage() {
    return $this->elem;
  }

  public function getConferencesPage() {
    return $this->elem;
  }

  public function getSailorsPage() {
    return $this->elem;
  }

  public function getElem() {
    return $this->elem;
  }
}