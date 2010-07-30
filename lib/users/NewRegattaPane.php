<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2
 * @date 2010-04-19
 */
require_once('conf.php');

/**
 * Create a new regatta
 *
 */
class NewRegattaPane extends AbstractUserPane {

  /**
   * Create a pane for creating regattas
   *
   * @param User $user the user creating the regatta
   */
  public function __construct(User $user) {
    parent::__construct("New regatta", $user);
  }
  
  private function defaultRegatta() {
    $day = new DateTime('next Saturday');
    return array("name"=>"", "start_date"=>$day->format('Y/m/d'), "start_time" => "10:00", "duration"=>2,
		 "venue"=>"", "scoring"=>"standard", "type"=>"conference",
		 "num_divisions"=>2, "num_races"=>"18");
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Create"));
    $p->addChild($f = new Form("create-edit"));

    $r = $this->defaultRegatta();
    // Replace with values from $args
    foreach ($r as $key => $value) {
      if (isset($args[$key]))
	$r[$key] = $args[$key];
    }

    $f->addChild(new FItem("Name:", new FText("name", $r["name"])));
    $f->addChild(new FItem("Start date:", new FText("start_date", $r["start_date"])));
    $f->addChild(new FItem("Start time:", new FText("start_time", $r["start_time"])));
    $f->addChild(new FItem("Duration (days):", new FText("duration", $r["duration"])));
    $f->addChild(new FItem("Venue:",   $sel = new FSelect("venue", array($r["venue"]))));
    $f->addChild(new FItem("Scoring:", $sco = new FSelect("scoring", array($r["scoring"]))));
    $f->addChild(new FItem("Type:",    $typ = new FSelect("type", array($r["type"]))));
    $f->addChild(new FItem("Divisions:",$div = new FSelect("num_divisions",  array($r["num_divisions"]))));
    $f->addChild(new FItem("Number of races:", new FText("num_races", $r["num_races"])));
    $f->addChild(new FSubmit("new-regatta", "Create"));

    // select
    $sel->addChild(new Option("", "[No venue]"));
    foreach (Preferences::getVenues() as $venue)
      $sel->addChild(new Option($venue->id, $venue));
    foreach (Preferences::getRegattaScoringAssoc() as $key => $value)
      $sco->addChild(new Option($key, $value));
    foreach (Preferences::getRegattaTypeAssoc() as $key => $value)
      $typ->addChild(new Option($key, $value));
    for ($i = 1; $i <= 4; $i++)
      $div->addChild(new Option($i, $i));
  }

  /**
   * Creates the new regatta
   *
   */
  public function process(Array $args) {
    if (isset($args['new-regatta'])) {
      $error = false;
      // 1. Check name
      if (!isset($args['name']) || addslashes(trim($args['name'])) == "") {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid (empty) name.", Announcement::ERROR);
	$error = true;
      }
      // 2. Check date
      if (!isset($args['start_date']) || strtotime($args['start_date']) === false) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid date given.", Announcement::ERROR);
	$error = true;
      }
      // 3. Check time
      if (!isset($args['start_time']) ||strtotime($args['start_time']) === false) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid start time.", Announcement::ERROR);
	$error = true;
      }
      // 4. Check duration
      if (!isset($args['duration']) || $args['duration'] < 1) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid duration.", Announcement::ERROR);
	$error = true;
      }
      // 5. Venue
      if (!empty($args['venue']) &&
	  Preferences::getVenue((int)$args['venue']) === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid venue.", Announcement::ERROR);
	$error = true;
      }
      // 6. Scoring
      $scoring = Preferences::getRegattaScoringAssoc();
      if (!isset($args['scoring']) ||
	  !isset($scoring[$args['scoring']])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid regatta type.", Announcement::ERROR);
	$error = true;
      }
      // 7. Type
      $type = Preferences::getRegattaTypeAssoc();
      if (!isset($args['type']) ||
	  !isset($type[$args['type']])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid regatta type.", Announcement::ERROR);
	$error = true;
      }
      // 8. Divisions
      if (!isset($args['num_divisions']) || $args['num_divisions'] < 1 || $args['num_divisions'] > 4) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid number of divisions.", Announcement::ERROR);
	$error = true;
      }
      // 9. Races
      if (!isset($args['num_races']) || $args['num_races'] < 1 || $args['num_races'] > 99) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid number of races.", Announcement::ERROR);
	$error = true;
      }

      // Submit?
      if ($error)
	return $args;

      // Move to new regatta
    }
    return array();
  }
}
?>