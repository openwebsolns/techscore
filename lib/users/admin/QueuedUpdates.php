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
    $this->PAGE->addContent($p = new XPort("Update queue"));
    $p->add(new XP(array(), "Choose the update queue to view from the list below."));
    $p->add($table = new XQuickTable(array('id'=>'queues-table'), array("Type", "Pending", "Last run")));

    require_once('public/UpdateManager.php');
    $segments = array(
      array(
        self::REGATTA,
        UpdateManager::getLastRegattaCompleted(),
        count(UpdateManager::getPendingRequests()),
      ),

      array(
        self::SEASON,
        UpdateManager::getLastSeasonCompleted(),
        count(UpdateManager::getPendingSeasons()),
      ),

      array(
        self::SCHOOL,
        UpdateManager::getLastSchoolCompleted(),
        count(UpdateManager::getPendingSchools()),
      ),

      array(
        self::FILE,
        UpdateManager::getLastFileCompleted(),
        count(UpdateManager::getPendingFiles()),
      ),
    );

    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY)) {
      $segments[] = array(
        self::CONFERENCE,
        UpdateManager::getLastConferenceCompleted(),
        count(UpdateManager::getPendingConferences()),
      );
    }

    if (DB::g(STN::SAILOR_PROFILES)) {
      $segments[] = array(
        self::SAILOR,
        UpdateManager::getLastSailorCompleted(),
        count(UpdateManager::getPendingSailors()),
      );
    }

    // Populate the table
    foreach ($segments as $segment) {
      $label = $segment[0];
      $last = $segment[1];
      $count = $segment[2];
      $link = $this->labels[$label];
      if ($count == 0) {
        $count = new XImg('/inc/img/s.png', "✓");
      }
      else {
        $link = new XA($this->link(array('r'=>$label)), $link);
      }
      $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);

      $table->addRow(array($link, $count, $date));
    }
  }

  public function process(Array $args) {
    throw new SoterException("Not yet implemented.");
  }
}
?>