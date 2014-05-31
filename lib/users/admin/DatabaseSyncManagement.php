<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Trigger and view database sync reports.
 *
 * Syncs with the database are non-trivial and should be allowed to
 * only some users, provided that they do not overload the system with
 * requests.
 *
 * @author Dayan Paez
 * @created 2014-05-31
 */
class DatabaseSyncManagement extends AbstractAdminUserPane {

  const TIMEOUT_SECONDS = 300;

  private $sailors_url;
  private $coaches_url;
  private $schools_url;

  public function __construct(Account $user) {
    parent::__construct("Database sync", $user);

    $this->sailors_url = DB::g(STN::SAILOR_API_URL);
    $this->coaches_url = DB::g(STN::COACH_API_URL);
    $this->schools_url = DB::g(STN::SCHOOL_API_URL);

    if ($this->sailors_url === null && $this->coaches_url === null && $this->schools_url === null)
      throw new PaneException("No database syncing available.");
  }

  public function fillHTML(Array $args) {
    $past_syncs = DB::getAll(DB::$SYNC_LOG);

    // ------------------------------------------------------------
    // Trigger a database sync
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Sync Databases"));

    // Timeout?
    if (count($past_syncs) > 0) {
      $interval = new DateInterval('P0DT' . self::TIMEOUT_SECONDS . 'S');
      $cutoff = new DateTime();
      $cutoff->sub($interval);
      $last = $past_syncs[0];

      if ($last->started_at > $cutoff) {
        $until = clone($last->started_at);
        $until->add($interval);
        $p->add(new XP(array('class'=>'warning'),
                       array("The databases were last synced ", DB::howLongAgo($last->started_at), ". Please try again in ", DB::howLongUntil($until), ".")));
        return;
      }
    }

    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "Select which part(s) of the database to sync from the list below."));
    $prefix = "Sync:";
    if ($this->sailors_url !== null) {
      $form->add(new FItem($prefix, new FCheckbox(Sync_Log::SAILORS, 1, "Sailors", true)));
      $prefix = "";
    }
    if ($this->coaches_url !== null) {
      $form->add(new FItem($prefix, new FCheckbox(Sync_Log::COACHES, 1, "Coaches", true)));
      $prefix = "";
    }
    if ($this->schools_url !== null) {
      $form->add(new FItem($prefix, new FCheckbox(Sync_Log::SCHOOLS, 1, "Schools", true)));
    }

    $form->add(new XSubmitP('sync', "Sync now"));
  }

  public function process(Array $args) {
    if (isset($args['sync'])) {
      $sailors = ($this->sailors_url !== null && DB::$V->incInt($args, Sync_Log::SAILORS, 1, 2) == 1);
      $coaches = ($this->coaches_url !== null && DB::$V->incInt($args, Sync_Log::COACHES, 1, 2) == 1);
      $schools = ($this->schools_url !== null && DB::$V->incInt($args, Sync_Log::SCHOOLS, 1, 2) == 1);

      if (!$sailors && !$coaches && !$schools)
        throw new SoterException("Nothing to sync.");
      
      require_once('scripts/SyncDB.php');
      $syncer = new SyncDB();
      $log = $syncer->run($schools, $sailors, $coaches);
      
      Session::pa(new PA(sprintf("Database synced: %d schools, %d sailors, %d coaches added.",
                                 count($log->getSchools()),
                                 count($log->getSailors()),
                                 count($log->getCoaches()))));
    }
  }
}
?>