<?php
/**
 * The page that summarizes the schools in the system, along with the
 * number of regattas they have participated in.
 *
 * @author Dayan Paez
 * @created 2011-02-08
 * @package www
 */
class UpdateSchoolsSummary {

  public static function run() {
    $page = new TPublicPage("All Schools");
    
    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());

    $page->addNavigation(new XA('http://collegesailing.info/teams', 'ICSA Info', array('class'=>'nav')));
    $confs = DBME::getAll(DBME::$CONFERENCE);
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
      
      foreach (DBME::getAll(DBME::$SCHOOL, new MyCond('conference', $conf->id)) as $i => $school) {
        $q = DBME::prepGetAll(DBME::$TEAM);
        $q->fields(array('regatta'), DBME::$TEAM->db_name());
        $q->where(new MyCond('school', $school->id));

	$link = sprintf('/schools/%s', $school->id);
	$cnt  = count(DBME::getAll(DBME::$REGATTA, new MyCondIn('id', $q)));

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
  $_SERVER['HTTP_HOST'] = 'cli';
  require_once(dirname(__FILE__) . '/../conf.php');

  UpdateSchoolsSummary::run();
}
?>
