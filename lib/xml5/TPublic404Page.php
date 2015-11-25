<?php
namespace xml5;

use \InvalidArgumentException;
use \DB;
use \DBCond;
use \STN;
use \Season;
use \Text_Entry;
use \TPublicPage;
use \XA;
use \XPort;
use \XRawText;

require_once('TPublicPage.php');

/**
 * Public 404 page supporting different modes.
 *
 * @author Dayan Paez
 * @version 2015-11-25
 */
class TPublic404Page extends TPublicPage {

  const MODE_GENERAL = 'general';
  const MODE_SCHOOL = 'school';

  public function __construct($mode) {
    parent::__construct(self::getTitle($mode));
    switch ($mode) {
    case self::MODE_GENERAL:
      $this->fillGeneral();
      break;

    case self::MODE_SCHOOL:
      $this->fillSchool();
      break;

    default:
      throw new InvalidArgumentException(
        sprintf("Unsupported mode specified: %s.", $mode)
      );
    }
  }

  private function fillGeneral() {
    $this->setDescription("Page not found. Possible linking or URL error.");

    // SETUP navigation
    $season = Season::forDate(DB::T(DB::NOW));
    $this->addMenu(new XA('/', "Home"));
    $this->addMenu(new XA('/schools/', "Schools"));
    $this->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null) {
      $this->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    }
    if (($n = DB::g(STN::ORG_NAME)) !== null && ($u = DB::g(STN::ORG_TEAMS_URL)) !== null) {
      $this->addMenu(new XA($u, sprintf("%s Teams", $n)));
    }
    if ($n !== null && ($u = DB::g(STN::ORG_URL)) !== null) {
      $this->addMenu(new XA($u, sprintf("%s Home", $n)));
    }

    $this->setHeader("404: File not found");
    $this->addSection($p = new XPort("Page overboard!"));
    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::GENERAL_404);
    if ($cont !== null) {
      $p->add(new XRawText($cont->html));
    }
  }

  private function fillSchool() {
    $this->setDescription("School page not found. Possible linking or URL error.");

    // SETUP navigation, get latest season
    $seasons = DB::getAll(DB::T(DB::SEASON), new DBCond('start_date', DB::T(DB::NOW), DBCond::LE));
    $season = null;
    if (count($seasons) > 0) {
      $season = $seasons[0];
    }

    $this->addMenu(new XA('/', "Home"));
    $this->addMenu(new XA('/schools/', "Schools"));
    $this->addMenu(new XA('/seasons/', "Seasons"));
    if ($season !== null) {
      $this->addMenu(new XA(sprintf('/%s', $season), $season->fullString()));
    }
    if (($lnk = $this->getOrgTeamsLink()) !== null)
      $this->addMenu($lnk);
    if (($lnk = $this->getOrgLink()) !== null)
      $this->addMenu($lnk);

    $this->setHeader("404: School page not found");
    $this->addSection($p = new XPort("Page overboard!"));
    $cont = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::SCHOOL_404);
    if ($cont !== null) {
      $p->add(new XRawText($cont->html));
    }
  }

  public static function getTitle($mode) {
    switch ($mode) {
    case self::MODE_SCHOOL:
      return "404: School page not found";
    default:
      return "404: Page not found";
    }
  }
}