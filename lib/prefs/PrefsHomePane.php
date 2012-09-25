<?php
/**
 * Defines one class, the home page for preferences.
 *
 * @package prefs
 */

require_once('prefs/AbstractPrefsPane.php');

/**
 * PrefsHomePane: the gateway to preferences editing
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class PrefsHomePane extends AbstractPrefsPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(Account $usr) {
    parent::__construct("Preferences", $usr);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort(sprintf("Edit %s", $this->SCHOOL->nick_name)));
    $p->add(new XP(array(),
                   array("This is the portal for editing details about your school. Navigate around using the menu to the left. When you are done, use the ",
                         new XEm("Back"),
                         " link to return to your home page.")));

    $p->add(new XP(array(), "If you have any questions, send them to paez@mit.edu. Please note that this part of TechScore is still under development."));

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

      $this->PAGE->addContent($p = new XPort("Choose school"));
      $p->add(new XStyle('text/css', 'table.conf td { text-align: left; vertical-align: top; }'));
      $p->add(new XP(array(), "Choose a different school to edit from the list below."));

      $col = 0;
      foreach ($conferences as $conf => $schools) {
        $p->add($tab = new XDiv(array('class'=>'conf'), array(new XH4($conf), $list = new XUl())));
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
    throw new SoterException("This page does not process any requests.");
  }
}
?>