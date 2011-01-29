<?php
/**
 * Defines one class, the home page for preferences.
 *
 * @package prefs
 */

/**
 * PrefsHomePane: the gateway to preferences editing
 *
 * @author Dayan Paez
 * @created 2009-10-14
 */
class PrefsHomePane extends AbstractUserPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(User $usr, School $school) {
    parent::__construct("Preferences", $usr, $school);
    if (is_array($_SESSION)) $_SESSION['SCHOOL'] = $school->id;
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port(sprintf("Edit %s", $this->SCHOOL->nick_name)));
    $p->addChild(new Para("This is the portal for editing details about your school. " .
			  "Navigate around using the menu to the left. When you are " .
			  "done, use the <em>Back</em> link to return to your home page."));

    $p->addChild(new Para("If you have any questions, send them to paez@mit.edu. " .
			  "Please note that this part of <strong>TechScore" .
			  "</strong> is still under development."));

    // Allow for editing of other schools
    if ($this->USER->get(User::ADMIN)) {
      $this->PAGE->addContent($p = new Port("Choose school"));

      // Stylesheet fix
      $p->addChild($st = new GenericElement("style"));
      $st->addAttr("type", "text/css");
      $st->addChild(new Text('table.conf td { text-align: left; vertical-align: top; }'));

      $p->addChild(new Para("Choose a different school to edit from the list below."));
      $p->addChild($tab = new Table());
      $tab->addAttr("id", "conftable");
      
      $conferences = Preferences::getConferences();
      $count = count($conferences);
      for ($row = 0; $row <= count($conferences) / 4; $row++) {
	$tab->addRow($h = new Row());
	$tab->addRow($b = new Row());
	$tab->addAttr("class", "conf");

	$col = 0;
	while (($col + 4 * $row) < $count && $col < 4) {
	  $conf = $conferences[4 * $row + $col];
	  $h->addCell(Cell::th($conf));
	  $b->addCell(new Cell($list = new Itemize()));
	  foreach (Preferences::getSchoolsInConference($conf) as $school) {
	    if ($school != $this->SCHOOL) {
	      $link = sprintf("/prefs/%s", $school->id);
	      $list->addItems(new LItem(new Link($link, $school->nick_name)));
	    }
	  }
	  $col++;
	}
      }
    }
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    return;
  }
}
?>