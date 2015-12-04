<?php
namespace pub;

use \TPublicPage;

use \DB;
use \STN;

use \XA;
use \XPort;
use \XQuickTable;
use \XTD;

require_once('xml5/TPublicPage.php');

/**
 * Public profile page generator for a given school
 *
 * @author Dayan Paez
 * @created 2015-01-24
 */
class SchoolsSummaryReportMaker {

  private $are_conferences_published;

  private $schoolsPage;
  private $sailorsPage;

  /**
   * Creates a new report maker for school summaries
   *
   */
  public function __construct() {
    $this->are_conferences_published = (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) !== null);
  }

  public function getSchoolsPage() {
    $this->fillSchoolsPage();
    return $this->schoolsPage;
  }

  public function getConferencesPage() {
    return $this->getSchoolsPage();
  }

  public function getSailorsPage() {
    $this->fillSailorsPage();
    return $this->sailorsPage;
  }

  private function fillSchoolsPage() {
    if ($this->schoolsPage === null) {
      $this->schoolsPage = $this->createPage();
    }
  }

  private function fillSailorsPage() {
    if ($this->sailorsPage === null) {
      $this->sailorsPage = $this->createPage();
    }
  }

  private function createPage() {
    $page = new TPublicPage("All Schools");
    $page->body->set('class', 'school-summary-page');
    $desc = "Listing of schools, with regatta participation.";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $desc = sprintf("Listing of schools in %s, with regatta participation.", $n);
    $page->setDescription($desc);
    $page->addMetaKeyword("schools");

    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    if (($lnk = $page->getOrgTeamsLink()) !== null)
      $page->addMenu($lnk);
    if (($lnk = $page->getOrgLink()) !== null)
      $page->addMenu($lnk);

    $confs = DB::getAll(DB::T(DB::CONFERENCE));
    $num_schools = 0;
    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    foreach ($confs as $conf) {
      $title = $conf . " " . DB::g(STN::CONFERENCE_TITLE);
      if ($this->are_conferences_published)
        $title = new XA($conf->url, $title);
      $p = new XPort($title);
      $p->set('id', $conf);
      $p->add($tab = new XQuickTable(array('class'=>'schools-table'), array("Mascot", "School", "City", "State")));

      $schools = $conf->getSchools();
      foreach ($schools as $i => $school) {
        $num_schools++;
        $link = $school->getURL();

        $burg = $school->drawSmallBurgee("");
        $tab->addRow(array(new XTD(array('class'=>'burgeecell'), $burg),
                           new XTD(array('class'=>'schoolname'), new XA($link, $school->name)),
                           $school->city,
                           $school->state),
                     array('class'=>'row'.($i%2)));
      }

      if (count($schools) > 0) {
        $page->addSection($p);
      }
    }

    $header = DB::g(STN::CONFERENCE_TITLE) . "s";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $header = sprintf("%s %s", $n, $header);
    $page->setHeader($header, array(sprintf("# of %ss", DB::g(STN::CONFERENCE_TITLE)) => count($confs), "# of Schools" => $num_schools));

    return $page;
  }
}