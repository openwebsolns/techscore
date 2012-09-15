<?php
/**
 * The page that summarizes the schools in the system, along with the
 * number of regattas they have participated in.
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

    $page->addMenu(new XA(Conf::$ICSA_HOME, "ICSA Home"));
    $page->addMenu(new XA('/schools/', "Schools"));
    $page->addMenu(new XA('/seasons/', "Seasons"));
    $page->addMenu(new XA(Conf::$ICSA_HOME . '/teams/', "ICSA Teams"));
    $page->addMenu(new XA('http://www.collegesailing.org/about/', "About"));

    $confs = DB::getAll(DB::$CONFERENCE);
    $num_schools = 0;
    $num_regattas = 0;
    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    foreach ($confs as $conf) {
      $page->addSection($p = new XPort($conf . " Conference"));
      $p->set('id', $conf);
      $p->add(new XTable(array('class'=>'schools-table'),
			 array(new XTHead(array(),
					  array(new XTR(array(),
							array(new XTH(array(), "Mascot"),
							      new XTH(array(), "School"),
							      new XTH(array(), "Code"),
							      new XTH(array(), "City"),
							      new XTH(array(), "State"),
							      new XTH(array(), "# Regattas"))))),
			       $tab = new XTBody())));
      
      foreach ($conf->getSchools() as $i => $school) {
	$num_schools++;
        $q = DB::prepGetAll(DB::$DT_TEAM);
        $q->fields(array('regatta'), DB::$DT_TEAM->db_name());
        $q->where(new DBCond('school', $school->id));

	$link = sprintf('/schools/%s', $school->id);
	$cnt  = count(DB::getAll(DB::$DT_REGATTA, new DBCondIn('id', $q)));
	$num_regattas += $cnt;

	$burg = "";
	$path = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $school->id);
	if (file_exists($path))
	  $burg = new XImg(sprintf('/inc/img/schools/%s.png', $school->id), $school->id, array('height'=>40));

	$tab->add(new XTR(array('class'=>'row'.($i%2)),
			  array(new XTD(array('class'=>'burgeecell'), $burg),
				new XTD(array('class'=>'schoolname'), new XA($link, $school->name)),
				new XTD(array(), $school->id),
				new XTD(array(), $school->city),
				new XTD(array(), $school->state),
				new XTD(array(), $cnt))));
      }
    }

    $page->setHeader("ICSA Conferences", array("# of Conferences" => count($confs),
					       "# of Schools" => $num_schools,
					       "Participation" => $num_regattas));

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
