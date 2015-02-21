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
    $last = UpdateManager::getLastRegattaCompleted();
    $count = count(UpdateManager::getPendingRequests());
    if ($count == 0) {
      $count = new XImg('/inc/img/s.png', "✓");
    }
    $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
    $table->addRow(
      array(
        new XA($this->link(array('r'=>self::REGATTA)), $this->labels[self::REGATTA]),
        $count,
        $date
      ));

    $last = UpdateManager::getLastSeasonCompleted();
    $count = count(UpdateManager::getPendingSeasons());
    if ($count == 0) {
      $count = new XImg('/inc/img/s.png', "✓");
    }
    $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
    $table->addRow(
      array(
        new XA($this->link(array('r'=>self::SEASON)), $this->labels[self::SEASON]),
        $count,
        $date
      ));

    $last = UpdateManager::getLastSchoolCompleted();
    $count = count(UpdateManager::getPendingSchools());
    if ($count == 0) {
      $count = new XImg('/inc/img/s.png', "✓");
    }
    $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
    $table->addRow(
      array(
        new XA($this->link(array('r'=>self::SCHOOL)), $this->labels[self::SCHOOL]),
        $count,
        $date
      ));

    if (DB::g(STN::PUBLISH_CONFERENCE_SUMMARY)) {
      $last = UpdateManager::getLastConferenceCompleted();
      $count = count(UpdateManager::getPendingConferences());
      if ($count == 0) {
        $count = new XImg('/inc/img/s.png', "✓");
      }
      $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
      $table->addRow(
        array(
          new XA($this->link(array('r'=>self::CONFERENCE)), $this->labels[self::CONFERENCE]),
          $count,
          $date
        ));
    }

    if (DB::g(STN::SAILOR_PROFILES)) {
      $last = UpdateManager::getLastSailorCompleted();
      $count = count(UpdateManager::getPendingSailors());
      if ($count == 0) {
        $count = new XImg('/inc/img/s.png', "✓");
      }
      $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
      $table->addRow(
        array(
          new XA($this->link(array('r'=>self::SAILOR)), $this->labels[self::SAILOR]),
          $count,
          $date
        ));
    }

    $last = UpdateManager::getLastFileCompleted();
    $count = count(UpdateManager::getPendingFiles());
    if ($count == 0) {
      $count = new XImg('/inc/img/s.png', "✓");
    }
    $date = ($last === null) ? "N/A" : DB::howLongFrom($last->completion_time);
    $table->addRow(
      array(
        new XA($this->link(array('r'=>self::FILE)), $this->labels[self::FILE]),
        $count,
        $date
      ));
  }

  public function process(Array $args) {
    throw new SoterException("Not yet implemented.");
  }
}
?>