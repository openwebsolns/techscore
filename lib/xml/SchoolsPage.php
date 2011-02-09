<?php
/**
 * The page that summarizes the school's in the system, along with the
 * number of regattas they have participated in.
 *
 * @author Dayan Paez
 * @created 2011-02-08
 * @package www
 */
require_once(dirname(__FILE__).'/../conf.php');
class SchoolsPage extends TPublicPage {
  
  /**
   * Create the new school summary page
   */
  public function __construct() {
    parent::__construct("All Schools");

    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());

    $this->addNavigation(new Link('http://collegesailing.info/teams', 'ICSA Info', array('class'=>'nav')));
    $confs = DBME::getAll(DBME::$CONFERENCE);
    foreach ($confs as $conf)
      $this->addMenu(new Link('#'.$conf, $conf));
    $this->addSection($d = new Div());
    $d->addChild(new GenericElement('h2', array(new Text("ICSA Conferences"))));
    $d->addChild($l = new Itemize());
    $l->addItems(new LItem(new Image(sprintf('/inc/img/icsa.png'), array('alt'=>"ICSA Burgee"))));
    $d->addAttr('align', 'center');
    $d->addAttr('id', 'reg-details');

    // ------------------------------------------------------------
    // Summary of each conference
    // count the number of regattas this school has teams in
    $q = DBME::prepGetAll(DBME::$TEAM);
    $q->fields(array('regatta'), DBME::$TEAM->db_name());
    foreach ($confs as $conf) {
      $this->addSection($p = new Port($conf));
      $p->addAttr('id', $conf);
      $p->addChild($tab = new Table());
      $tab->addHeader(new Row(array(Cell::th("Mascot"),
				    Cell::th("School"),
				    Cell::th("Code"),
				    Cell::th("City"),
				    Cell::th("State"),
				    Cell::th("# Regattas"))));
      foreach (DBME::getAll(DBME::$SCHOOL, new MyCond('conference', $conf->id)) as $school) {
	$link = sprintf('/schools/%s', $school->id);
	$cnt  = count(DBME::getAll(DBME::$REGATTA, new MyCondIn('id', $q)));

	$burg = new Text("");
	$path = sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $school->id);
	if (file_exists($path))
	  $burg = new Image(sprintf('/inc/img/schools/%s.png', $school->id),
			    array('alt'=>$school->id,
				  'height'=>'40px'));

	$tab->addRow(new Row(array(new Cell($burg),
				   new Cell(new Link($link, $school->name)),
				   new Cell($school->id),
				   new Cell($school->city),
				   new Cell($school->state),
				   new Cell($cnt))));
      }
    }
  }
}

if (isset($argv) && basename($argv[0]) == basename(__FILE__)) {
  $_SERVER['HTTP_HOST'] = 'cli';
  require_once('../conf.php');

  $p = new SchoolsPage();
  $f = sprintf('%s/../../html/schools/index.html', dirname(__FILE__));
  file_put_contents($f, $p->toHTML());
}
?>