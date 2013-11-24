<?php
/**
 * Defines one class, the editor page for school's team names.
 *
 * @package prefs
 */

require_once('prefs/AbstractPrefsPane.php');

/**
 * TeamNamePrefsPane: editing the valid school names to use
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class TeamNamePrefsPane extends AbstractPrefsPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param Account $usr the user
   */
  public function __construct(Account $usr, School $school) {
    parent::__construct("Squad names", $usr, $school);
    $this->page_url = 'team';
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Set squad names"));
    $p->add(new XP(array(), "Every school consists of at least one squad. Enter all possible squad names (usually a variation of the school's mascot) in the list below. There may be a squad name for coed teams, and a different name for women teams. Or a scshool may have a varsity and junior varisty combination, etc."));
    $p->add(new XP(array(),
                   array("When a team from this school is added to a regatta, the ", new XStrong("primary"), " squad name (first on the list below) will be chosen automatically. Later, the scorer or the school's coach may choose an alternate name from those specified in the list below.")));
    $p->add(new XP(array(), "The squad names should all be different. Squad names may not be differentiated with the simple addition of a numeral suffix."));

    $p->add($form = $this->createForm());

    // Fill form
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), array("", "Name")));
    $names = $this->SCHOOL->getTeamNames();
    $first = (count($names) > 0) ? $names[0] : "";
    // First row
    $tab->addRow(array("Primary", new XTextInput("name[]", $first, array("maxlength"=>20))),
                 array('style'=>'background:#EEEEEE;font-weight:bold'));

    // Next four
    for ($i = 1; $i < count($names) + 5; $i++) {
      $name = (isset($names[$i])) ? $names[$i] : "";
      $tab->addRow(array("", new XTextInput("name[]", $name, array("maxlength"=>20))));
    }

    // Submit
    $form->add(new XSubmitP('set-names', "Enter names"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    if (isset($args['set-names'])) {
      $list = DB::$V->reqList($args, 'name', null, "No list of names provided.");
      if (count($list) == 0)
        throw new SoterException("There must be at least one team name, none given.");

      $re = '/ [0-9]+$/';

      // There must be a valid primary name
      $pri = trim(array_shift($list));
      if (strlen($pri) == 0)
        throw new SoterException("Primary team name must not be empty.");

      if (preg_match($re, $pri) > 0)
        throw new SoterException(sprintf("Invalid team name \"%s\": no numeral suffixes allowed.", $pri));

      $names = array($pri => $pri);
      $repeats = false;
      foreach ($list as $name) {
        $name = trim($name);
        if (strlen($name) > 0) {
          if (isset($names[$name]))
            $repeats = true;
          else {
            if (preg_match($re, $name) > 0)
              throw new SoterException(sprintf("Invalid team name \"%s\": no numeral suffixes allowed.", $name));
            $names[$name] = $name;
          }
        }
      }

      // Update the team names
      $curr = $this->SCHOOL->getTeamNames();
      $this->SCHOOL->setTeamNames(array_values($names));
      Session::pa(new PA("Team name preferences updated."));
      if ($repeats)
        Session::pa(new PA("Team names cannot be repeated.", PA::I));

      // First time? Update previous instances
      if (count($curr) == 0) {
        $new_name = array_shift($names);
        $reg_names = array();
        $re = sprintf('/^%s( [0-9]+)?$/', $this->SCHOOL->nick_name);
        foreach ($this->SCHOOL->getRegattas() as $reg) {
          $changed = false;
          foreach ($reg->getTeams($this->SCHOOL) as $team) {
            if (preg_match($re, $team->name) > 0) {
              $team->name = str_replace($this->SCHOOL->nick_name, $new_name, $team->name);
              DB::set($team);
              $changed = true;
            }
          }
          if ($changed) {
            require_once('public/UpdateManager.php');
            UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_TEAM, $this->SCHOOL->id);
            $reg_names[] = sprintf("%s (%s)", $reg->name, $reg->getSeason());
          }
        }

        $count = count($reg_names);
        if ($count > 0) {
          if ($count <= 5)
            Session::pa(new PA(sprintf("Updated the following regattas with the new team name: %s.",
                                       implode(", ", $reg_names))));
          else
            Session::pa(new PA(sprintf("Updated %d public regattas with new preferred name.", $count)));
        }
      }
    }
  }
}
?>