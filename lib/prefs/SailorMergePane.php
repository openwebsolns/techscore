<?php
/**
 * Defines one class, the editor page for merging sailors
 *
 * @package prefs
 */

require_once('prefs/AbstractPrefsPane.php');

/**
 * SailorMergePane: editor pane to merge the unsorted sailors from a
 * given school with those in the actual database.
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class SailorMergePane extends AbstractPrefsPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param Account $usr the user
   */
  public function __construct(Account $usr) {
    parent::__construct("Sailors", $usr);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Merge temporary sailors"));
    $p->add(new XP(array(), "When a sailor is not found in the database, the scorers can add the sailor temporarily. These temporary sailors appear throughout TechScore with an asterisk next to their name."));

    $p->add(new XP(array(), "It is the school's responsibilities to match the temporary sailors with the actual sailor from the ICSA database once the missing sailor has been approved."));

    $p->add(new XP(array(), "Use this form to update the database by matching the temporary sailor with the actual one from the ICSA database. If the sailor does not appear, he/she may have to be approved by ICSA before the changes are reflected in TechScore. Also, bear in mind that TechScore's copy of the ICSA membership database might lag ICSA's copy by as much as a week."));

    // Get all the temporary sailors
    $temp = $this->SCHOOL->getUnregisteredSailors();
    if (empty($temp)) {
      $p->add(new XP(array('class'=>'strong center'), "No temporary sailors for this school."));
      return;
    }

    $p->add($form = new XForm(sprintf('/prefs/%s/sailor', $this->SCHOOL->id), XForm::POST));
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), array("Temporary sailor", "ICSA Match")));

    // Create choices
    $sailors = $this->SCHOOL->getSailors();
    $choices = array("" => "", "Sailors"=>array(), "Coaches"=>array());
    foreach ($sailors as $sailor)
      $choices["Sailors"][$sailor->id] = (string)$sailor;
    foreach ($this->SCHOOL->getCoaches('all', true) as $sailor)
      $choices["Coaches"][$sailor->id] = (string)$sailor;

    foreach ($temp as $sailor) {
      $tab->addRow(array($sailor, XSelect::fromArray($sailor->id, $choices)));
    }

    // Submit
    $form->add(new XSubmitInput("match_sailors", "Update database"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    require_once('public/UpdateManager.php');

    // Check $args
    if (isset($args['match_sailors'])) {
      unset($args['match_sailors']);

      $divs = Division::getAssoc();
      $reals = array();
      foreach ($this->SCHOOL->getSailors() as $sailor)
        $reals[$sailor->id] = $sailor;

      $temps = array();
      foreach ($this->SCHOOL->getUnregisteredSailors() as $sailor)
        $temps[$sailor->id] = $sailor;

      $replaced = 0;
      $affected = array();
      // Process each temp id
      foreach ($args as $id => $value) {
        $value = trim($value);
        if (strlen($value) == 0)
          continue;

        // Check that the id and value are valid
        if (isset($reals[$value]) && isset($temps[$id])) {
          $real = $reals[$value];
          $temp = $temps[$id];

          // Notify the affected regattas to redo their RPs
          foreach ($divs as $div) {
            foreach (RpManager::getRegattas($temp, null, $div) as $reg) {
              UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_RP, $this->SCHOOL);
              $affected[$reg->id] = $reg;
            }
          }

          // Replace
          RpManager::replaceTempActual($temp, $real);
          $replaced++;
        }
      }
      if (count($affected) > 0) {
        Session::pa(new PA(sprintf("Affected %s regattas retroactively.", count($affected))));
      }
      if ($replaced > 0) {
        Session::pa(new PA("Updated $replaced temporary sailor(s)."));
      }
      else {
        Session::pa(new PA("No sailors updated.", PA::I));
      }
    }
  }
}
?>