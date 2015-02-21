<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * View list of pending database updates.
 *
 * @author Dayan Paez
 * @version 2015-02-20
 */
class QueuedUpdates extends AbstractAdminUserPane {

  const REGATTA = 'regatta';
  const SCHOOL = 'school';
  const SEASON = 'season';
  const CONFERENCE = 'conference';
  const SAILOR = 'sailor';
  const FILE = 'file';

  const NUM_PER_PAGE = 20;

  private $labels;

  public function __construct(Account $user) {
    parent::__construct("Pending updates", $user);
    $this->labels = array(
      self::REGATTA => "Regattas",
      self::SCHOOL => "Schools",
      self::SEASON => "Seasons and Front Page",
      self::CONFERENCE => DB::g(STN::CONFERENCE_TITLE),
      self::SAILOR => "Sailors",
      self::FILE => "Public Files",
    );
  }

  public function fillHTML(Array $args) {

    // ------------------------------------------------------------
    // Specific queue?
    // ------------------------------------------------------------
    if (isset($args['section'])) {
      try {
        $labels = $this->labels;
        if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY) === null)
          unset($labels[self::CONFERENCE]);
        if (DB::g(STN::SAILOR_PROFILES) === null)
          unset($labels[self::SAILOR]);
        $section = DB::$V->reqKey($args, 'section', $labels, "Invalid section requested.");
        $this->fillSection($section, $args);
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // All queues
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Update queue"));
    $p->add(new XP(array(), "Choose the update queue to view from the list below."));
    $p->add($table = new XQuickTable(array('id'=>'queues-table', 'class'=>'full'), array("Type", "Pending", "Last run")));

    require_once('public/UpdateManager.php');
    $segments = array(
      array(
        self::REGATTA,
        UpdateManager::getLastRegattaCompleted(),
      ),

      array(
        self::SEASON,
        UpdateManager::getLastSeasonCompleted(),
      ),

      array(
        self::SCHOOL,
        UpdateManager::getLastSchoolCompleted(),
      ),

      array(
        self::FILE,
        UpdateManager::getLastFileCompleted(),
      ),
    );

    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY)) {
      $segments[] = array(
        self::CONFERENCE,
        UpdateManager::getLastConferenceCompleted(),
      );
    }

    if (DB::g(STN::SAILOR_PROFILES)) {
      $segments[] = array(
        self::SAILOR,
        UpdateManager::getLastSailorCompleted(),
      );
    }

    // Populate the table
    foreach ($segments as $segment) {
      $label = $segment[0];
      $last = $segment[1];
      $count = count($this->getPendingBySection($label));
      $link = $this->labels[$label];
      if ($count == 0) {
        $count = new XImg('/inc/img/s.png', "✓");
      }
      else {
        $link = new XA($this->link(array('section'=>$label)), $link);
      }
      $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);

      $table->addRow(array($link, $count, $date));
    }
  }

  /**
   * Shows the pending queue for the given section.
   *
   * @param const $section one of the class constants
   */
  private function fillSection($section, Array $args) {
    $this->PAGE->addContent(new XP(array(), new XA($this->link(), "← Back to all queues")));
    $this->PAGE->addContent($p = new XPort(sprintf("Pending items for %s", $this->labels[$section])));
    $pending = $this->getPendingBySection($section);
    if (count($pending) == 0) {
      $p->add(new XP(array('class'=>'warning'), "No pending items for this queue."));
      return;
    }

    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz(count($pending), self::NUM_PER_PAGE, $this->link(), $args);
    $p->add($whiz->getPageLinks());
    $pending = $whiz->getSlice($pending);

    $p->add(
      $table = new XQuickTable(
        array('class'=>'pending-queue-table full'),
        array(
          "Time",
          $this->labels[$section],
          "Activity",
          "Argument",
        )
      )
    );
    foreach ($pending as $update) {
      $table->addRow(
        array(
          DB::howLongFrom($update->request_time),
          $this->getObjectNameForUpdate($update),
          ucwords($update->activity),
          ($update->argument === null) ? '--' : $update->argument,
        )
      );
    }
  }

  /**
   * Helper method returns the correct queue by class constant.
   *
   * @param const $section one of the class constants.
   * @throws InvalidArgumentException if unknown section.
   */
  private function getPendingBySection($section) {
    require_once('public/UpdateManager.php');
    switch ($section) {
    case self::REGATTA:
      return UpdateManager::getPendingRequests();
    case self::SEASON:
      return UpdateManager::getPendingSeasons();
    case self::SCHOOL:
      return UpdateManager::getPendingSchools();
    case self::FILE:
      return UpdateManager::getPendingFiles();
    case self::CONFERENCE:
      return UpdateManager::getPendingConferences();
    case self::SAILOR:
      return UpdateManager::getPendingSailors();
    default:
      throw new InvalidArgumentException("Invalid section provided: $section.");
    }
  }

  private function getObjectNameForUpdate(AbstractUpdate $obj) {
    if ($obj instanceof UpdateRequest)
      return $obj->regatta->name;
    if ($obj instanceof UpdateSeasonRequest)
      return $obj->season->fullString();
    if ($obj instanceof UpdateSchoolRequest)
      return $obj->school->nick_name;
    if ($obj instanceof UpdateConferenceRequest)
      return $obj->conference->name;
    if ($obj instanceof UpdateFileRequest)
      return $obj->file->id;
    if ($obj instanceof UpdateSailorRequest)
      return $obj->sailor;
    return new XEm("Unknown");
  }

  public function process(Array $args) {
    throw new SoterException("Not yet implemented.");
  }
}
?>