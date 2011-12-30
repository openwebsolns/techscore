<?php
/**
 * Defines one class, the home page for preferences.
 *
 * @package prefs
 */

require_once('users/AbstractUserPane.php');

/**
 * PrefsHomePane: the gateway to preferences editing
 *
 * @author Dayan Paez
 * @version 2009-10-14
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
    $p->add(new Para("This is the portal for editing details about your school. " .
			  "Navigate around using the menu to the left. When you are " .
			  "done, use the <em>Back</em> link to return to your home page."));

    $p->add(new Para("If you have any questions, send them to paez@mit.edu. " .
			  "Please note that this part of <strong>TechScore" .
			  "</strong> is still under development."));

    // Allow for editing of other schools
    $schools = $this->USER->getSchools();
    if (count($schools) > 1) {
      // separate schools into conference list
      $conferences = array();
      foreach ($schools as $school) {
	if (!isset($conferences[$school->conference->id]))
	  $conferences[$school->conference->id] = array();
	$conferences[$school->conference->id][$school->id] = $school;
      }

      $this->PAGE->addContent($p = new Port("Choose school"));
      // Stylesheet fix
      $p->add($st = new GenericElement("style"));
      $st->set("type", "text/css");
      $st->add(new XText('table.conf td { text-align: left; vertical-align: top; }'));

      $p->add(new Para("Choose a different school to edit from the list below."));
      $p->add($tab = new Table());
      $tab->set("id", "conftable");
      $tab->set("class", "conf");
      
      $col = 0;
      foreach ($conferences as $conf => $schools) {
	if ($col++ % 4 == 0) {
	  $tab->addRow($h = new Row());
	  $tab->addRow($b = new Row());
	  
	}

	$h->addCell(Cell::th($conf));
	$b->addCell(new Cell($list = new XUl()));
	foreach ($schools as $school) {
	  if ($school != $this->SCHOOL)
	    $link = new XA(sprintf("/prefs/%s", $school->id), $school->nick_name);
	  else
	    $link = new XSpan($school->nick_name);
	  $list->add(new XLi($link));
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