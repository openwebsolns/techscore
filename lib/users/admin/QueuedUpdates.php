<?php
use \users\AbstractUserPane;
use \xml5\PageWhiz;

/**
 * View list of pending database updates.
 *
 * @author Dayan Paez
 * @version 2015-02-20
 */
class QueuedUpdates extends AbstractUserPane {

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
      $count = count($this->getPendingBySection($label, false));
      $link = new XA($this->link(array('section'=>$label)), $this->labels[$label]);
      if ($count == 0) {
        $count = new XImg('/inc/img/s.png', "✓");
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
    $pending = $this->getPendingBySection($section, true);
    if (count($pending) == 0) {
      $p->add(new XValid("No pending items for this queue."));
      return;
    }

    $whiz = new PageWhiz(count($pending), self::NUM_PER_PAGE, $this->link(), $args);
    $p->add($whiz->getPageLinks());
    $pending = $whiz->getSlice($pending);

    $p->add(
      $table = new XQuickTable(
        array('class'=>'pending-queue-table full'),
        array(
          "Time",
          "Resource",
          "Activity",
          "Argument",
          "Attempts",
        )
      )
    );
    foreach ($pending as $update) {
      $attempts = $update->attempt_count;
      if ($attempts >= UpdateManager::MAX_ATTEMPTS) {
        $attempts = new XImg(WS::link('/inc/img/i.png'), "⚠", array('title' => "In DLQ"));
      }

      $table->addRow(
        array(
          new XSpan(DB::howLongFrom($update->request_time), array('title' => $update->request_time->format('c'))),
          $this->getObjectNameForUpdate($update),
          ucwords($update->activity),
          $this->getArgumentForUpdate($update),
          $attempts,
        )
      );
    }
  }

  /**
   * Helper method returns the correct queue by class constant.
   *
   * @param const $section one of the class constants.
   * @param boolean $includeDlq true to include DLQ items in response.
   * @throws InvalidArgumentException if unknown section.
   */
  private function getPendingBySection($section, $includeDlq) {
    switch ($section) {
    case self::REGATTA:
      return UpdateManager::getPendingRequests($includeDlq);
    case self::SEASON:
      return UpdateManager::getPendingSeasons($includeDlq);
    case self::SCHOOL:
      return UpdateManager::getPendingSchools($includeDlq);
    case self::FILE:
      return UpdateManager::getPendingFiles($includeDlq);
    case self::CONFERENCE:
      return UpdateManager::getPendingConferences($includeDlq);
    case self::SAILOR:
      return UpdateManager::getPendingSailors($includeDlq);
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
    if ($obj instanceof UpdateFileRequest) {
      return $obj->file;
    }
    if ($obj instanceof UpdateSailorRequest)
      return $obj->sailor;
    return new XEm("Unknown");
  }

  private function getArgumentForUpdate(AbstractUpdate $obj) {
    if ($obj instanceof UpdateSeasonRequest)
      return '--';
    if ($obj instanceof UpdateFileRequest)
      return '--';
    return ($obj->argument === null) ? '--' : $obj->argument;
  }

  public function process(Array $args) {
    throw new SoterException("Not yet implemented.");
  }
}
?>
