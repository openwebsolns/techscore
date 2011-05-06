<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Generates the all-american report, hopefully
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class AllAmerican extends AbstractAdminUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(User $user) {
    parent::__construct("All-American", $user);
    if (!isset($_SESSION['aa']))
      $_SESSION['aa'] = array('regattas' => array(),
			      'sailors' => array(),
			      'params-set' => false);
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // 1. Step one: choose schools
    // ------------------------------------------------------------
    if (empty($_SESSION['aa']['regattas'])) {
      $this->PAGE->addContent($p = new Port("Choose regattas"));
      $season = new Season(new DateTime());
      $regattas = $season->getRegattas();
      if (count($regattas) == 0) {
	$p->addChild("There are no regattas in the current season.");
	return;
      }
      
      $p->addChild($form = new Form("/aa-edit"));
      $form->addChild(new Para("Choose the regattas you wish to include in the report from the list below."));
      $form->addChild($tab = new Table());
      $tab->addAttr('id', 'regtable');

      $types = Preferences::getRegattaTypeAssoc();
      $tab->addHeader(new Row(array(Cell::th(""),
				    Cell::th("Name"),
				    Cell::th("Type"),
				    Cell::th("Date"),
				    Cell::th("Status"))));
      foreach ($regattas as $reg) {
	$id = 'r'.$reg->id;
	$tab->addRow(new Row(array(new Cell($chk = new FCheckbox("regatta[]", $reg->id, array('id'=>$id))),
				   new Cell(new Label($id, $reg->name)),
				   new Cell(new Label($id, $types[$reg->type])),
				   new Cell(new Label($id, $reg->start_time->format('Y/m/d H:i'))),
				   new Cell(new Label($id, ($reg->finalized) ? "Final" : "Pending")))));
	if ($reg->finalized === null)
	  $chk->addAttr("disabled", "disabled");
      }
      $form->addChild(new Para("Next, choose the sailors to incorporate into the report."));
      $form->addChild(new FSubmit('set-regattas', "Choose sailors >>"));
    }

    // ------------------------------------------------------------
    // Choose sailors
    // ------------------------------------------------------------
    if ($_SESSION['aa']['params-set'] === false) {
      $analyzer = new ScoresAnalyzer($_SESSION['aa']['regattas']);
      // provide a list of sailors that meet criteria, and a search
      // box to add new ones (this latter bit might require some sort
      // of Javascript, for Ajax, no?
      $this->PAGE->addContent($p = new Port("Sailors in list"));
      $p->addChild(new Para("The following sailors meet the criteria for All-American inclusion. Use the bottom form to add more sailors to the list."));
      $p->addChild($item = new Itemize());
      
      $item->addItems($sub = new LItem("Top 5 in A division"));
      $sailors = $analyzer->getHighFinishers(Division::A(), 5);
      if (count($sailors) > 0) {
	$sub->addChild($sublist = new Itemize());
	foreach ($sailors as $sailor)
	  $sublist->addItems(new LItem($sailor));
      }

      $item->addItems($sub = new LItem("Top 4 in B division"));
      $sailors = $analyzer->getHighFinishers(Division::B(), 4);
      if (count($sailors) > 0) {
	$sub->addChild($sublist = new Itemize());
	foreach ($sailors as $sailor)
	  $sublist->addItems(new LItem($sailor));
      }

      $item->addItems($sub = new LItem("Top 4 in C division"));
      $sailors = $analyzer->getHighFinishers(Division::C(), 4);
      if (count($sailors) > 0) {
	$sub->addChild($sublist = new Itemize());
	foreach ($sailors as $sailor)
	  $sublist->addItems(new LItem($sailor));
      }

      $item->addItems($sub = new LItem("Top 4 in D division"));
      $sailors = $analyzer->getHighFinishers(Division::D(), 4);
      if (count($sailors) > 0) {
	$sub->addChild($sublist = new Itemize());
	foreach ($sailors as $sailor)
	  $sublist->addItems(new LItem($sailor));
      }
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Choose regattas
    // ------------------------------------------------------------
    if (isset($args['set-regattas'])) {
      if (!isset($args['regatta']) || !is_array($args['regatta']) || count($args['regatta']) == 0) {
	$this->announce(new Announcement("Please choose at least one regatta.", Announcement::WARNING));
	return false;
      }
      $regs = array();
      $errors = 0;
      $_SESSION['aa']['regattas'] = array();
      foreach ($args['regatta'] as $id) {
	try {
	  $reg = new Regatta($id);
	  if ($reg->get(Regatta::TYPE) != Preferences::TYPE_PERSONAL &&
	      $reg->get(Regatta::FINALIZED) !== null)
	    $_SESSION['aa']['regattas'][] = $reg->id();
	  else
	    $errors++;
	}
	catch (Exception $e) {
	  $errors++;
	}
      }
      if ($errors > 0)
	$this->announce(new Announcement("Some regattas specified are not valid.", Announcement::WARNING));
      if (count($_SESSION['aa']['regattas']) > 0)
	$this->announce(new Announcement("Set regattas for All-American report."));
      return $args;
    }
    print_r($args);
    exit;
  }
}
?>