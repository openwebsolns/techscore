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
    DBME::setConnection(DB::connection());

    $page->addNavigation(new XA('http://collegesailing.info/teams', 'ICSA Info', array('class'=>'nav')));
    $confs = DB::getAll(DB::$CONFERENCE);
    foreach ($confs as $conf)
      $page->addMenu(new XA('#'.$conf, $conf));
    $page->addSection($d = new XDiv(array('id'=>'reg-details')));
    $d->add(new XH2("ICSA Conferences"));
    $d->add(new XUl(array(), array(new XLi(new XImg('/inc/img/icsa.png', "ICSA Burgee")))));;

    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    foreach ($confs as $conf) {
      $page->addSection($p = new XPort($conf));
      $p->set('id', $conf);
      $p->add(new XTable(array(),
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
        $q = DBME::prepGetAll(DB::$DT_TEAM);
        $q->fields(array('regatta'), DB::$DT_TEAM->db_name());
        $q->where(new DBCond('school', $school->id));

	$link = sprintf('/schools/%s', $school->id);
	$cnt  = count(DBME::getAll(DB::$DT_REGATTA, new DBCondIn('id', $q)));

	$burg = "";
	$path = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $school->id);
	if (file_exists($path))
	  $burg = new XImg(sprintf('/inc/img/schools/%s.png', $school->id), $school->id, array('height'=>40));

	$tab->add(new XTR(array('class'=>'row'.($i%2)),
			  array(new XTD(array(), $burg),
				new XTD(array(), new XA($link, $school->name)),
				new XTD(array(), $school->id),
				new XTD(array(), $school->city),
				new XTD(array(), $school->state),
				new XTD(array(), $cnt))));
      }
    }

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
