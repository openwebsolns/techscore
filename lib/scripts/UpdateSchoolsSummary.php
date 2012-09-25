<?php
/**
 * This file is part of TechScore
 */

/**
 * The page that summarizes the schools in the system.
 *
 * @author Dayan Paez
 * @version 2011-02-08
 * @package www
 */
class UpdateSchoolsSummary {

  public static function run() {
    require_once('xml5/TPublicPage.php');
    require_once('regatta/PublicDB.php');
    $page = new TPublicPage("All Schools");
    $page->setDescription("Listing of schools in ICSA, with regatta participation.");
    $page->addMetaKeyword("schools");

    $page->addMenu(new XA('/', "Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));

    $confs = DB::getAll(DB::$CONFERENCE);
    $num_schools = 0;
    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    foreach ($confs as $conf) {
      $page->addSection($p = new XPort($conf . " Conference"));
      $p->set('id', $conf);
      $p->add($tab = new XQuickTable(array('class'=>'schools-table'), array("Mascot", "School", "City", "State")));

      foreach ($conf->getSchools() as $i => $school) {
        $num_schools++;
        $link = sprintf('/schools/%s', $school->id);

        $burg = "";
        $path = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $school->id);
        if (file_exists($path))
          $burg = new XImg(sprintf('/inc/img/schools/%s.png', $school->id), $school->id, array('height'=>40));

        $tab->addRow(array(new XTD(array('class'=>'burgeecell'), $burg),
                           new XTD(array('class'=>'schoolname'), new XA($link, $school->name)),
                           $school->city,
                           $school->state),
                     array('class'=>'row'.($i%2)));
      }
    }

    $page->setHeader("ICSA Conferences", array("# of Conferences" => count($confs), "# of Schools" => $num_schools));

    // Write to file!
    $f = sprintf('%s/../../html/schools/index.html', dirname(__FILE__));
    file_put_contents($f, $page->toXML());
  }
}

if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(__FILE__) . '/../conf.php');
  UpdateSchoolsSummary::run();
}
?>
